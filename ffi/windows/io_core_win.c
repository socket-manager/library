#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <mswsock.h>
#include <stdint.h>


#pragma comment(lib, "ws2_32.lib")

#define IO_EVENT_READ        1
#define IO_EVENT_WRITE       2
#define IO_EVENT_ERROR       3
#define IO_EVENT_DISCONNECT  4
#define IO_EVENT_ACCEPT      5   // AcceptEx 用

#define MAX_EVENTS           128

// AcceptEx 用バッファサイズ（IPv4 前提）
#define ACCEPT_EX_ADDR_BUF   ((sizeof(SOCKADDR_IN) + 16) * 2)
// 先行発行する AcceptEx の本数
#define ACCEPT_EX_PREPOST    16

typedef struct {
    int     handle;
    int     event_type;
    int     error_code;
    size_t  bytes;
    void   *user_data;
} io_event;

typedef struct {
    int       count;
    io_event  events[MAX_EVENTS];
} io_event_list;

/* IOCP 用エントリ（内部専用） */
typedef struct io_fd_entry {
    SOCKET     fd;
    OVERLAPPED ov_read;
    int        active;

    int        is_listen;

    // ★ listen ソケット専用：複数 AcceptEx スロット
    OVERLAPPED ov_accept[ACCEPT_EX_PREPOST];
    OVERLAPPED ov_accept_recv[ACCEPT_EX_PREPOST];
    SOCKET     accept_sock[ACCEPT_EX_PREPOST];
    char       accept_buf[ACCEPT_EX_PREPOST][ACCEPT_EX_ADDR_BUF];
    int        pending_accept[ACCEPT_EX_PREPOST];  // AcceptEx 揺れ吸収用

    struct io_fd_entry *next;
} io_fd_entry;

/* fd → entry のハッシュテーブル用ノード */
typedef struct fd_map_entry {
    SOCKET               fd;
    io_fd_entry         *entry;
    struct fd_map_entry *next;
} fd_map_entry;

typedef BOOL (PASCAL *LPFN_ACCEPTEX)(
    SOCKET sListenSocket,
    SOCKET sAcceptSocket,
    PVOID lpOutputBuffer,
    DWORD dwReceiveDataLength,
    DWORD dwLocalAddressLength,
    DWORD dwRemoteAddressLength,
    LPDWORD lpdwBytesReceived,
    LPOVERLAPPED lpOverlapped
);

typedef struct {
    // クライアントポート用
    HANDLE        iocp;

    // fd → entry のハッシュテーブル
    fd_map_entry **fd_map;        // ポインタ配列（バケット配列）
    int            fd_map_size;   // バケット数
    io_fd_entry   *fd_list_head;

    // Listenポート用
    SOCKET        *listen_fds;
    int            listen_count;
    int            listen_capacity;

    // AcceptEx 関数ポインタ
    LPFN_ACCEPTEX  lpAcceptEx;
} io_context;

/* 現在のチックカウントを取得 */
static uint64_t now_ms(void)
{
    return (uint64_t)GetTickCount64();
}

/* ハッシュ関数 */
static int io_hash_fd(io_context *ctx, SOCKET fd)
{
    unsigned long long v = (unsigned long long)fd;
    return (int)(v % (unsigned long long)ctx->fd_map_size);
}

/* fd → entry を検索（存在しなければ NULL） */
typedef struct io_lookup_result {
    io_fd_entry *entry;   // 見つかったエントリ
    int          index;   // AcceptFD の場合はスロット番号、通常は -1
} io_lookup_result;
static io_lookup_result io_get_entry(io_context *ctx, SOCKET fd)
{
    io_lookup_result r;
    r.entry = NULL;
    r.index = -1;

    if (ctx == NULL || ctx->fd_map == NULL || ctx->fd_map_size <= 0) {
        return r;
    }

    // ① まず fd_map で通常検索（ListenFD / ClientFD）
    int h = io_hash_fd(ctx, fd);
    fd_map_entry *cur = ctx->fd_map[h];

    while (cur) {
        if (cur->fd == fd) {
            r.entry = cur->entry;
            r.index = -1;
            return r;
        }
        cur = cur->next;
    }

    // ② 見つからなければ AcceptFD の可能性
    //   → ListenFD のエントリを総当たりして AcceptFD を探す
    io_fd_entry *e = ctx->fd_list_head;

    while (e) {
        if (e->is_listen) {
            for (int i = 0; i < ACCEPT_EX_PREPOST; i++) {
                if (e->accept_sock[i] == fd) {
                    r.entry = e;   // ListenFD の entry
                    r.index = i;   // AcceptFD のスロット番号
                    return r;
                }
            }
        }
        e = e->next;  // io_fd_entry の全リストを辿る
    }

    // ③ それでも見つからなければ無効
    return r;
}

/* fd → entry を新規作成して登録 */
static io_fd_entry *io_create_entry(io_context *ctx, SOCKET fd)
{
    if (ctx == NULL || ctx->fd_map == NULL || ctx->fd_map_size <= 0) {
        return NULL;
    }

    int h = io_hash_fd(ctx, fd);

    io_fd_entry *e = (io_fd_entry *)calloc(1, sizeof(io_fd_entry));
    if (e == NULL) {
        return NULL;
    }

    fd_map_entry *node = (fd_map_entry *)malloc(sizeof(fd_map_entry));
    if (node == NULL) {
        free(e);
        return NULL;
    }

    e->fd        = fd;
    e->active    = 0;
    e->is_listen = 0;
    e->next      = ctx->fd_list_head;
    ctx->fd_list_head = e;

    ZeroMemory(&e->ov_read, sizeof(OVERLAPPED));

    for (int i = 0; i < ACCEPT_EX_PREPOST; i++) {
        e->accept_sock[i]    = INVALID_SOCKET;
        e->pending_accept[i] = 0;
        ZeroMemory(&e->ov_accept[i], sizeof(OVERLAPPED));
        // accept_buf は calloc で 0 初期化済み
    }

    node->fd    = fd;
    node->entry = e;
    node->next  = ctx->fd_map[h];
    ctx->fd_map[h] = node;

    return e;
}

/* fd に対応するfd_mapエントリを削除（io_detach_entry 用） */
static void io_remove_from_fd_map(io_context *ctx, SOCKET fd)
{
    if (ctx == NULL || ctx->fd_map == NULL || ctx->fd_map_size <= 0) {
        return;
    }

    int h = io_hash_fd(ctx, fd);
    fd_map_entry *cur  = ctx->fd_map[h];
    fd_map_entry *prev = NULL;

    while (cur) {
        if (cur->fd == fd) {
            // チェーンから外す
            if (prev) {
                prev->next = cur->next;
            } else {
                // 先頭要素だった場合
                ctx->fd_map[h] = cur->next;
            }

            free(cur);
            return;
        }

        prev = cur;
        cur  = cur->next;
    }

    // 見つからなければ何もしない
}

/* fd に対応するio_fd_entryエントリを削除（io_detach_entry 用） */
static void io_remove_from_fd_list(io_context *ctx, io_fd_entry *target)
{
    if (ctx == NULL || target == NULL) {
        return;
    }

    io_fd_entry *cur  = ctx->fd_list_head;
    io_fd_entry *prev = NULL;

    while (cur) {
        if (cur == target) {
            // チェーンから外す
            if (prev) {
                prev->next = cur->next;
            } else {
                // 先頭要素だった場合
                ctx->fd_list_head = cur->next;
            }
            // ここでは free はしない
            //    メモリ解放は呼び出し側（io_detach_entry）が責任を持つ
            return;
        }

        prev = cur;
        cur  = cur->next;
    }

    // 見つからなければ何もしない
}

/* fd に対応するエントリを削除（unregister / close 用） */
static int io_post_accept(io_context *ctx, io_fd_entry *e, int slot);
void io_detach_entry(io_context *ctx, SOCKET fd)
{
    if (ctx == NULL) {
        return;
    }

    // entry と index を同時に取得
    io_lookup_result r = io_get_entry(ctx, fd);
    io_fd_entry *e = r.entry;

    if (e == NULL) {
        return; // 未登録
    }

    // ============================================================
    // ① AcceptFD の場合（複合 AcceptEx スロット）
    // ============================================================
    if (r.index >= 0) {
        int i = r.index;

        // AcceptEx が未完了ならキャンセル
        CancelIoEx((HANDLE)e->accept_sock[i], &e->ov_accept[i]);

        // AcceptFD を閉じる
        closesocket(e->accept_sock[i]);
        e->accept_sock[i] = INVALID_SOCKET;

        // OVERLAPPED を初期化（再利用のため）
        ZeroMemory(&e->ov_accept[i], sizeof(OVERLAPPED));

        // AcceptEx を再発行（PREPOST を維持）
        io_post_accept(ctx, e, i);

        return; // AcceptFD の detach はここで完了
    }

    // ============================================================
    // ② ListenFD または ClientFD の場合
    // ============================================================

    // ListenFD の場合は複合 AcceptFD を全部キャンセル
    if (e->is_listen) {
        for (int i = 0; i < ACCEPT_EX_PREPOST; i++) {
            if (e->accept_sock[i] != INVALID_SOCKET) {
                CancelIoEx((HANDLE)e->accept_sock[i], &e->ov_accept[i]);
                closesocket(e->accept_sock[i]);
                e->accept_sock[i] = INVALID_SOCKET;
            }
        }
    }

    // READ/WRITE の未完了 I/O をキャンセル
    CancelIoEx((HANDLE)e->fd, &e->ov_read);

    // ソケットを閉じる
    closesocket(e->fd);

    // fd_map から削除
    io_remove_from_fd_map(ctx, e->fd);

    // fd_list_head から削除
    io_remove_from_fd_list(ctx, e);

    // メモリ解放
    free(e);
}

/* read readiness 監視用の「0 バイト WSARecv」を発行 */
static int io_post_zero_recv(io_context *ctx, io_fd_entry *e)
{
    (void)ctx; // 現状 ctx は未使用

    // listen ソケットには 0 バイト WSARecv を投げない
    if (e->is_listen) {
        return 0;
    }

    ZeroMemory(&e->ov_read, sizeof(OVERLAPPED));

    DWORD flags = 0;
    WSABUF buf;

    /* 0 バイト読み込み用のダミー */
    buf.buf = NULL;
    buf.len = 0;

    int r = WSARecv(e->fd, &buf, 1, NULL, &flags, &e->ov_read, NULL);
    if (r == SOCKET_ERROR) {
        int err = WSAGetLastError();
        if (err == WSAENOTCONN) {
            // AcceptEx 直後の「接続前揺れ」は完全に無視
            return 0;
        }
        if (err != WSA_IO_PENDING) {
            /* ここではソケットは閉じない。PHP 側が管理する前提。 */
            e->active         = 0;
            return -1;
        }
    }

    return 0;
}

// ListenFD のエントリ + スロット番号を指定して、
// accept_sock[slot] に対して 0 バイト recv を投げる
int io_post_zero_recv_accept(io_context *ctx, io_fd_entry *listen_e, int slot)
{
    SOCKET s = listen_e->accept_sock[slot];
    OVERLAPPED *ov = &listen_e->ov_accept_recv[slot]; // 新しく用意する配列

    ZeroMemory(ov, sizeof(OVERLAPPED));

    DWORD flags = 0;
    DWORD bytes = 0;

    WSABUF buf;
    buf.buf = NULL;
    buf.len = 0;

    int rc = WSARecv(s, &buf, 1, &bytes, &flags, ov, NULL);
    if (rc == SOCKET_ERROR) {
        int err = WSAGetLastError();
        if (err != WSA_IO_PENDING) {
            return -1;
        }
    }
    return 0;
}

/* AcceptEx を 1 本発行 */
static int io_post_accept(io_context *ctx, io_fd_entry *e, int slot)
{
    if (ctx == NULL || ctx->lpAcceptEx == NULL || e == NULL || !e->is_listen) {
        return -1;
    }
    if (slot < 0 || slot >= ACCEPT_EX_PREPOST) {
        return -1;
    }

    SOCKET *pfd = &e->accept_sock[slot];

    // 1) AcceptFD が無ければ作る
    if (*pfd == INVALID_SOCKET) {
        SOCKET s = WSASocket(
            AF_INET,
            SOCK_STREAM,
            IPPROTO_TCP,
            NULL,
            0,
            WSA_FLAG_OVERLAPPED
        );
        if (s == INVALID_SOCKET) {
            return -1;
        }

        // 2) IOCP に関連付ける（key = s）
        if (CreateIoCompletionPort((HANDLE)s, ctx->iocp, (ULONG_PTR)s, 0) == NULL) {
            closesocket(s);
            return -1;
        }

        *pfd = s;
    }

    // 3) OVERLAPPED を初期化
    OVERLAPPED *ov = &e->ov_accept[slot];
    ZeroMemory(ov, sizeof(OVERLAPPED));

    // 4) AcceptEx 用バッファ（ローカル＋リモートアドレス）
    //    ここでは e->accept_buf[slot] / ACCEPT_EX_ADDR_BUF を使う前提
    char *buf   = e->accept_buf[slot];

    DWORD bytes = 0;
    BOOL ok = ctx->lpAcceptEx(
        e->fd,              // ListenFD
        *pfd,               // AcceptFD（スロットごと）
        buf,
        0,                  // 先読みデータ長（ここでは 0）
        ACCEPT_EX_ADDR_BUF / 2,         // ローカルアドレス領域
        ACCEPT_EX_ADDR_BUF / 2,         // リモートアドレス領域
        &bytes,
        ov
    );

    if (!ok) {
        DWORD err = WSAGetLastError();
        if (err != WSA_IO_PENDING) {
            // 失敗した AcceptFD は閉じてスロットを空にしておく
            closesocket(*pfd);
            *pfd = INVALID_SOCKET;
            return -1;
        }
    }

    return 0;
}

/**
 * 初期化処理
 *
 * ctx: io_context* IOCP コンテキスト
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_core_init(io_context *ctx)
{
    WSADATA wsa;

    if (ctx == NULL) {
        return -1;
    }

    if (WSAStartup(MAKEWORD(2,2), &wsa) != 0) {
        return -1;
    }

    ctx->iocp = CreateIoCompletionPort(INVALID_HANDLE_VALUE, NULL, 0, 0);
    if (ctx->iocp == NULL) {
        WSACleanup();
        return -1;
    }

    ctx->lpAcceptEx = NULL;
    ctx->fd_list_head = NULL;

    // fd_map（ポインタ配列）の初期化
    ctx->fd_map_size = 65536; // 適当な初期サイズ（必要なら調整可）
    ctx->fd_map = (fd_map_entry **)calloc(ctx->fd_map_size, sizeof(fd_map_entry *));
    if (ctx->fd_map == NULL) {
        CloseHandle(ctx->iocp);
        ctx->iocp = NULL;
        WSACleanup();
        return -1;
    }

    // Listen 用配列の初期化
    ctx->listen_capacity = 16;
    ctx->listen_count    = 0;
    ctx->listen_fds      = (SOCKET *)calloc(ctx->listen_capacity, sizeof(SOCKET));
    if (ctx->listen_fds == NULL) {
        free(ctx->fd_map);
        ctx->fd_map = NULL;
        CloseHandle(ctx->iocp);
        ctx->iocp = NULL;
        WSACleanup();
        return -1;
    }

    return 0;
}

/**
 * ソケットハンドルを IO ドライバへ登録
 *
 * ctx: io_context* IOCP コンテキスト
 * fd : ソケットハンドル
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_register(io_context *ctx, int fd)
{
    if (ctx == NULL) {
        return -1;
    }

    SOCKET s = (SOCKET)fd;
    io_lookup_result r = io_get_entry(ctx, s);
    io_fd_entry *e = r.entry;

    if (e != NULL && r.index >= 0) {
        // AcceptFD は io_post_accept() が管理する
        // io_register() の対象ではない
        return 0;
    }

    if (e == NULL) {
        e = io_create_entry(ctx, s);
        if (e == NULL) {
            return -1;
        }
    }

    if (e->active) {
        /* 既に登録済みなら何もしない */
        return 0;
    }

    if (CreateIoCompletionPort((HANDLE)s, ctx->iocp, (ULONG_PTR)s, 0) == NULL) {
        return -1;
    }

    e->fd             = s;
    e->active         = 1;
    e->is_listen      = 0;

    /* read readiness 相当の監視を開始（0 バイト WSARecv） */
    if (io_post_zero_recv(ctx, e) < 0) {
        /* ここでもソケットは閉じない。PHP 側が管理。 */
        return -1;
    }

    return 0;
}

/**
 * ソケットハンドルを Listen用IO ドライバへ登録（AcceptEx 対応）
 *
 * ctx: io_context* IOCP コンテキスト
 * fd : ソケットハンドル
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_registerListen(io_context *ctx, int fd)
{
    if (ctx == NULL) {
        return -1;
    }

    SOCKET s = (SOCKET)fd;

    io_lookup_result r = io_get_entry(ctx, s);
    if (r.entry != NULL && r.index >= 0) {
        // AcceptFD は listen 登録の対象外
        return 0;
    }

    // listen ソケットも IOCP に登録し、io_fd_entry を取得
    io_fd_entry *e = r.entry;
    if (e == NULL) {
        e = io_create_entry(ctx, s);
        if (e == NULL) {
            return -1;
        }
    }

    // 既に listen 登録済みなら何もしない
    if (e->active && e->is_listen) {
        return 0;
    }

    // 最初の listen 登録時に AcceptEx を取得
    if (ctx->lpAcceptEx == NULL) {
        GUID guid = WSAID_ACCEPTEX;
        DWORD bytes = 0;
        LPFN_ACCEPTEX fn = NULL;

        if (WSAIoctl(s,
                     SIO_GET_EXTENSION_FUNCTION_POINTER,
                     &guid, sizeof(guid),
                     &fn, sizeof(fn),
                     &bytes, NULL, NULL) == SOCKET_ERROR) {
            return -1;
        }
        ctx->lpAcceptEx = fn;
    }

    // 必要なら listen_fds を拡張
    if (ctx->listen_count >= ctx->listen_capacity) {
        int     new_cap = ctx->listen_capacity * 2;
        SOCKET *tmp     = (SOCKET *)realloc(ctx->listen_fds, sizeof(SOCKET) * new_cap);
        if (tmp == NULL) {
            return -1;
        }
        ctx->listen_fds      = tmp;
        ctx->listen_capacity = new_cap;
    }

    ctx->listen_fds[ctx->listen_count++] = s;


    if (CreateIoCompletionPort((HANDLE)s, ctx->iocp, (ULONG_PTR)s, 0) == NULL) {
        return -1;
    }

    e->fd        = s;
    e->active    = 1;
    e->is_listen = 1;

    // AcceptEx を複数本先行発行
    for (int i = 0; i < ACCEPT_EX_PREPOST; i++) {
        e->accept_sock[i]    = INVALID_SOCKET;
        e->pending_accept[i] = 0;
        ZeroMemory(&e->ov_accept[i], sizeof(OVERLAPPED));
        if (io_post_accept(ctx, e, i) < 0) {
            // 一旦エラー扱い（必要ならログなど）
            return -1;
        }
    }

    return 0;
}

/**
 * ソケットハンドルを IO ドライバから解除
 *
 * ctx: io_context* IOCP コンテキスト
 * fd : ソケットハンドル
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_unregister(io_context *ctx, int fd)
{
    if (ctx == NULL) {
        return -1;
    }

    SOCKET s = (SOCKET)fd;

    // entry と index を同時に取得
    io_lookup_result r = io_get_entry(ctx, s);
    io_fd_entry *e = r.entry;

    if (e != NULL && r.index >= 0) {
        // AcceptFD は io_detach_entry() がスロット単位で処理する
        // io_unregister() の対象ではない
        return 0;
    }

    if (e == NULL || !e->active) {
        /* 既に unregister 済みなど */
        return 0;
    }

    /* この fd に対する未完了 I/O をキャンセルする */
    CancelIoEx((HANDLE)s, &e->ov_read);

    if (e->is_listen) {
        // ListenFD の場合は AcceptFD の I/O もキャンセル
        for (int i = 0; i < ACCEPT_EX_PREPOST; i++) {
            if (e->accept_sock[i] != INVALID_SOCKET) {
                CancelIoEx((HANDLE)e->accept_sock[i], &e->ov_accept[i]);
            }
        }
    }

    /* ソケットクローズは PHP 側で行う前提 */

    /* fd_map から削除しつつ、io_fd_entry もここで解放 */
    io_detach_entry(ctx, s);

    return 0;
}

/**
 * イベント待機（AcceptEx + 0バイトWSARecv）
 *
 * ctx: io_context* IOCP コンテキスト
 * timeout_ms: タイムアウト（ミリ秒）
 * events_ptr: io_event_list*（C 側で count と events[] を埋める）
 *
 * return:
 *   >= 0 : 発生したイベント数
 *   <  0 : エラー
 */
__declspec(dllexport)
int io_select(io_context *ctx, int timeout_ms, void *events_ptr)
{
    io_event_list *events;
    if (ctx == NULL || events_ptr == NULL) {
        return -1;
    }

    events        = (io_event_list *)events_ptr;
    events->count = 0;

    uint64_t start_ms = now_ms();
    uint64_t deadline_ms;

    if (timeout_ms < 0) {
        // 無限待ち扱い：deadline を実質無限に
        deadline_ms = (uint64_t)(~(uint64_t)0);
    } else {
        deadline_ms = start_ms + (uint64_t)timeout_ms;
    }

    while (events->count < MAX_EVENTS) {

        DWORD      bytes = 0;
        ULONG_PTR  key   = 0;
        OVERLAPPED *ov   = NULL;

        BOOL ok = GetQueuedCompletionStatus(
            ctx->iocp,
            &bytes,
            &key,
            &ov,
            0   // ノンブロッキング
        );

        SOCKET s = (SOCKET)key;

        io_lookup_result r = io_get_entry(ctx, s);
        // fd → entry を取得
        io_fd_entry *e = r.entry;
        int slot = r.index;

        // 既に無効状態の場合はスキップ
        if (e == NULL || !e->active) {
            return events->count;
        }

        io_event *ev = &events->events[events->count++];
        ev->bytes      = (size_t)bytes;
        ev->handle     = (int)s;
        ev->error_code = 0;
        ev->event_type = 0;
        ev->user_data  = NULL;

        if (!ok) {
            DWORD err = GetLastError();
            if ((int)err == 995) {
                continue;
            }

            // キューに何もない
            if (ov == NULL) {
                // タイムアウト判定
                if (timeout_ms >= 0 && now_ms() >= deadline_ms) {
                    break;
                }
                // タイムアウトしていないなら、もう一度ループに戻る
                continue;
            }

            if (err == ERROR_NETNAME_DELETED || err == WSAECONNRESET) {
                // 相手側が切断した（RST/FIN 相当）
                ev->event_type = IO_EVENT_DISCONNECT;
                e->active      = 0;     // もう 0 バイト recv は投げない
            } else {
                ev->event_type = IO_EVENT_ERROR;
            }

            // エラーイベント
            ev->error_code = (int)err;

            /* listen ソケットの AcceptEx 完了かどうかを判定 */
            if (e->is_listen == 1) {
                // 次の AcceptEx を仕込む
                e->accept_sock[slot]    = INVALID_SOCKET;
                e->pending_accept[slot] = 0;
                io_post_accept(ctx, e, slot);
            }

            return events->count;
        }

        // 以降は成功イベントの処理

        // slot番号を確定
        if (e->is_listen) {
            for (int i = 0; i < ACCEPT_EX_PREPOST; i++) {
                if (ov == &e->ov_accept[i] || ov == &e->ov_accept_recv[i]) {
                    slot = i;
                    break;
                }
            }
        }

        /* listen ソケットの AcceptEx 完了かどうかを判定 */
        if (e->is_listen == 1 && slot >= 0 && ov == &e->ov_accept[slot]) {
            // AcceptEx 後の必須処理
            if (setsockopt(e->accept_sock[slot], SOL_SOCKET, SO_UPDATE_ACCEPT_CONTEXT,
                        (char *)&e->fd, sizeof(e->fd)) == SOCKET_ERROR) {
                DWORD err = WSAGetLastError();
                ev->event_type = IO_EVENT_ERROR;
                ev->error_code = (int)err;

                e->accept_sock[slot]    = INVALID_SOCKET;
                e->pending_accept[slot] = 0;
                io_post_accept(ctx, e, slot);

                return events->count;
            }

            ev->event_type = IO_EVENT_ACCEPT;
            ev->handle = e->accept_sock[slot];

            e->accept_sock[slot]    = INVALID_SOCKET;
            e->pending_accept[slot] = 0;
            ZeroMemory(&e->ov_accept[slot], sizeof(OVERLAPPED));

            // 以後の read readiness 用に再度 0 バイト recv を投げておく
            io_fd_entry *ce = io_create_entry(ctx, e->accept_sock[slot]);
            if (ce) {
                ce->fd        = s;
                ce->active    = 1;
                ce->is_listen = 0;
                io_post_zero_recv(ctx, ce);
            }
            io_post_accept(ctx, e, slot);

            continue;
        }

        /* listen ソケットの 0 バイト recv 完了かどうかを判定 */
        if (ov == &e->ov_read) {
            // 通常の read readiness
            ev->event_type = IO_EVENT_READ;

            if (e->active) {
                io_post_zero_recv(ctx, e);
            }
        } else {
            ev->event_type = IO_EVENT_ERROR;
            ev->error_code = -1;
            break;
        }
    }

    return events->count;
}

/**
 * 後始末処理
 *
 * ctx: io_context* IOCP コンテキスト
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_core_close(io_context *ctx)
{
    if (ctx == NULL) {
        return -1;
    }

    if (ctx->iocp != NULL) {
        CloseHandle(ctx->iocp);
        ctx->iocp = NULL;
    }

    // fd_map の解放
    if (ctx->fd_map != NULL) {
        for (int i = 0; i < ctx->fd_map_size; i++) {
            fd_map_entry *cur = ctx->fd_map[i];
            while (cur) {
                SOCKET fd = cur->fd;
                cur = cur->next;
                io_detach_entry(ctx, fd);
            }
        }
        free(ctx->fd_map);
        ctx->fd_map = NULL;
        ctx->fd_map_size = 0;
    }

    if (ctx->listen_fds != NULL) {
        free(ctx->listen_fds);
        ctx->listen_fds      = NULL;
        ctx->listen_count    = 0;
        ctx->listen_capacity = 0;
    }

    WSACleanup();
    return 0;
}
