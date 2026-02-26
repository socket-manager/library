#include <sys/epoll.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <fcntl.h>
#include <unistd.h>
#include <errno.h>
#include <stdlib.h>
#include <string.h>

#define IO_EVENT_READ        1
#define IO_EVENT_WRITE       2
#define IO_EVENT_ERROR       3
#define IO_EVENT_DISCONNECT  4

#define MAX_EVENTS 128

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
    int epfd;
    int capacity;
    int count;
    struct epoll_event *evlist;
} io_context;

static int set_nonblock(int fd) {
    int flags = fcntl(fd, F_GETFL, 0);
    if (flags == -1) return -1;
    return fcntl(fd, F_SETFL, flags | O_NONBLOCK);
}

/**
 * 初期化
 */
int io_core_init(io_context *ctx, size_t recv_buf_size)
{
    (void)recv_buf_size;

    if(!ctx) return -1;

    ctx->epfd = epoll_create1(EPOLL_CLOEXEC);
    if(ctx->epfd < 0) return -1;

    ctx->capacity = 4096;
    ctx->count = 0;
    ctx->evlist = calloc(ctx->capacity, sizeof(struct epoll_event));

    return ctx->evlist ? 0 : -1;
}

/**
 * 登録
 */
int io_register(io_context *ctx, int fd, int is_udp)
{
    (void)is_udp;

    if(!ctx) return -1;

    // 既に登録済みかチェック
    // epoll は重複登録するとエラーになるので、ここは必要
    // （本番では fd→user_data の map を持つと高速）
    struct epoll_event ev;
    memset(&ev, 0, sizeof(ev));

    set_nonblock(fd);

    ev.events = EPOLLIN;  // WSAPoll と同じく read 監視のみ
    ev.data.fd = fd;

    if(epoll_ctl(ctx->epfd, EPOLL_CTL_ADD, fd, &ev) == -1)
    {
        if(errno == EEXIST) return 0;
        return -1;
    }

    ctx->count++;
    return 0;
}

/**
 * 解除
 */
int io_unregister(io_context *ctx, int fd)
{
    if(!ctx) return -1;

    epoll_ctl(ctx->epfd, EPOLL_CTL_DEL, fd, NULL);
    if(ctx->count > 0) ctx->count--;

    return 0;
}

/**
 * イベント待機
 */
int io_select(io_context *ctx, int timeout_ms, void *events_ptr)
{
    io_event_list *events = (io_event_list *)events_ptr;
    if(!ctx || !events) return -1;

    events->count = 0;

    if(ctx->count == 0)
    {
        if(timeout_ms > 0) usleep(timeout_ms * 1000);
        return 0;
    }

    int n = epoll_wait(ctx->epfd, ctx->evlist, MAX_EVENTS, timeout_ms);
    if(n <= 0) return n;

    for(int i = 0; i < n && events->count < MAX_EVENTS; i++)
    {
        struct epoll_event *ev = &ctx->evlist[i];
        io_event *out = &events->events[events->count++];

        out->handle = ev->data.fd;
        out->bytes = 0;
        out->user_data = NULL;
        out->error_code = 0;
        out->event_type = 0;

        if(ev->events & EPOLLIN)
            out->event_type = IO_EVENT_READ;

        if(ev->events & EPOLLOUT)
            out->event_type = IO_EVENT_WRITE;

        if(ev->events & EPOLLERR)
            out->event_type = IO_EVENT_ERROR;

        if(ev->events & EPOLLHUP)
            out->event_type = IO_EVENT_DISCONNECT;
    }

    return events->count;
}

/**
 * 終了処理
 */
int io_core_close(io_context *ctx)
{
    if(!ctx) return -1;

    if(ctx->epfd >= 0) close(ctx->epfd);
    free(ctx->evlist);

    return 0;
}
