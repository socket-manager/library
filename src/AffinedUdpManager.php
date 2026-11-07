<?php
/**
 * ライブラリファイル
 * 
 * モジュール管理クラスのファイル
 */

namespace SocketManager\Library;


use Socket;
use Exception;
use SplQueue;
use SocketManager\Library\FrameWork\AffinedUdp\AdaptiveIoDriverFactory;
use SocketManager\Library\FrameWork\AffinedUdp\IIoDriver;
use SocketManager\Library\AffinedUdp\Engine;
use SocketManager\Library\AffinedUdp\EngineQueueEnum;
use SocketManager\Library\AffinedUdp\SocketManagerParameter;
use SocketManager\Library\AffinedUdp\BufferingProtocol;

use SocketManager\Library\Traits\AffinedUdpBehavior;


/**
 * モジュール管理クラス（AffinedUdp用）
 * 
 * ソケットリソースの管理と周期ドリブンの制御を行う
 * 
 * 周期ドリブンマネージャーを「プロトコル」部と「コマンド」部に分類して管理する
 */
class AffinedUdpManager extends CoreSocketManager
{
    use AffinedUdpBehavior;

    //--------------------------------------------------------------------------
    // 【定数】
    //--------------------------------------------------------------------------

    /**
     * インターバル間隔
     */
    protected const INTERVAL_SPAN = 30000000;


    //--------------------------------------------------------------------------
    // 【プロパティ】
    //--------------------------------------------------------------------------

    /**
     * OS送信バッファサイズ（SO_SNDBUF用）
     */
    private int $os_sndbuf_sz = 1024;

    /**
     * OS受信バッファサイズ（SO_RCVBUF用）
     */
    private int $os_rcvbuf_sz = 1024;

    /**
     * システムコール受信バッファサイズ
     */
    private int $internal_rcvbuf_sz = 2048; // 2K（MTU + α）

    /**
     * クライアントソケットリソースのリスト
     */
    private array $sockets = [];

    /**
     * I/O ドライバ
     */
    private IIoDriver $iio_driver;

    /**
     * エンジンUNIT
     */
    private ?Engine $engine_units = null;

    /**
     * プロトコルインターフェース
     */
    private BufferingProtocol $protocol;

    /**
     * UNITパラメータ
     */
    protected ?SocketManagerParameter $unit_parameter = null;


    //--------------------------------------------------------------------------
    // 【メソッド】
    //--------------------------------------------------------------------------


    //--------------------------------------------------------------------------
    // 初期化関連
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param ?string $p_host 待ち受け用ホスト
     * @param ?int $p_port 待ち受け用ポート
     * @param ?int $p_limit 接続制限数
     * @param ?int $p_os_sndbuf_sz OS送信バッファサイズ
     * @param ?int $p_os_rcvbuf_sz OS受信バッファサイズ
     * @param ?int $p_internal_rcvbuf_sz システムコール受信サイズ
     * @param ?int $p_internal_max_packets MAX受信パケット件数
     */
    public function __construct
    (
        ?string $p_host = null,
        ?int $p_port = null,
        ?int $p_limit = null,
        ?int $p_os_sndbuf_sz = null,
        ?int $p_os_rcvbuf_sz = null,
        ?int $p_internal_rcvbuf_sz = null,
        ?int $p_internal_max_packets = null
    )
    {
        parent::__construct($p_host, $p_port, $p_limit);

        // OS送信バッファサイズの設定
        if($p_os_sndbuf_sz !== null)
        {
            $this->os_sndbuf_sz = $p_os_sndbuf_sz;
        }

        // OS受信バッファサイズの設定
        if($p_os_rcvbuf_sz !== null)
        {
            $this->os_rcvbuf_sz = $p_os_rcvbuf_sz;
        }

        // システムコール受信サイズの設定
        if($p_internal_rcvbuf_sz !== null)
        {
            $this->internal_rcvbuf_sz = $p_internal_rcvbuf_sz;
        }

        // MAX受信パケット件数の設定
        if($p_internal_max_packets !== null)
        {
            $this->internal_max_packets = $p_internal_max_packets;
        }

        //--------------------------------------------------------------------------
        // I/O ドライバの初期化
        //--------------------------------------------------------------------------

        $this->iio_driver = AdaptiveIoDriverFactory::create($this, $this->internal_rcvbuf_sz, $this->sockets);
        $this->protocol = new BufferingProtocol();

        // UNITパラメータの設定
        $this->unit_parameter = new SocketManagerParameter($this->lang);
        $this->unit_parameter->setProtocolInterface($this->protocol);
        $this->unit_parameter->setSocketManager($this);
        $this->unit_parameter->setLanguage($this->lang);

        $this->engine_units = new Engine($this->protocol);
        $this->setEngineUnits($this->engine_units);

        return;
    }

    /**
     * IInitManagerによる初期化
     * 
     * @param IInitManager $p_init IInitManagerのインスタンス
     */
    public function initManager(IInitManager $p_init)
    {
        // ログライターの登録
        $w_ret = $p_init->getLogWriter();
        if($w_ret !== null)
        {
            $this->log_writer = $w_ret;
        }

        // シリアライザーの登録
        $w_ret = $p_init->getSerializer();
        if($w_ret !== null)
        {
            $this->serializer = $w_ret;
        }

        // アンシリアライザーの登録
        $w_ret = $p_init->getUnserializer();
        if($w_ret !== null)
        {
            $this->unserializer = $w_ret;
        }

        // コマンドディスパッチャーの登録
        $w_ret = $p_init->getCommandDispatcher();
        if($w_ret !== null)
        {
            $this->command_dispatcher = $w_ret;
        }

        // 緊急停止時のコールバックの登録
        $w_ret = $p_init->getEmergencyCallback();
        if($w_ret !== null)
        {
            $this->emergency_callback = $w_ret;
        }

        // UNITパラメータの設定
        $w_ret = $p_init->getUnitParameter();
        if($w_ret !== null)
        {
            $this->unit_parameter = $w_ret;
            $this->unit_parameter->setProtocolInterface($this->protocol);
            $this->unit_parameter->setSocketManager($this);
            $this->unit_parameter->setLanguage($this->lang);
        }
    }

    /**
     * TICKキュー専用UNIT登録
     * 
     * @param array $p_entry TICKキューのUNIT群
     */
    public function setTickUnits(array $p_entry)
    {
        $this->engine_units->setTickUnits($p_entry);
        $this->setEngineUnits($this->engine_units);

        return;
    }

    /**
     * キー抽出コールバックの登録
     * 
     * @param callable(string $p_buf, int $p_len, string $p_host, int $p_port, array &$p_prop):?string $p_fnc キー抽出コールバック
     */
    public function setKeyResolveCallback(callable $p_fnc)
    {
        $this->key_resolve_callback = $p_fnc;
        return;
    }


    //--------------------------------------------------------------------------
    // getter 関連
    //--------------------------------------------------------------------------

    /**
     * 待ち受けソケットを取得
     * 
     * @return ?Socket 待ち受けソケットリソース
     */
    public function getAwaitSocket()
    {
        $soc = null;
        if($this->await_cid !== null)
        {
            $soc = $this->sockets[$this->await_cid];
        }
        return $soc;
    }


    //--------------------------------------------------------------------------
    // システムコール関連
    //--------------------------------------------------------------------------

    /**
     * データ受信（IOドライバ用）
     * 
     * 受信バッファサイズ分を受信する
     * 
     * @param $p_soc ソケットリソース
     * @param mixed &$p_buf 受信バッファ
     * @param ?string &$p_host リモート先のホスト
     * @param ?int &$p_port リモート先のポート
     * @param ?int $p_size 受信サイズ（指定があればデフォルトサイズより優先される）
     * @return int 受信したサイズ or false（失敗） or null（取得できるデータがない） or 0（切断検知）
     */
    public function ioRecvFrom($p_soc, &$p_buf, ?string &$p_host, ?int &$p_port, ?int $p_size = null): int|null|false
    {
        // 受信サイズ決定
        $size = $this->internal_rcvbuf_sz;
        if($p_size !== null)
        {
            $size = $p_size;
        }

        // データ受信
        $buf = '';
        $host = '';
        $port = 0;
        $w_ret = @socket_recvfrom($p_soc, $buf, $size, 0, $host, $port);
        if($w_ret === false)
        {
            $w_ret = LogMessageEnum::SOCKET_ERROR->array($p_soc);
            if($w_ret['code'] === self::SOCKET_ERROR_READ_RETRY)
            {
                return null;
            }

            // ソケット操作を完了できなかった
            if($w_ret['code'] === self::SOCKET_ERROR_COULDNT_COMPLETED)
            {
                return null;
            }

            // 接続中の送受信
            if($w_ret['code'] === self::SOCKET_ERROR_SENDING_WHILE_CONNECTED)
            {
                return null;
            }

            // 相手からの切断を判定
            foreach(self::SOCKET_ERROR_PEER_SHUTDOWN as $cod)
            {
                if($w_ret['code'] === $cod)
                {
                    return 0;
                }
            }
            $this->logWriter('notice', [__METHOD__ => $w_ret['message']]);
            return false;
        }

        $p_buf = $buf;
        $p_host = $host;
        $p_port = $port;
        $len = strlen($buf);
        
        return $len;
    }

    /**
     * データ受信（IOドライバ用）
     * 
     * 受信バッファサイズ分を受信する
     * 
     * ※プロトコルUNITで使用 
     * 
     * @param mixed $p_soc ソケットリソース
     * @param mixed &$p_recv 受信エリア
     * @param ?int $p_size 受信サイズ（指定があればデフォルトサイズより優先される）
     * @return int 受信したサイズ or false（失敗） or null（取得できるデータがない） or 0（切断検知）
     */
    public function ioRead($p_soc, &$p_recv, ?int $p_size = null)
    {
        // 受信サイズ決定
        $size = $this->internal_rcvbuf_sz;
        if($p_size !== null)
        {
            $size = $p_size;
        }

        // データ受信
        $p_recv = '';

        $w_ret = @socket_read($p_soc, $size);
        if($w_ret === false)
        {
            $w_ret = LogMessageEnum::SOCKET_ERROR->array($p_soc);
            if($w_ret['code'] === self::SOCKET_ERROR_READ_RETRY)
            {
                return null;
            }

            // ソケット操作を完了できなかった
            if($w_ret['code'] === self::SOCKET_ERROR_COULDNT_COMPLETED)
            {
                return null;
            }

            // 接続中の送受信
            if($w_ret['code'] === self::SOCKET_ERROR_SENDING_WHILE_CONNECTED)
            {
                return null;
            }

            // 相手からの切断を判定
            $shutdown = false;
            foreach(self::SOCKET_ERROR_PEER_SHUTDOWN as $cod)
            {
                if($w_ret['code'] === $cod)
                {
                    $shutdown = true;
                }
            }
            if($shutdown === true)
            {
                return 0;
            }
            $this->logWriter('notice', [__METHOD__ => $w_ret['message']]);
            return false;
        }
        if($w_ret === "")
        {
            return 0;
        }

        $p_recv = $w_ret;

        $len = strlen($p_recv);
        
        return $len;
    }

    //--------------------------------------------------------------------------
    // 送受信関連（IProtocolParameter用）
    //--------------------------------------------------------------------------

    /**
     * データ受信
     * 
     * @param string $p_cid    接続ID
     * @param ?string &$p_data 受信データ
     * @param ?string &$p_host 送信元ホスト
     * @param ?int &$p_port    送信元ポート
     * @return int|null|false 受信データバイト数。エラー時はfalseを返すがフレームワーク要因のエラーのみ扱う。データがない場合はnull。
     */
    public function recvfrom(string $p_cid, ?string &$p_data, ?string &$p_host, ?int &$p_port): int|null|false
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        /** @var SplQueue */
        $que = $this->descriptors[$p_cid]['recv_buffers'];

        if($que->isEmpty())
        {
            return null;
        }

        $pop = $que->dequeue();
        $p_data = $pop['data'];
        $p_host = $pop['host'];
        $p_port = $pop['port'];
        return $pop['size'];
    }

    /**
     * データ送信
     * 
     * @param ?string $p_cid  接続ID
     * @param string $p_data  送信データ
     * @param ?string $p_host 送信先ホスト（nullの場合は最後に受信したリモートホストが適用される）
     * @param ?int $p_port    送信先ポート（nullの場合は最後に受信したリモートポートが適用される）
     * @return int|null|false 送信したバイト数。エラー時はfalseを返すがフレームワーク要因のエラーのみ扱う。送信の保留が必要な場合にnullを返す。
     */
    public function sendto(?string $p_cid, string $p_data, ?string $p_host = null, ?int $p_port = null): int|null|false
    {
        if(!isset($this->sockets[$p_cid]))
        {
            return false;
        }

        $soc = $this->sockets[$p_cid];

        // 送信先を取得
        $prop = $this->descriptors[$p_cid]['remote'];
        $host = $prop['host'];
        $port = $prop['port'];
        if($p_host !== null && $p_port !== null)
        {
            $host = $p_host;
            $port = $p_port;
        }

        // データ送信
        $len = strlen($p_data);
        $w_ret = @socket_sendto($soc, $p_data, $len, 0, $host, $port);
        if($w_ret === false)
        {
            $w_ret = LogMessageEnum::SOCKET_ERROR->array($soc);
            if($w_ret['code'] === self::SOCKET_ERROR_COULDNT_COMPLETED)
            {
                return null;
            }
            $this->logWriter('error', [__METHOD__ => 'socket_sendto', "message" => $w_ret['message'], 'connection id' => $p_cid]);
            return false;
        }

        return $w_ret;
    }

    //--------------------------------------------------------------------------
    // 内部処理
    //--------------------------------------------------------------------------

    /**
     * 周期ドリブン処理の実行
     * 
     * @param int $p_cycle_interval 周期インターバルタイム（マイクロ秒）
     * @param int $p_alive_interval アライブチェックインターバルタイム（秒）
     * @return bool true（成功） or false（失敗）
     */
    public function cycleDriven(int $p_cycle_interval = 2000, int $p_alive_interval = 0): bool
    {
        // ソケットセレクト
        $w_ret = $this->select();
        if($w_ret === false)
        {
            return false;
        }

        $cids = array_keys($this->descriptors);
        if($cids === [])
        {
            // 周期インターバル
            usleep($p_cycle_interval);
            $this->prev_intervaltime = hrtime(true);
            return true;
        }
        $total = count($cids);

        // ディスクリプタでループ
        for($i = 0; $i < $total; $i++)
        {
            $cid = $cids[$i];

            // 途中で切断（要素削除）されていれば安全にスキップ
            if(!isset($this->descriptors[$cid]))
            {
                continue;
            }

            $des = $this->descriptors[$cid];

            // アライブチェックフラグ
            $alive_check = 0;

            // プロトコルUNITが処理中ではない
            $flg_exec = $this->isExecutingSequence(UnitKindEnum::PROTOCOL, $cid);
            if($flg_exec === false)
            {
                // IO 受信バッファにデータがある場合
                if(!$des['recv_buffers']->isEmpty())
                {
                    // データ受信時のキューを実行する
                    $w_ret = $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::RECV->value, StatusEnum::START->value);
                    if($w_ret === true)
                    {
                        $this->setQueueNameForStart(UnitKindEnum::PROTOCOL, $cid, ProtocolQueueEnum::RECV->value);
                    }
                }
                else
                {
                    // 一番古い送信データを取得
                    $w_ret = $this->getSendStack($cid);
                    if($w_ret === false)
                    {
                        return false;
                    }
                    $dat = $w_ret;

                    // 送信バッファにデータがあれば送信キューを設定
                    if($dat !== null)
                    {
                        // 送信データを退避
                        $this->setProperties($cid, ['send_buffer' => $dat]);

                        // キューの開始
                        $w_ret = $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::SEND->value, StatusEnum::START->value);
                        if($w_ret === true)
                        {
                            $this->setQueueNameForStart(UnitKindEnum::PROTOCOL, $cid, ProtocolQueueEnum::SEND->value);
                        }
                    }
                    else
                    {
                        // 送信データをクリア
                        $this->setProperties($cid, ['send_buffer' => null]);

                        // インターバル指定あり、かつアライブキューが存在する
                        if
                        (
                                $p_alive_interval > 0
                            &&  $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::ALIVE->value, StatusEnum::START->value) === true
                        )
                        {
                            $alive_check = 1;
                        }            
                    }
                }
            }
            else
            if($flg_exec === true)
            {
                // アライブキューの実行中を検査
                $w_ret = $this->isExecutedQueue(UnitKindEnum::PROTOCOL, $cid, ProtocolQueueEnum::ALIVE->value);
                if($w_ret === true)
                {
                    $alive_check = 2;
                }
            }

            if($cid === $this->await_cid)
            {
                $alive_check = null;
            }
            else
            {
                // 最終アクセスタイムスタンプを取得
                $w_ret = $this->getProperties($cid, ['last_access_timestamp']);
                if($w_ret === false)
                {
                    return false;
                }
                $timestamp = $w_ret['last_access_timestamp'];
            }

            // UNITパラメータへ接続IDを設定
            $this->unit_parameter->setConnectionId($cid);

            // アライブチェック
            if($alive_check === 0)  // プロトコル部実行中のタイムアウトを検査
            {
                if(($flg_exec === true || $flg_exec === null) && $p_alive_interval > 0) // プロトコル部実行制限中であってもチェックを行う
                {
                    // 実行中タイムアウト判定
                    $dif = time() - $timestamp;
                    if($dif > $p_alive_interval)
                    {
                        $this->logWriter('error', [__METHOD__ => LogMessageEnum::ALIVE_CHECK_TIMEOUT->message($this->lang), 'cid' => $cid, 'old_time' => $timestamp, 'now_time' => time()]);
                        $this->emergencyShutdown(UnitKindEnum::PROTOCOL, $cid);
                        continue;
                    }
                }
            }
            else
            if($alive_check === 2)  // アライブチェック中のタイムアウトを検査
            {
                // タイムアウト値を設定
                $timeout = $p_alive_interval;
                $w_ret = $this->getProperties($cid, ['alive_adjust_timeout']);
                if($w_ret === false)
                {
                    return false;
                }
                else
                {
                    if($w_ret !== null)
                    {
                        $timeout = $w_ret['alive_adjust_timeout'];
                    }
                }

                // 実行中タイムアウト判定
                $dif = time() - $timestamp;
                if($dif > $timeout)
                {
                    $this->logWriter('error', [__METHOD__ => LogMessageEnum::ALIVE_CHECK_TIMEOUT->message($this->lang), 'cid' => $cid, 'old_time' => $timestamp, 'now_time' => time()]);
                    $this->emergencyShutdown(UnitKindEnum::PROTOCOL, $cid);
                    continue;
                }
            }
            else
            if($alive_check === 1)  // アライブチェック開始までのタイムアウトを検査
            {
                // 一時的なタイムアウト値をクリア
                $w_ret = $this->setProperties($cid, ['alive_adjust_timeout' => null]);
                if($w_ret === false)
                {
                    return false;
                }

                // タイムアウト判定
                $dif = time() - $timestamp;
                if($dif > $p_alive_interval)
                {
                    $this->logWriter('notice', [__METHOD__ => "[{$cid}]".LogMessageEnum::ALIVE_CHECK_START_TIMEOUT->message($this->lang), 'difference' => $dif, 'interval' => $p_alive_interval]);

                    // 最終アクセスタイムスタンプの設定
                    $w_ret = $this->setProperties($cid, ['last_access_timestamp' => time()]);
                    if($w_ret === false)
                    {
                        return false;
                    }

                    // キューの開始
                    $this->setQueueNameForStart(UnitKindEnum::PROTOCOL, $cid, ProtocolQueueEnum::ALIVE->value);
                }
            }

            // プロトコルUNITの実行
            $w_ret = $this->executeUnit(UnitKindEnum::PROTOCOL, $cid);
            if($w_ret === false)
            {
                continue;
            }

            // コマンドディスパッチャーの処理
            $command_dispatcher = $this->command_dispatcher;
            if($command_dispatcher !== null)
            {
                // コマンドUNIT実行中の検査
                $flg_exec = $this->isExecutingSequence(UnitKindEnum::COMMAND, $cid);
                if($des['forced_dispatcher'] === true)
                {
                    // 一番古い受信データを取得
                    $w_ret = $this->getRecvStack($cid);
                    if($w_ret === false)
                    {
                        return false;
                    }
                    else
                    if($w_ret !== null)
                    {
                        // 受信データを退避
                        $this->setProperties($cid, ['recv_buffer' => $w_ret]);
                    }
                    $des['forced_dispatcher'] = false;
                }
                else
                if($flg_exec === false)
                {
                    // 一番古い受信データを取得
                    $w_ret = $this->getRecvStack($cid);
                    if($w_ret === false)
                    {
                        return false;
                    }
                    $dat = $w_ret;
                    if($dat !== null)
                    {
                        // 受信データを退避
                        $this->setProperties($cid, ['recv_buffer' => $dat]);

                        // UNITの実行
                        $this->unit_parameter->setUnitKind(UnitKindEnum::COMMAND);

                        // コマンドディスパッチャーのコール
                        $w_ret = false;
                        try
                        {
                            $w_ret = $command_dispatcher($this->unit_parameter, $dat);
                        }
                        catch(UnitException | Exception $e)
                        {
                            $class = get_class($e);
                            if($class === self::E_CLASS_NAME_FOR_UNIT)
                            {
                                $this->logWriter('error', $e->getArrayMessage());
                            }
                            else
                            {
                                $this->logWriter('error', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                            }

                            $this->emergencyShutdown(UnitKindEnum::ENGINE, $cid);
                            continue;
                        }
                        if($w_ret !== null)
                        {
                            $this->setQueueNameForStart(UnitKindEnum::COMMAND, $cid, $w_ret);
                        }
                    }
                    else
                    {
                        // 受信データをクリア
                        $this->setProperties($cid, ['recv_buffer' => null]);
                    }
                }
            }

            // コマンドUNITの実行
            $w_ret = $this->executeUnit(UnitKindEnum::COMMAND, $cid);
            if($w_ret === false)
            {
                continue;
            }

            // 周期インターバルを制御
            $now_intervaltime = hrtime(true);
            if(($now_intervaltime - $this->prev_intervaltime) >= self::INTERVAL_SPAN)
            {
                // 周期インターバル
                usleep($p_cycle_interval);
                $this->prev_intervaltime = $now_intervaltime;
            }

            // エンジンUNITを制御
            if($this->cycle_driven_for_engine !== null)
            {
                // エンジンUNITの処理中を検査
                $flg_exec = $this->isExecutingSequence(UnitKindEnum::ENGINE, $cid);
                if($flg_exec === true)
                {
                    // エンジンUNITの実行
                    $w_ret = $this->executeUnit(UnitKindEnum::ENGINE, $cid);
                    if($w_ret === false)
                    {
                        continue;
                    }
                }
                else
                if($flg_exec === false)
                {
                    // TICKキューの設定がある場合
                    $w_ret = $this->cycle_driven_for_engine->isSetQueue(EngineQueueEnum::TICK->value, StatusEnum::START->value);
                    if($w_ret === true)
                    {
                        // TICKキュー開始
                        $w_ret = $this->setQueueNameForStart(UnitKindEnum::ENGINE, $cid, EngineQueueEnum::TICK->value);
                        if($w_ret === false)
                        {
                            $this->logWriter('error', [__METHOD__ => LogMessageEnum::QUEUE_START_FAIL->message($this->lang)]);
                            return false;
                        }
                    }
                }
            }

            if($i > 0 && $i % 16 === 0)
            {
                // ソケットセレクト
                $w_ret = $this->select();
                if($w_ret === false)
                {
                    return false;
                }
            }
        }

        return true;
    }
}

