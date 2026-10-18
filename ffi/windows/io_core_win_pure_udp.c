#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <stdint.h>

#pragma comment(lib, "ws2_32.lib")

#define IO_EVENT_READ        1
#define IO_EVENT_ERROR       3
#define IO_EVENT_DISCONNECT  4

#define MAX_EVENTS           128

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

typedef struct {
    char           ip[16]; // "xxx.xxx.xxx.xxx"
    // char           ip[INET_ADDRSTRLEN]; // "xxx.xxx.xxx.xxx"@@@
    unsigned short port;                // host order
    size_t         data_len;
    char           data[];
} udp_accept_t;

/* fd → entry のハッシュテーブル用ノード */
typedef struct io_fd_entry {
    SOCKET              fd;
    OVERLAPPED          ov_read;
    int                 active;

    char               *recv_buf;
    struct sockaddr_in  udp_from;
    int                 udp_from_len;

    struct io_fd_entry *next;
} io_fd_entry;

typedef struct fd_map_entry {
    SOCKET               fd;
    io_fd_entry         *entry;
    struct fd_map_entry *next;
} fd_map_entry;

typedef struct {
    HANDLE        iocp;

    fd_map_entry **fd_map;
    int            fd_map_size;
    io_fd_entry   *fd_list_head;

    size_t         recv_buf_size;
} io_context;

/* 時刻取得 */
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

/* fd → entry 検索 */
static io_fd_entry *io_get_entry(io_context *ctx, SOCKET fd)
{
    if (!ctx || !ctx->fd_map || ctx->fd_map_size <= 0) return NULL;

    int h = io_hash_fd(ctx, fd);
    fd_map_entry *cur = ctx->fd_map[h];

    while (cur) {
        if (cur->fd == fd) return cur->entry;
        cur = cur->next;
    }
    return NULL;
}

/* fd → entry 新規作成 */
static io_fd_entry *io_create_entry(io_context *ctx, SOCKET fd)
{
    if (!ctx || !ctx->fd_map || ctx->fd_map_size <= 0) return NULL;

    int h = io_hash_fd(ctx, fd);

    io_fd_entry *e = (io_fd_entry *)calloc(1, sizeof(io_fd_entry));
    if (!e) return NULL;

    fd_map_entry *node = (fd_map_entry *)malloc(sizeof(fd_map_entry));
    if (!node) {
        free(e);
        return NULL;
    }

    e->recv_buf = (char *)malloc(ctx->recv_buf_size);
    if (!e->recv_buf) {
        free(e);
        free(node);
        return NULL;
    }

    e->fd        = fd;
    e->active    = 0;
    e->next      = ctx->fd_list_head;
    ctx->fd_list_head = e;

    ZeroMemory(&e->ov_read, sizeof(OVERLAPPED));

    node->fd    = fd;
    node->entry = e;
    node->next  = ctx->fd_map[h];
    ctx->fd_map[h] = node;

    return e;
}

/* fd_map から削除 */
static void io_remove_from_fd_map(io_context *ctx, SOCKET fd)
{
    if (!ctx || !ctx->fd_map || ctx->fd_map_size <= 0) return;

    int h = io_hash_fd(ctx, fd);
    fd_map_entry *cur  = ctx->fd_map[h];
    fd_map_entry *prev = NULL;

    while (cur) {
        if (cur->fd == fd) {
            if (prev) prev->next = cur->next;
            else      ctx->fd_map[h] = cur->next;
            free(cur);
            return;
        }
        prev = cur;
        cur  = cur->next;
    }
}

/* fd_list から削除 */
static void io_remove_from_fd_list(io_context *ctx, io_fd_entry *target)
{
    if (!ctx || !target) return;

    io_fd_entry *cur  = ctx->fd_list_head;
    io_fd_entry *prev = NULL;

    while (cur) {
        if (cur == target) {
            if (prev) prev->next = cur->next;
            else      ctx->fd_list_head = cur->next;
            return;
        }
        prev = cur;
        cur  = cur->next;
    }
}

/* WSARecvFrom 発行 */
static int io_post_recvfrom(io_context *ctx, io_fd_entry *e)
{
    ZeroMemory(&e->ov_read, sizeof(OVERLAPPED));

    DWORD flags = 0;
    DWORD bytes = 0;

    WSABUF buf;
    buf.buf = e->recv_buf;
    buf.len = (ULONG)ctx->recv_buf_size;

    e->udp_from_len = sizeof(e->udp_from);

    int r = WSARecvFrom(
        e->fd,
        &buf,
        1,
        &bytes,
        &flags,
        (struct sockaddr *)&e->udp_from,
        &e->udp_from_len,
        &e->ov_read,
        NULL
    );

    if (r == SOCKET_ERROR) {
        int err = WSAGetLastError();
        if (err != WSA_IO_PENDING) {
            e->active = 0;
            return -1;
        }
    }

    return 0;
}

/* 初期化 */
__declspec(dllexport)
int io_core_init(io_context *ctx, size_t recv_buf_size)
{
    WSADATA wsa;

    if (!ctx) return -1;

    if (WSAStartup(MAKEWORD(2,2), &wsa) != 0) {
        return -1;
    }

    ctx->iocp = CreateIoCompletionPort(INVALID_HANDLE_VALUE, NULL, 0, 0);
    if (!ctx->iocp) {
        WSACleanup();
        return -1;
    }

    ctx->recv_buf_size = recv_buf_size;
    ctx->fd_list_head  = NULL;

    ctx->fd_map_size = 65536;
    ctx->fd_map = (fd_map_entry **)calloc(ctx->fd_map_size, sizeof(fd_map_entry *));
    if (!ctx->fd_map) {
        CloseHandle(ctx->iocp);
        ctx->iocp = NULL;
        WSACleanup();
        return -1;
    }

    return 0;
}

/* UDP ソケット登録 */
__declspec(dllexport)
int io_register(io_context *ctx, int fd)
{
    if (!ctx) return -1;

    SOCKET s = (SOCKET)fd;

    io_fd_entry *e = io_get_entry(ctx, s);
    if (!e) {
        e = io_create_entry(ctx, s);
        if (!e) return -1;
    }

    if (e->active) return 0;

    if (CreateIoCompletionPort((HANDLE)s, ctx->iocp, (ULONG_PTR)s, 0) == NULL) {
        return -1;
    }

    e->fd     = s;
    e->active = 1;

    return io_post_recvfrom(ctx, e);
}

/* unregister */
__declspec(dllexport)
int io_unregister(io_context *ctx, int fd)
{
    if (!ctx) return -1;

    SOCKET s = (SOCKET)fd;
    io_fd_entry *e = io_get_entry(ctx, s);

    if (!e || !e->active) return 0;

    CancelIoEx((HANDLE)s, &e->ov_read);
    closesocket(s);

    e->active = 0;

    io_remove_from_fd_map(ctx, s);
    io_remove_from_fd_list(ctx, e);

    if (e->recv_buf) free(e->recv_buf);
    free(e);

    return 0;
}

/* IOCP イベント待ち */
__declspec(dllexport)
int io_select(io_context *ctx, int timeout_ms, void *events_ptr)
{
    if (!ctx || !events_ptr) return -1;

    io_event_list *events = (io_event_list *)events_ptr;
    events->count = 0;

    uint64_t start_ms    = now_ms();
    uint64_t deadline_ms = (timeout_ms < 0)
        ? (uint64_t)(~(uint64_t)0)
        : start_ms + (uint64_t)timeout_ms;

    while (events->count < MAX_EVENTS) {

        DWORD      bytes = 0;
        ULONG_PTR  key   = 0;
        OVERLAPPED *ov   = NULL;

        BOOL ok = GetQueuedCompletionStatus(
            ctx->iocp,
            &bytes,
            &key,
            &ov,
            0
        );

        SOCKET s = (SOCKET)key;

        io_event *ev = &events->events[events->count++];
        ev->bytes      = (size_t)bytes;
        ev->handle     = (int)s;
        ev->error_code = 0;
        ev->event_type = 0;
        ev->user_data  = NULL;

        if (!ok) {
            DWORD err = GetLastError();

            if (ov == NULL) {
                if (timeout_ms >= 0 && now_ms() >= deadline_ms) {
                    events->count--; // この枠は使っていない
                    break;
                }
                events->count--; // 無効枠
                continue;
            }

            io_fd_entry *e = io_get_entry(ctx, s);
            if (!e || !e->active) {
                events->count--;
                continue;
            }

            if ((int)err == ERROR_OPERATION_ABORTED) {
                io_remove_from_fd_map(ctx, e->fd);
                io_remove_from_fd_list(ctx, e);
                if (e->recv_buf) free(e->recv_buf);
                free(e);
                events->count--;
                continue;
            }

            if (err == ERROR_NETNAME_DELETED || err == WSAECONNRESET) {
                ev->event_type = IO_EVENT_DISCONNECT;
            } else {
                ev->event_type = IO_EVENT_ERROR;
            }

            ev->error_code = (int)err;
            e->active      = 0;
            continue;
        }

        if (ov == NULL) {
            events->count--;
            continue;
        }

        io_fd_entry *e = io_get_entry(ctx, s);
        if (!e || !e->active) {
            events->count--;
            continue;
        }

        /* UDP 受信イベント */
        if (ov == &e->ov_read) {

            // @@@サイズゼロでも返却する
            // if (bytes == 0) {
            //     ev->event_type = IO_EVENT_DISCONNECT;
            //     e->active      = 0;
            //     continue;
            // }

            udp_accept_t *pkt = (udp_accept_t *)malloc(sizeof(udp_accept_t) + bytes);
            if (!pkt) {
                ev->event_type = IO_EVENT_ERROR;
                ev->error_code = ERROR_NOT_ENOUGH_MEMORY;
                e->active      = 0;
                continue;
            }

            inet_ntop(AF_INET, &e->udp_from.sin_addr, pkt->ip, sizeof(pkt->ip));
            pkt->port     = ntohs(e->udp_from.sin_port);
            pkt->data_len = (size_t)bytes;
            if (bytes > 0) {
                memcpy(pkt->data, e->recv_buf, bytes);
            }

            ev->event_type = IO_EVENT_READ;
            ev->bytes      = (size_t)bytes;
            ev->user_data  = pkt;

            if (e->active) {
                io_post_recvfrom(ctx, e);
            }

            continue;
        }

        // 未知の OVERLAPPED
        events->count--;
    }

    return events->count;
}

/* 終了処理 */
__declspec(dllexport)
void io_core_close(io_context *ctx)
{
    if (!ctx) return;

    io_fd_entry *cur = ctx->fd_list_head;
    while (cur) {
        io_fd_entry *next = cur->next;
        if (cur->active) {
            CancelIoEx((HANDLE)cur->fd, &cur->ov_read);
            closesocket(cur->fd);
        }
        if (cur->recv_buf) free(cur->recv_buf);
        free(cur);
        cur = next;
    }

    if (ctx->fd_map) {
        for (int i = 0; i < ctx->fd_map_size; i++) {
            fd_map_entry *n = ctx->fd_map[i];
            while (n) {
                fd_map_entry *nn = n->next;
                free(n);
                n = nn;
            }
        }
        free(ctx->fd_map);
    }

    if (ctx->iocp) {
        CloseHandle(ctx->iocp);
    }

    WSACleanup();
}

/**
 * メモリ解放
 *
 * p: メモリポインタ
 */
__declspec(dllexport)
void io_free(void *p)
{
    free(p);
}
