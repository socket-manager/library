<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork;

use FFI;
use RuntimeException;


/**
 * Native I/O Driver クラス
 * 
 * ネイティブ I/O ドライバによってハイパフォーマンスモードで動作します
 */
class NativeIoDriver implements IIoDriver
{
    private FFI $ffi;

    /** @var FFI\CData $ctx */
    private $ctx;

    /** @var FFI\CData $events */
    private $events;

    /**
     * コンストラクタ
     * 
     * @param FFI $p_ffi FFI インスタンス
     */
    public function __construct(FFI $p_ffi)
    {
        $this->ffi    = $p_ffi;
        $this->ctx    = $this->ffi->new("io_context");
        $this->events = $this->ffi->new("io_event_list");
        $ret = $this->ffi->io_core_init(FFI::addr($this->ctx));
        if($ret !== 0)
        {
            // ここは既存のエラーハンドリング方針に合わせて例外 or ログなど
            throw new RuntimeException('io_core_init failed: '.$ret);
        }
    }

    /**
     * ソケットハンドルを I/O ドライバへ登録依頼する
     * 
     * @param $p_sock ソケットリソース
     * @return int ソケットハンドル
     */
    public function register($p_sock): int
    {
        $handle = socketfd($p_sock);
        $this->ffi->io_register(FFI::addr($this->ctx), $handle);
        return $handle;
    }

    /**
     * ソケットハンドルを I/O ドライバへ解除依頼する
     * 
     * @param $p_handle ソケットハンドル
     */
    public function unregister($p_handle): void
    {
        $this->ffi->io_unregister(FFI::addr($this->ctx), $p_handle);
    }

    /**
     * イベント待機
     * 
     * @param int $p_timeout タイムアウト時間（ms）
     * @return array|false 発生したイベントの配列 or false（失敗）
     */
    public function waitEvents(int $p_timeout = 0): array|false
    {
        $ret = $this->ffi->io_select(FFI::addr($this->ctx), $p_timeout, FFI::addr($this->events));
        if($ret < 0)
        {
            return false;
        }
        if($ret === 0)
        {
            return [];
        }

        // PHP 配列に変換
        return $this->convertEvents($this->events);
    }

    /**
     * C 側の io_event_list を PHP 配列へ変換する
     *
     * @param FFI\CData $p_events io_event_list*
     * @return array
     */
    private function convertEvents(FFI\CData $p_events): array
    {
        $ret = [];

        for($i = 0; $i < $p_events->count; $i++)
        {
            $ev = $p_events->events[$i];

            // event_type を文字列へ変換
            $type = null;
            if( $ev->event_type === 1 )
            {
                $type = 'read';
            }
            else
            if( $ev->event_type === 2 )
            {
                $type = 'write';
            }
            else
            if( $ev->event_type === 3 )
            {
                $type = 'error';
            }
            else
            if( $ev->event_type === 4 )
            {
                $type = 'disconnect';
            }
            else
            {
                $type = 'unknown';
            }

            $ret[] = [
                'cid'        => $ev->handle,
                'sock'       => null,               // Native では不要。互換性のため残す
                'type'       => $type,
                'bytes'      => (int)$ev->bytes,
                'error_code' => (int)$ev->error_code,
                'data'       => null                // 今回は未使用。将来の拡張用
            ];
        }

        return $ret;
    }
}
