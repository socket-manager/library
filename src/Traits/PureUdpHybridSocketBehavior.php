<?php
/**
 * ライブラリファイル
 * 
 * Traitクラスのファイル
 */

namespace SocketManager\Library\Traits;

use Exception;
use SocketManager\Library\LogMessageEnum;
use SocketManager\Library\UnitKindEnum;
use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\StatusEnum;
use SocketManager\Library\PureUdp\SocketManagerParameter;
use SocketManager\Library\FrameWork\PureUdp\IIoDriver;


/**
 * ハイブリッドソケット対応（PureUdp用）
 * 
 * @property string $lang 言語設定
 * @property mixed $await_socket 待ち受けソケットリソース
 * @property array $descriptors ディスクリプタのリスト
 * @property array $sockets クライアントソケットリソースのリスト
 * @property int $os_sndbuf_sz OS送信バッファサイズ（SO_SNDBUF用）
 * @property int $os_rcvbuf_sz OS受信バッファサイズ（SO_RCVBUF用）
 * @property int $limit_connection 制限接続数
 * @property int $internal_max_packets MAX受信パケット件数
 * @property IIoDriver $iio_driver I/Oドライバ
 * @property CycleDrivenManager $cycle_driven_for_protocol 周期ドリブンマネージャー（プロトコルUNIT用）
 * @property ?SocketManagerParameter $unit_parameter UNITパラメータ
 * @property Closure(string $buf, int $len, string $host, int $port, array &$prop): ?string $key_resolve_callback キー抽出コールバック
 * @property Closure(SocketManagerParameter $param) $emergency_callback 緊急停止時のコールバック
 */
trait PureUdpHybridSocketBehavior 
{
    //--------------------------------------------------------------------------
    // 【プロトタイプ宣言】
    //--------------------------------------------------------------------------

    /**
     * ログ出力
     * 
     * SocketManagerで使用しているチャンネル名と同じになる
     * 
     * @param string $p_level ログレベル
     * @param array $p_param ログパラメータ
     */
    abstract public function logWriter(string $p_level, array $p_param);

    /**
     * リモートホストを基にbindアドレスの自動選択
     * 
     * @param string $p_remote_host リモートホスト
     * @return string bindアドレス
     */
    abstract private function selectBindHost(string $p_remote_host): string;

    /**
     * ソケットディスクリプタの生成
     * 
     * @param string $p_cid 接続ID
     * @param mixed $p_soc ソケットリソース
     * @param string $p_host リモートホスト
     * @param int $p_port リモートポート
     * @return array|false ディスクリプタ or false（失敗）
     */
    abstract private function createDescriptor(string $p_cid, $p_soc, string $p_host, int $p_port);

    /**
     * キュー名の設定（処理開始用）
     * 
     * @param UnitKindEnum $p_kind 設定対象種別
     * @param string $p_cid 接続ID
     * @param ?string $p_name キュー名 or null（なし）
     * @return bool true（成功） or false (失敗)
     */
    abstract protected function setQueueNameForStart(UnitKindEnum $p_kind, string $p_cid, ?string $p_name): bool;

    /**
     * 緊急停止
     * 
     * @param UnitKindEnum $p_kind 対象種別
     * @param string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    abstract protected function emergencyShutdown(UnitKindEnum $p_kind, string $p_cid): bool;


    //--------------------------------------------------------------------------
    // 【プロパティ】
    //--------------------------------------------------------------------------

    /**
     * クライアントソケットのリソース本体
     */
    private array $socket_resources = [];

    /**
     * クライアントソケットのリモートアドレス
     */
    private array $socket_remotes = [];


    //--------------------------------------------------------------------------
    // 【メソッド】
    //--------------------------------------------------------------------------

    /**
     * ソケット接続
     * 
     * @param string $p_host ホスト名
     * @param int $p_port ポート番号
     * @param int $p_local_port ローカルポート番号
     * @return string|bool リソースID or false（失敗）
     */
    public function connect(string $p_host, int $p_port, int $p_local_port = 15000): string|bool
    {
        $host = trim($p_host);
        if(!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {   // ドメイン名として名前解決を試みる
            $ip = gethostbyname($host);
            
            // 入力したドメイン名と返ってきた値が同じ、または形式がIPアドレスでなければ失敗
            if($ip === $host || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            {
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::NAME_RESOLUTION_FAILURE->message($this->lang)]);
                return false;
            }
        }

        if($this->await_socket !== null)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }
        else
        if($this->socket_resources === [])
        {
            // ロールの設定
            $this->iio_driver->setRoleClient($this->socket_resources);

            // キー抽出コールバックの設定
            if($this->key_resolve_callback === null)
            {
                $this->key_resolve_callback = $this->keyResolveCallback(...);
            }
        }

        // Create datagram socket
        $soc = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if($soc === false)
        {
            $this->logWriter('error', [__METHOD__ => 'socket_create', 'message' => LogMessageEnum::SOCKET_ERROR->socket()]);
            return false;
        }

        // ノンブロッキングの設定
        $w_ret = socket_set_nonblock($soc);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::NONBLOCK_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // buffer size
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_RCVBUF, $this->os_rcvbuf_sz);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // buffer size
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_SNDBUF, $this->os_sndbuf_sz);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // 宛先に応じて bind IP を決定
        $bind_host = $this->selectBindHost($host);

        // bind socket to specified host
        $w_ret = socket_bind($soc, $bind_host, $p_local_port);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        // ソケットリソースの登録
        $fd = $this->iio_driver->register($soc);
        $rid = '#'.$fd;
        $this->socket_resources[$rid] = $soc;
        $this->socket_remotes[$rid] = [
            'host' => $host,
            'port' => $p_port
        ];

        return $rid;
    }

    /**
     * セッションオープン
     * 
     * @param string $p_rid リソースID
     * @param ?string $p_sid セッションID
     * @return bool true（成功） or false（失敗）
     */
    public function openSession(string $p_rid, ?string $p_sid = null): bool
    {
        $remote = $this->socket_remotes[$p_rid] ?? null;
        if($remote === null)
        {
            return false;
        }

        if($p_sid === null)
        {
            $p_sid = "{$remote['host']}:{$remote['port']}";
        }

        if(isset($this->descriptors[$p_sid]))
        {
            return false;
        }

        // ディスクリプタの生成
        $des = $this->createDescriptor($p_sid, $this->socket_resources[$p_rid], $this->socket_remotes[$p_rid]['host'], $this->socket_remotes[$p_rid]['port']);
        if($des === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }

        // キューの設定がない場合は抜ける
        $w_ret = $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::CONNECT->value, StatusEnum::START->value);
        if($w_ret === true)
        {
            // 接続時のキュー名設定
            $w_ret = $this->setQueueNameForStart(UnitKindEnum::PROTOCOL, $des['connection_id'], ProtocolQueueEnum::CONNECT->value);
            if($w_ret === false)
            {
                $this->logWriter('error', [__METHOD__ => "[{$des['connection_id']}]".LogMessageEnum::QUEUE_START_FAIL->message($this->lang)]);
                return false;
            }
        }

        return true;
    }

    /**
     * ソケットセレクト
     * 
     * @param int $p_utimer ブロッキングタイム（マイクロ秒）
     * @return bool true（成功） or false（失敗）
     */
    protected function select($p_utimer = 0): bool
    {
        //--------------------------------------------------------------------------
        // 処理対象のソケットがない場合は抜ける
        //--------------------------------------------------------------------------

        if($this->await_socket === null)
        {
            if($this->descriptors === [])
            {
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_NO_COUNT->message($this->lang)]);
                return false;
            }
        }

        //--------------------------------------------------------------------------
        // セレクト実行
        //--------------------------------------------------------------------------

        $chgs = $this->iio_driver->waitEvents();
        if($chgs === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket()]);
            return false;
        }

        //--------------------------------------------------------------------------
        // 変更のあったディスクリプタの更新
        //--------------------------------------------------------------------------

        foreach($chgs as &$chg)
        {
            if($chg['type'] !== 'read')
            {   // IO エラー（キーが確定していないので即終了）
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::IO_ERROR->message($this->lang), 'rid' => $chg['cid']]);
                continue;
            }

            $key = null;
            $prop = [];
            $key_resolve_callback = $this->key_resolve_callback;
            $buffering_key = null;
            if($this->await_socket !== null)
            {   // 待ち受け
                try
                {
                    $key = $key_resolve_callback($chg['data'], $chg['bytes'], $chg['host'], $chg['port'], $prop);
                    if($key === null)
                    {   // 正常なパケットではない
                        // 受信データを破棄して次の処理に廻す
                        continue;
                    }
                }
                catch(Exception $e)
                {
                    $this->logWriter('error', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    return false;
                }
                if(!isset($this->descriptors[$key]))
                {
                    // 制限接続数の判定
                    if(count($this->descriptors) >= $this->limit_connection)
                    {
                        $this->logWriter('notice', [__METHOD__ => LogMessageEnum::CONNECTION_LIMIT_REACHED->message($this->lang)]);
                        continue;
                    }

                    // 新規でディスクリプタを生成
                    $this->createDescriptor($key, $chg['sock'], $chg['host'], $chg['port']);
                    // ACCEPTキューへ繋げる
                    $w_ret = $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::ACCEPT->value, StatusEnum::START->value);
                    if($w_ret === true)
                    {
                        // ACCEPT時のキュー名設定
                        $w_ret = $this->setQueueNameForStart(UnitKindEnum::PROTOCOL, $this->descriptor[$key]['connection_id'], ProtocolQueueEnum::ACCEPT->value);
                        if($w_ret === false)
                        {   // 不正だと見なして緊急切断
                            $this->logWriter('error', [__METHOD__ => LogMessageEnum::QUEUE_START_FAIL->message($this->lang), 'cid' => $key]);
                            // 緊急停止時コールバックを実行
                            $emergency_callback = $this->emergency_callback;
                            if($emergency_callback !== null)
                            {
                                $this->unit_parameter->setConnectionId($key);
                                $emergency_callback($this->unit_parameter);
                            }
                            // ここでは接続が確立していないためディスクリプタ破棄のみ
                            unset($this->descriptors[$key]);
                            continue;
                        }
                    }
                }

                // 受信データのバッファリングキーの設定
                $buffering_key = $key;
            }
            else
            {   // クライアント
                try
                {
                    $key = $key_resolve_callback($chg['data'], $chg['bytes'], $chg['host'], $chg['port'], $prop);
                    if($key === null)
                    {   // 正常なパケットではない
                        // 受信データを破棄して次の処理に廻す
                        continue;
                    }
                }
                catch(Exception $e)
                {
                    $this->logWriter('error', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    return false;
                }
                if(!isset($this->descriptors[$key]))
                {   // 不正だと見なして緊急切断
                    $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_NO_COUNT->message($this->lang), 'cid' => $key]);
                    $this->emergencyShutdown(UnitKindEnum::ENGINE, $key);
                    return false;
                }

                // 受信データのバッファリングキーの設定
                $buffering_key = $key;
            }

            // user_property のマージ
            $this->descriptors[$buffering_key]['user_property'] = array_replace_recursive(
                $this->descriptors[$buffering_key]['user_property'],
                $prop
            );

            // リモートアドレスの設定
            $this->descriptors[$buffering_key]['remote'] = [
                'host' => $chg['host'],
                'port' => $chg['port']
            ];

            /**
             * @var \SplQueue 受信バッファへ追加
             */
            $que = $this->descriptors[$buffering_key]['recv_buffers'];
            if($que->count() > $this->internal_max_packets)
            {   // 件数超過で緊急切断
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::PACKETS_LIMIT_OVER->message($this->lang), 'cid' => $this->descriptor[$buffering_key]['connection_id']]);
                $this->emergencyShutdown(UnitKindEnum::ENGINE, $buffering_key);
                continue;
            }
            $que->enqueue([
                'data' => $chg['data'],
                'size' => $chg['bytes'],
                'host' => $chg['host'],
                'port' => $chg['port']
            ]);

            // 最終アクセスタイムスタンプの設定
            $this->descriptors[$buffering_key]['last_access_timestamp'] = time();
        }

        return true;
    }

    /**
     * ソケットクローズ
     * 
     * @param string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    public function shutdown(string $p_cid): bool
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        if($this->await_socket === null)
        {
            // ソケットハンドルの解放
            $this->iio_driver->unregister($this->descriptors[$p_cid]['handle']);
        }

        // ソケットリソースのエントリからはずす
        unset($this->sockets[$p_cid]);

        // ディスクリプタのエントリからはずす
        unset($this->descriptors[$p_cid]);

        return true;
    }

}
