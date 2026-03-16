<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork;

use SocketManager\Library\SocketManager;


/**
 * Compatible I/O Driver クラス
 * 
 * 互換 I/O ドライバによって互換モードで動作します
 */
class CompatibleIoDriver implements IIoDriver
{
    private ?string $await_connection_id = null;

    private array $sockets;         // Socket リソースの配列（実体は上位のクラスに存在）

    private SocketManager $manager; // SocketManagerインスタンス

    /**
     * コンストラクタ
     * 
     * @param array &$p_sockets ソケットリソースの参照渡し
     * @param SocketManager $p_manager SocketManagerインスタンス
     */
    public function __construct(array &$p_sockets, SocketManager $p_manager)
    {
        $this->sockets = &$p_sockets;   // ラベルを渡してポインタ的に使う
        $this->manager = $p_manager;
    }

    /**
     * ソケットハンドルを I/O ドライバへ登録依頼する  
     * 
     * @param $p_sock ソケットリソース
     * @param bool $p_is_udp UDPフラグ
     * @param bool $p_is_client クライアントフラグ
     * @return int ソケットハンドル
     */
    public function register($p_sock, bool $p_is_udp, bool $p_is_client): int
    {
        // インターフェースを合わせるためだけの実装。ここでは新しいソケットハンドルIDのみ返却。
        return spl_object_id($p_sock);
    }

    /**
     * ソケットハンドルを I/O ドライバへ登録依頼する（Listen用）  
     * 
     * @param $p_sock ソケットリソース
     * @return int ソケットハンドル
     */
    public function registerListen($p_sock): int
    {
        // インターフェースを合わせるためだけの実装。ここでは新しいソケットハンドルIDのみ返却。
        $id = spl_object_id($p_sock);
        $this->await_connection_id = '#'.$id;
        return $id;
    }

    /**
     * ソケットハンドルを I/O ドライバへ登録依頼する（UDP待ち受け用）  
     * 
     * @param $p_sock ソケットリソース
     * @return int ソケットハンドル
     */
    public function registerUdpListen($p_sock): int
    {
        // インターフェースを合わせるためだけの実装。ここでは新しいソケットハンドルIDのみ返却。
        $id = spl_object_id($p_sock);
        $this->await_connection_id = '#'.$id;
        return $id;
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
        $r = [];
        if($this->await_connection_id !== null)
        {
            $r[] = $this->sockets[$this->await_connection_id];
            $w = null;
            $e = null;
            $w_ret = @socket_select($r, $w, $e, 0, $p_timeout * 1000);
            if($w_ret === false)
            {
                return false;
            }
        }
        $ret = [];
        $r_cnt = count($r);
        if($r_cnt > 0)
        {
            $ret[] = [
                'cid'        => $this->await_connection_id,
                'sock'       => $this->sockets[$this->await_connection_id],
                'type'       => 'read',
                'bytes'      => 0,
                'error_code' => 0,
                'data'       => ''
            ];
        }
        else
        {
            $sockets = $this->sockets;
            unset($sockets[$this->await_connection_id]);
            foreach($sockets as $cid => $soc)
            {
                $type = 'read';
                $data = '';
                $bytes = 0;
                $error = 0;
                $len = $this->manager->ioRecv($cid, $data);
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
                if($len === 0)
                {
                    $type = 'disconnect';
                }
                else
                {
                    $bytes = $len;
                }
                $ret[] = [
                    'cid'        => $cid,
                    'sock'       => $soc,
                    'type'       => $type,
                    'bytes'      => $bytes,
                    'error_code' => $error,
                    'data'       => $data
                ];
            }
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
        return true;
    }
}
