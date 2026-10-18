<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork\PureUdp;

use FFI;

use SocketManager\Library\PureUdpManager;


/**
 * Adaptive I/O Driver ファクトリクラス
 * 
 * アダプティブ I/O アーキテクチャにより最適なドライバを生成
 */
class AdaptiveIoDriverFactory
{
    // 動作モード定義
    public const MODE_IO_NATIVE = 0x01;
    public const MODE_IO_COMPATIBLE = 0x02;

    /**
     * @var ?int 動作モード
     */
    public static ?int $mode = null;

    /**
     * ドライバI/Fの取得
     * 
     * @param PureUdpManager $p_manager PureUdpManagerインスタンス
     * @param int $p_recv_buf_size 受信バッファサイズ
     * @return IIoDriver ドライバI/F
     */
    public static function create(PureUdpManager $p_manager, int $p_recv_buf_size): IIoDriver
    {
        $loaded_ffi = false;
        if(extension_loaded('ffi'))
        {
            $loaded_ffi = true;
        }
        $loaded_socketsfd = false;
        if(extension_loaded('socketsfd'))
        {
            $loaded_socketsfd = true;
        }

        if(
            ($loaded_ffi && $loaded_socketsfd)
        &&  (filter_var(ini_get('ffi.enable'), FILTER_VALIDATE_BOOLEAN) || ini_get('ffi.enable') === 'preload')
        &&  (
                (PHP_OS_FAMILY === 'Windows' && file_exists(__DIR__.'/../driver/io_core_win_pure_udp.dll'))
            ||  (PHP_OS_FAMILY === 'Linux' && file_exists(__DIR__.'/../driver/libio_core_linux_pure_udp.so'))
            )
        ){
            self::$mode = self::MODE_IO_NATIVE; // モード設定
            switch(PHP_OS_FAMILY)
            {
                case 'Windows':
                    $header_os = <<<CDEF
                        typedef struct {
                            void  *iocp;          // HANDLE → void*

                            void  *fd_map;        // fd_map_entry** → void*
                            int    fd_map_size;
                            void  *fd_list_head;  // io_fd_entry* → void*

                            size_t recv_buf_size;
                        } io_context;

                        typedef struct {
                            int       count;
                            io_event  events[128];
                        } io_event_list;
CDEF;
                    $lib = __DIR__ . '/../driver/io_core_win_pure_udp.dll';
                    break;
                case 'Linux':
                    $header_os = <<<CDEF
                        typedef struct {
                            int     epfd;
                            int     capacity;
                            int     count;
                            void   *evlist;

                            size_t  recv_buf_size;
                            void   *mmsg;
                            void   *iov;
                            void   *from_addrs;
                            char   *raw_mmsg_buffers;

                            int     pending_msg_count;
                            int     pending_msg_index;
                            int     pending_fd;
                        } io_context;

                        typedef struct {
                            int       count;
                            io_event  events[4096];
                        } io_event_list;
CDEF;
                    $lib = __DIR__ . '/../driver/libio_core_linux_pure_udp.so';
                    break;
            }
            $header = <<<CDEF
                typedef struct {
                    char           ip[16];
                    unsigned short port;
                    size_t         data_len;
                    char           data[];
                } udp_accept_t;

                typedef struct {
                    int     handle;
                    int     event_type;
                    int     error_code;
                    size_t  bytes;
                    void*   user_data;
                } io_event;

                {$header_os}
    
                // 初期化処理
                // ctx: IO ドライバのコンテキスト
                // recv_buf_size: 受信バッファサイズ
                // return: 0 = success, 非0 = error code
                int io_core_init(io_context* ctx, unsigned long long recv_buf_size);

                // ソケットハンドルを IO ドライバへ登録
                // ctx: IO ドライバのコンテキスト
                // fd: OS のソケットハンドル（Windows=SOCKET, Linux=fd）
                // return: 0 = success, 非0 = error code
                int io_register(io_context* ctx, int fd);

                // ソケットハンドルを IO ドライバから解除
                // ctx: IO ドライバのコンテキスト
                // fd: OS のソケットハンドル（Windows=SOCKET, Linux=fd）
                // return: 0 = success, 非0 = error code
                int io_unregister(io_context* ctx, int fd);

                // イベント待機
                // ctx: IO ドライバのコンテキスト
                // timeout_ms: タイムアウト（ミリ秒）
                // events: io_event_list*（C 側で count と events[] を埋める）
                // return: 発生したイベント数（0 以上）、負数 = error code
                int io_select(io_context* ctx, int timeout_ms, void* events);

                // 後始末処理
                // ctx: IO ドライバのコンテキスト
                // return: 0 = success, 非0 = error code
                int io_core_close(io_context *ctx);

                // メモリ解放
                // p: メモリ領域
                void io_free(void *p);
CDEF;
            $driver = new NativeIoDriver(FFI::cdef($header, $lib), $p_manager, $p_recv_buf_size);
            printf("\033[1;32mBoot sequence finished — running in Adaptive IO-Driver Mode.\033[0m\n");
            return $driver;
        }
        self::$mode = self::MODE_IO_COMPATIBLE; // モード設定
        return new CompatibleIoDriver($p_manager);
    }
}
