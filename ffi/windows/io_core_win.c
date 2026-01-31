#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>

#pragma comment(lib, "ws2_32.lib")

#define IO_EVENT_READ        1
#define IO_EVENT_WRITE       2
#define IO_EVENT_ERROR       3
#define IO_EVENT_DISCONNECT  4

#define MAX_EVENTS  128

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
    WSAPOLLFD *fds;
    int count;
    int capacity;
} io_context;


/**
 * 初期化処理
 *
 * ctx: io_context* WSAPoll コンテキスト
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_core_init(io_context *ctx)
{
    WSADATA wsa;
    WSAStartup(MAKEWORD(2,2), &wsa);

    if(ctx == NULL)
    {
        return -1;
    }

    ctx->capacity = 4096;
    ctx->fds = malloc(sizeof(WSAPOLLFD) * ctx->capacity);
    ctx->count = 0;

    return 0;
}

/**
 * ソケットハンドルを IO ドライバへ登録
 *
 * ctx: io_context* WSAPoll コンテキスト
 * fd : ソケットハンドル
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_register(io_context *ctx, int fd)
{
    int i;

    if(ctx == NULL)
    {
        return -1;
    }

    // 既に登録済みなら何もしない
    for(i = 0; i < ctx->count; i++)
    {
        if((int)ctx->fds[i].fd == fd)
        {
            return 0;
        }
    }

    if(ctx->count >= ctx->capacity)
    {
        ctx->capacity *= 2;
        ctx->fds = realloc(ctx->fds, sizeof(WSAPOLLFD) * ctx->capacity);
    }
    ctx->fds[ctx->count].fd     = (SOCKET)fd;
    ctx->fds[ctx->count].events = POLLIN;
    ctx->fds[ctx->count].revents = 0;
    ctx->count++;

    return 0;
}

/**
 * ソケットハンドルを IO ドライバから解除
 *
 * ctx: io_context* WSAPoll コンテキスト
 * fd : ソケットハンドル
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_unregister(io_context *ctx, int fd)
{
    int i;

    if(ctx == NULL)
    {
        return -1;
    }

    for(i = 0; i < ctx->count; i++)
    {
        if((int)ctx->fds[i].fd == fd)
        {
            // 後ろの要素を詰める
            ctx->fds[i] = ctx->fds[ctx->count - 1];
            ctx->count--;
            return 0;
        }
    }

    return 0;
}

/**
 * イベント待機
 *
 * ctx: io_context* WSAPoll コンテキスト
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
    io_event_list *events = (io_event_list *)events_ptr;
    int            n, i;

    if(ctx == NULL || events == NULL)
    {
        return -1;
    }

    events->count = 0;

    if(ctx->count == 0)
    {
        // 監視対象が無い場合はタイムアウト扱い
        if(timeout_ms > 0)
        {
            Sleep(timeout_ms);
        }
        return 0;
    }

    n = WSAPoll(ctx->fds, ctx->count, timeout_ms);

    if(n == SOCKET_ERROR)
    {
        return -1;
    }

    if(n == 0)
    {
        return 0;
    }

    for(i = 0; i < ctx->count && events->count < MAX_EVENTS; i++)
    {
        SHORT re = ctx->fds[i].revents;

        if(re == 0)
        {
            continue;
        }

        io_event *ev = &events->events[events->count++];

        ev->handle      = (int)ctx->fds[i].fd;
        ev->bytes       = 0;
        ev->user_data   = NULL;
        ev->error_code  = 0;
        ev->event_type  = 0;

        if(re & POLLIN)
        {
            ev->event_type = IO_EVENT_READ;
        }
        else
        if(re & POLLOUT)
        {
            ev->event_type = IO_EVENT_WRITE;
        }

        if(re & POLLERR)
        {
            ev->event_type  = IO_EVENT_ERROR;
        }

        if(re & POLLHUP)
        {
            ev->event_type  = IO_EVENT_DISCONNECT;
        }
    }

    return events->count;
}

/**
 * 後始末処理
 *
 * ctx: io_context* IOCP ハンドル
 *
 * return:
 *   = 0 : 正常
 *   < 0 : エラー
 */
__declspec(dllexport)
int io_core_close(io_context *ctx)
{
    return 0;
}
