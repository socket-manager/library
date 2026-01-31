<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork;

use FFI;


/**
 * Adaptive I/O Driver ファクトリクラス
 * 
 * アダプティブ I/O アーキテクチャにより最適なドライバを生成
 */
class AdaptiveIoDriverFactory
{
    public static function create(array &$p_sockets): IIoDriver
    {
        if(
            (filter_var(ini_get('ffi.enable'), FILTER_VALIDATE_BOOLEAN) || ini_get('ffi.enable') === 'preload')
        &&  (
                (PHP_OS_FAMILY === 'Windows' && file_exists(__DIR__.'/driver/io_core_win.dll'))
            ||  (PHP_OS_FAMILY === 'Linux' && file_exists(__DIR__.'/driver/libio_core_linux.so'))
            )
        ){
            switch(PHP_OS_FAMILY)
            {
                case 'Windows':
                    $header_os = <<<CDEF
                        typedef unsigned int SOCKET;
                        typedef short SHORT;

                        typedef struct {
                            SOCKET fd;
                            SHORT  events;
                            SHORT  revents;
                        } WSAPOLLFD;

                        typedef struct {
                            WSAPOLLFD *fds;
                            int count;
                            int capacity;
                        } io_context;
CDEF;
                    $lib = __DIR__ . '/driver/io_core_win.dll';
                    break;
                case 'Linux':
                    $header_os = <<<CDEF
                        typedef struct {
                            int   epfd;
                            int   capacity;
                            int   count;
                            void *evlist;
                        } io_context;
CDEF;
                    $lib = __DIR__ . '/driver/libio_core_linux.so';
                    break;
            }
            $header = <<<CDEF
                {$header_os}
    
                typedef struct {
                    int     handle;
                    int     event_type;
                    int     error_code;
                    size_t  bytes;
                    void*   user_data;
                } io_event;

                typedef struct {
                    int       count;
                    io_event  events[128];
                } io_event_list;

                // 初期化処理
                // ctx: IO ドライバのコンテキスト
                // return: 0 = success, 非0 = error code
                int io_core_init(io_context* ctx);

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
CDEF;
            $driver = new NativeIoDriver(FFI::cdef($header, $lib));
            printf("\033[1;32mBoot sequence finished — running in High‑Performance Mode.\033[0m\n");
            return $driver;
        }
        return new CompatibleIoDriver($p_sockets);
    }
}
