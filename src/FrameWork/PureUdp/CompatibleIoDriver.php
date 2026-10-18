<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork\PureUdp;

use SocketManager\Library\PureUdpManager;


/**
 * Compatible I/O Driver クラス
 * 
 * 互換 I/O ドライバによって互換モードで動作します
 */
class CompatibleIoDriver implements IIoDriver
{
    private ?bool $server = null;       // サーバーロールフラグ
    private $io_recv = null;            // 受信コールバック
    private array $sockets;             // Socket リソースの配列
    private PureUdpManager $manager;    // PureUdpManagerインスタンス

    /**
     * コンストラクタ
     * 
     * @param PureUdpManager $p_manager PureUdpManagerインスタンス
     */
    public function __construct(PureUdpManager $p_manager)
    {
        $this->manager = $p_manager;
    }

    /**
     * Server Role の設定
     * 
     * @param $p_sock ソケットリソース
     */
    public function setRoleServer($p_sock): void
    {
        $this->sockets = [$p_sock];
        $this->io_recv = $this->ioRecvAwait(...);
        $this->server = true;
    }

    /**
     * Client Role の設定
     * 
     * @param array $p_socs ソケットリソースリスト
     */
    public function setRoleClient(array &$p_socs): void
    {
        $this->sockets = &$p_socs;
        $this->io_recv = $this->ioRecv(...);
        $this->server = false;
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
        if($this->server)
        {
            $r = $this->sockets;
            $w = null;
            $e = null;
            $w_ret = @socket_select($r, $w, $e, 0, $p_timeout * 1000);
            if($w_ret === false)
            {
                return false;
            }
            if($r === [])
            {
                return $ret;
            }
        }

        $io_recv = $this->io_recv;
        return $io_recv();
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

    /**
     * 受信データをイベントデータへ変換（待ち受けポートから受信）  
     * 
     * @return array|false array:イベントリスト or false:失敗
     */
    private function ioRecvAwait(): array|false
    {
        $ret = [];
        $buf = $host = $port = null;
        $len = $this->manager->ioRecv($this->sockets[0], $buf, $host, $port);
        if($len === null)
        {
            return $ret;
        }
        if($len === false)
        {
            return false;
        }

        $ret[] = [
            'cid'        => null,
            'sock'       => $this->sockets[0],
            'type'       => 'read',
            'bytes'      => $len,
            'error_code' => 0,
            'data'       => $buf,
            'host'       => $host,
            'port'       => $port
        ];

        return $ret;
    }

    /**
     * 受信データをイベントデータへ変換（クライアントポート）
     * 
     * @return array|false array:イベントリスト or false:失敗
     */
    private function ioRecv(): array|false
    {
        $ret = [];

        foreach($this->sockets as $cid => $soc)
        {
            $type = 'read';

            $buf = $host = $port = null;
            $len = $this->manager->ioRecv($soc, $buf, $host, $port);
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
            $ret[] = [
                'cid'        => $cid,
                'sock'       => $soc,
                'type'       => 'read',
                'bytes'      => $len,
                'error_code' => 0,
                'data'       => $buf,
                'host'       => $host,
                'port'       => $port
            ];
        }

        return $ret;
    }
}
