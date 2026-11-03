<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork\AffinedUdp;

use SocketManager\Library\UnitKindEnum;
use SocketManager\Library\AffinedUdpManager;
use SocketManager\Library\AffinedUdp\EngineQueueEnum;


/**
 * Compatible I/O Driver クラス
 * 
 * 互換 I/O ドライバによって互換モードで動作します
 */
class CompatibleIoDriver implements IIoDriver
{
    private AffinedUdpManager $manager;    // AffinedUdpManagerインスタンス

    private array $sockets;             // クライアントソケットリソースの配列
    private array $await_socket = [];   // 待ち受けソケットリソースの配列
    private string $await_cid;          // 待ち受けソケットリソースのCID


    /**
     * コンストラクタ
     * 
     * @param AffinedUdpManager $p_manager AffinedUdpManagerインスタンス
     * @param array &$p_sockets クライアントソケットの配列
     */
    public function __construct(AffinedUdpManager $p_manager, array &$p_sockets)
    {
        $this->manager = $p_manager;
        $this->sockets = &$p_sockets;
    }

    /**
     * 待ち受け用ソケットハンドルを I/O ドライバへ登録依頼する
     * 
     * @param $p_sock ソケットリソース
     * @return int ソケットハンドル
     */
    public function registerListen($p_sock): int
    {
        $this->await_socket[] = $p_sock;
        $ret = spl_object_id($p_sock);
        $this->await_cid = '#'.$ret;
        return $ret;
    }

    /**
     * ソケットハンドルを I/O ドライバへ登録依頼する
     * 
     * @param $p_sock ソケットリソース
     * @return int ソケットハンドル
     */
    public function register($p_sock): int
    {
        return spl_object_id($p_sock);
    }

    /**
     * ソケットハンドルを I/O ドライバへ解除依頼する  
     * （実際は上位で制御されるためここでは何もしない）
     * 
     * @param $p_handle ソケットハンドル
     */
    public function unregister($p_handle): void
    {
        // インターフェースを合わせるためだけの実装。
        return;
    }

    /**
     * イベント待機
     * 
     * @param int $p_timeout タイムアウト時間（ms）
     * @return array|false 発生したイベントの配列 or false（失敗）
     */
    public function waitEvents(int $p_timeout = 0): array|false
    {
        $ret = [];
        $await_cid = null;
        if($this->await_socket !== [])
        {
            $r = $this->await_socket;
            $w = null;
            $e = null;
            $w_ret = @socket_select($r, $w, $e, 0, $p_timeout * 1000);
            if($w_ret === false)
            {
                return false;
            }
            if($r !== [])
            {
                $await_cid = $this->await_cid;
            }
        }

        if($await_cid !== null)
        {
            $type = 'read';
            $soc = $this->await_socket[0];
            $buf = '';
            $host = $port = null;
            $len = $this->manager->ioRecvFrom($this->await_socket[0], $buf, $host, $port);
            if($len === false)
            {
                $type = 'error';
            }
            else
            if($len === null)
            {
            }
            else
            {
                $ret[] = [
                    'cid'        => $await_cid,
                    'sock'       => $soc,
                    'type'       => $type,
                    'bytes'      => $len,
                    'error_code' => 0,
                    'data'       => $buf,
                    'host'       => $host,
                    'port'       => $port
                ];
            }
        }

        foreach($this->sockets as $cid => $soc)
        {
            if($cid === $await_cid)
            {
                continue;
            }
            $len = null;
            $type = 'read';
            $buf = '';
            $host = $port = null;

            // エンジンUNITのCONNECTキュー
            $w_ret = $this->manager->isExecutedQueue(UnitKindEnum::ENGINE, $cid, EngineQueueEnum::CONNECT->value);
            if($w_ret === true)
            {
                $len = $this->manager->ioRecvFrom($soc, $buf, $host, $port);
            }
            else
            {
                $len = $this->manager->ioRead($soc, $buf);
                $w_ret = $this->manager->getProperties($cid, ['remote']);
                if($w_ret === false)
                {
                    $len = false;
                }
                else
                {
                    $host = $w_ret['remote']['host'];
                    $port = $w_ret['remote']['port'];
                }
            }

            if($len === null)
            {
                continue;
            }
            else
            if($len === false)
            {
                $type = 'error';
            }
            else
            if($len === 0 && $w_ret === false)
            {
                $type = 'disconnect';
            }
            $ret[] = [
                'cid'        => $cid,
                'sock'       => $soc,
                'type'       => $type,
                'bytes'      => $len,
                'error_code' => 0,
                'data'       => $buf,
                'host'       => $host,
                'port'       => $port
            ];
        }

        return $ret;
    }

    /**
     * ソケットのアドレス情報取得
     * 
     * @param $p_handle ソケットハンドル
     * @param string &$p_ip_buf IPアドレス格納エリア
     * @param int &$p_port ポート番号格納エリア
     * @return bool true（成功） or false（失敗）
     */
    public function getSockName($p_handle, string &$p_ip_buf, int &$p_port): bool
    {
        return true;    // I/Fを合わせるための実装
    }
}
