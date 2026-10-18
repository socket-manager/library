#define _GNU_SOURCE  /* recvmmsg の有効化に必須 */
#include <sys/epoll.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <fcntl.h>
#include <unistd.h>
#include <errno.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>

#define IO_EVENT_READ        1
#define IO_EVENT_WRITE       2
#define IO_EVENT_ERROR       3
#define IO_EVENT_DISCONNECT  4

#define MAX_EVENTS 640
// #define MAX_EVENTS 128

#define VLEN_MMSG  64
// #define VLEN_MMSG  64 

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

/* Windows/IOCP側と完全一致するPHP FFI結合用構造体 */
typedef struct {
    char           ip[16]; 
    unsigned short port;   
    size_t         data_len;
    char           data[];
} udp_accept_t;

typedef struct {
    int epfd;                         /* 4バイト */
    int capacity;                     /* 4バイト */
    int count;                        /* 4バイト */
    /* パディングにより、ここから64bit（8バイト）境界にアライメントされる */
    
    struct epoll_event *evlist;       /* 8バイト (ポインタ) */
    size_t              recv_buf_size;/* 8バイト */
    
    /* 内部に実体配列は持たせず、すべてアドレスを指すポインタに変更 */
    struct mmsghdr     *mmsg;         /* 8バイト (ポインタ) */
    struct iovec       *iov;          /* 8バイト (ポインタ) */
    struct sockaddr_in *from_addrs;   /* 8バイト (ポインタ) */
    char               *raw_mmsg_buffers; /* 8バイト (ポインタ) */

    /* 持ち越し管理メンバ */
    int                 pending_msg_count; /* 4バイト */
    int                 pending_msg_index; /* 4バイト */
    int                 pending_fd;        /* 4バイト */
    /* 構造体末尾のパディング4バイト（合計サイズ調整） */
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
    if(!ctx) return -1;

    ctx->epfd = epoll_create1(EPOLL_CLOEXEC);
    if(ctx->epfd < 0) return -1;

    ctx->capacity = 4096;
    ctx->count = 0;
    ctx->evlist = calloc(ctx->capacity, sizeof(struct epoll_event));
    if(!ctx->evlist) {
        close(ctx->epfd);
        return -1;
    }

    ctx->recv_buf_size = recv_buf_size;

    /* 各種構造体配列とバッファ領域をそれぞれ正確にmallocし、ポインタに代入 */
    ctx->mmsg = (struct mmsghdr *)calloc(VLEN_MMSG, sizeof(struct mmsghdr));
    ctx->iov  = (struct iovec *)calloc(VLEN_MMSG, sizeof(struct iovec));
    ctx->from_addrs = (struct sockaddr_in *)calloc(VLEN_MMSG, sizeof(struct sockaddr_in));
    ctx->raw_mmsg_buffers = (char *)malloc(VLEN_MMSG * recv_buf_size);

    if(!ctx->mmsg || !ctx->iov || !ctx->from_addrs || !ctx->raw_mmsg_buffers) {
        /* どこかが失敗したら安全に全解放してエラー */
        if(ctx->mmsg) free(ctx->mmsg);
        if(ctx->iov) free(ctx->iov);
        if(ctx->from_addrs) free(ctx->from_addrs);
        if(ctx->raw_mmsg_buffers) free(ctx->raw_mmsg_buffers);
        free(ctx->evlist);
        close(ctx->epfd);
        return -1;
    }

    /* 確保した領域同士を内部マッピング */
    for (int i = 0; i < VLEN_MMSG; i++) {
        ctx->iov[i].iov_base = ctx->raw_mmsg_buffers + (i * recv_buf_size);
        ctx->iov[i].iov_len  = recv_buf_size;

        ctx->mmsg[i].msg_hdr.msg_iov    = &ctx->iov[i];
        ctx->mmsg[i].msg_hdr.msg_iovlen = 1;
        ctx->mmsg[i].msg_hdr.msg_name   = &ctx->from_addrs[i];
        ctx->mmsg[i].msg_hdr.msg_namelen = sizeof(struct sockaddr_in);
    }

    /* 持ち越し管理の初期化 */
    ctx->pending_msg_count = 0;
    ctx->pending_msg_index = 0;
    ctx->pending_fd        = 0;

    return 0;
}

/**
 * 登録
 */
int io_register(io_context *ctx, int fd)
{
    if(!ctx) return -1;

    struct epoll_event ev;
    memset(&ev, 0, sizeof(ev));

    set_nonblock(fd);

    ev.events = EPOLLIN; // 純粋UDP受信監視
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
 * イベント待機（システムコール地獄の破壊層）
 */
int io_select(io_context *ctx, int timeout_ms, void *events_ptr)
{
    io_event_list *events = (io_event_list *)events_ptr;
    if(!ctx || !events) return -1;

    events->count = 0;

    // -------------------------------------------------------------------------
    // 取りこぼしたイベントデータのフラッシュ
    // -------------------------------------------------------------------------
    if (ctx->pending_msg_count > 0) {
        int m = ctx->pending_msg_index;
        int fd = ctx->pending_fd;

        while (m < ctx->pending_msg_count && events->count < MAX_EVENTS) {
            uint32_t bytes = ctx->mmsg[m].msg_len;
            if (bytes > 0) {
                udp_accept_t *pkt = (udp_accept_t *)malloc(sizeof(udp_accept_t) + bytes);
                if (pkt) {
                    struct sockaddr_in *from = &ctx->from_addrs[m];
                    inet_ntop(AF_INET, &from->sin_addr, pkt->ip, sizeof(pkt->ip));
                    pkt->port     = ntohs(from->sin_port);
                    pkt->data_len = (size_t)bytes;
                    
                    char *src_buf = ctx->raw_mmsg_buffers + (m * ctx->recv_buf_size);
                    memcpy(pkt->data, src_buf, bytes);

                    io_event *out = &events->events[events->count++];
                    out->handle     = fd;
                    out->event_type = IO_EVENT_READ;
                    out->bytes      = (size_t)bytes;
                    out->error_code = 0;
                    out->user_data  = pkt;
                }
            }
            m++;
        }

        if (m < ctx->pending_msg_count) {
            /* まだイベントリストが満杯で処理しきれなかった場合、位置を更新して今回も即座にPHPへ戻る */
            ctx->pending_msg_index = m;
            return events->count; 
        } else {
            /* すべて処理しきれたら、持ち越し状態をクリーンにクリア */
            ctx->pending_msg_count = 0;
            ctx->pending_msg_index = 0;
            ctx->pending_fd        = 0;
        }
    }

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
        int fd = ev->data.fd;

        /* エラー系イベントのハンドリング */
        if(ev->events & (EPOLLERR | EPOLLHUP)) {
            io_event *out = &events->events[events->count++];
            out->handle = fd;
            out->bytes = 0;
            out->user_data = NULL;
            out->error_code = 0;
            out->event_type = (ev->events & EPOLLHUP) ? IO_EVENT_DISCONNECT : IO_EVENT_ERROR;
            continue;
        }

        /* UDP パケット受信：recvmmsg による一括空間結合の点火 */
        if(ev->events & EPOLLIN)
        {
            /* 1回のシステムコールで、カーネル内バッファから最大 VLEN_MMSG 個を一括吸い上げ */
            int msg_count = recvmmsg(fd, ctx->mmsg, VLEN_MMSG, MSG_DONTWAIT, NULL);
            if(msg_count < 0) {
                if(errno == EAGAIN || errno == EWOULDBLOCK) continue;
                
                io_event *out = &events->events[events->count++];
                out->handle = fd;
                out->bytes = 0;
                out->user_data = NULL;
                out->error_code = errno;
                out->event_type = IO_EVENT_ERROR;
                continue;
            }

            /* 吸い上げたパケット群をudp_accept_tへマッピング */
            for(int m = 0; m < msg_count; m++) {
                uint32_t bytes = ctx->mmsg[m].msg_len;
                // @@@サイズゼロでも返却する
                // if(bytes == 0) continue; // 空パケットはスキップ

                /* イベントリストが満杯になった瞬間の記憶退避 */
                if(events->count >= MAX_EVENTS) {
                    ctx->pending_msg_count = msg_count; /* この一撃で吸い上げた全件数 */
                    ctx->pending_msg_index = m;         /* 処理できなかった最初のインデックス */
                    ctx->pending_fd        = fd;        /* ファイル記述子を記憶 */
                    break; 
                }

                udp_accept_t *pkt = (udp_accept_t *)malloc(sizeof(udp_accept_t) + bytes);
                if (!pkt) {
                    io_event *out = &events->events[events->count++];
                    out->handle = fd;
                    out->event_type = IO_EVENT_ERROR;
                    out->error_code = ENOMEM;
                    out->bytes = 0;
                    out->user_data = NULL;
                    break;
                }

                /* アドレス情報およびデータの格納 */
                struct sockaddr_in *from = &ctx->from_addrs[m];
                inet_ntop(AF_INET, &from->sin_addr, pkt->ip, sizeof(pkt->ip));
                pkt->port     = ntohs(from->sin_port);
                pkt->data_len = (size_t)bytes;
                
                /* iov_base から malloc 領域へゼロコピー転送 */
                if (bytes > 0) {
                    char *src_buf = ctx->raw_mmsg_buffers + (m * ctx->recv_buf_size);
                    memcpy(pkt->data, src_buf, bytes);
                }

                /* PHP FFIへ渡すイベントデータの確定 */
                io_event *out = &events->events[events->count++];
                out->handle     = fd;
                out->event_type = IO_EVENT_READ;
                out->bytes      = (size_t)bytes;
                out->error_code = 0;
                out->user_data  = pkt;
            }

            /* もし未処理パケットが発生してループを抜けたなら、他のfdのepollイベント処理も次回へ回す */
            if (ctx->pending_msg_count > 0) {
                break;
            }
        }
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
    if(ctx->evlist) free(ctx->evlist);
    if(ctx->mmsg) free(ctx->mmsg);
    if(ctx->iov) free(ctx->iov);
    if(ctx->from_addrs) free(ctx->from_addrs);
    if(ctx->raw_mmsg_buffers) free(ctx->raw_mmsg_buffers);

    return 0;
}

/**
 * メモリ解放 (PHP FFI側から呼ばれるポインタ解放API)
 */
void io_free(void *p)
{
    free(p);
}
