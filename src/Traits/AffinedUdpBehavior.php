<?php
/**
 * ライブラリファイル
 * 
 * Traitクラスのファイル
 */

namespace SocketManager\Library\Traits;

use Exception;
use SplQueue;
use SocketManager\Library\LogMessageEnum;
use SocketManager\Library\UnitKindEnum;
use SocketManager\Library\ProtocolQueueEnum;
use SocketManager\Library\StatusEnum;
use SocketManager\Library\AffinedUdp\EngineQueueEnum;
use SocketManager\Library\AffinedUdp\SocketManagerParameter;
use SocketManager\Library\FrameWork\AffinedUdp\IIoDriver;


/**
 * 接続型UDPソケット対応（AffinedUdp用）
 * 
 * @property string $lang 言語設定
 * @property string $await_host ホスト名
 * @property int $await_port ポート番号
 * @property mixed $await_socket 待ち受けソケットリソース
 * @property mixed $await_cid 待ち受けソケット接続ID
 * @property array $descriptors ディスクリプタのリスト
 * @property array $sockets クライアントソケットリソースのリスト
 * @property int $os_sndbuf_sz OS送信バッファサイズ（SO_SNDBUF用）
 * @property int $os_rcvbuf_sz OS受信バッファサイズ（SO_RCVBUF用）
 * @property int $limit_connection 制限接続数
 * @property int $internal_max_packets MAX受信パケット件数
 * @property IIoDriver $iio_driver I/Oドライバ
 * @property CycleDrivenManager $cycle_driven_for_protocol 周期ドリブンマネージャー（プロトコルUNIT用）
 * @property CycleDrivenManager $cycle_driven_for_engine 周期ドリブンマネージャー（エンジンUNIT用）
 * @property ?SocketManagerParameter $unit_parameter UNITパラメータ
 * @property Closure(string $buf, int $len, string $host, int $port, array &$prop): ?string $key_resolve_callback キー抽出コールバック
 * @property Closure(SocketManagerParameter $param) $emergency_callback 緊急停止時のコールバック
 */
trait AffinedUdpBehavior
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
     * 現在のクライアント数を取得
     * 
     * @return int クライアント数
     */
    abstract public function getClientCount(): int;

    /**
     * 接続制限数を取得
     * 
     * @return int 接続制限数
     */
    abstract public function getLimitConnection(): int;

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
     * 待ち受けソケット接続ID
     */
    private ?string $await_cid = null;


    //--------------------------------------------------------------------------
    // 【メソッド】
    //--------------------------------------------------------------------------

    /**
     * 待ち受けソケットのCIDを取得
     * 
     * @return ?string 待ち受けソケット接続ID
     */
    public function getAwaitCid(): ?string
    {
        return $this->await_cid;
    }

    /**
     * UDPソケット待ち受け
     * 
     * 引数のホスト名とポート番号の指定があれば、コンストラクタで設定された内容より優先されます
     * 
     * @param ?string $p_host ホスト名
     * @param ?int $p_port ポート番号
     * @return bool true（成功） or false（失敗）
     */
    public function listen(?string $p_host = null, ?int $p_port = null): bool
    {
        $host = null;
        if($p_host !== null)
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
        }
        else
        {
            $host = $this->await_host;
        }

        $port = $this->await_port;
        if($p_port !== null)
        {
            $port = $p_port;
        }

        if($this->await_cid !== null)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }
        else
        if($this->sockets !== [])
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }

        // Create TCP/IP sream socket
        $soc = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if($soc === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket()]);
            return false;
        }

        // ノンブロッキングの設定
        $w_ret = socket_set_nonblock($soc);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::NONBLOCK_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // reuseable port
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_REUSEADDR, 1);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // buffer size
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_RCVBUF, 256 * 1024);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // buffer size
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_SNDBUF, 16 * 1024);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // bind socket to specified host
        $w_ret = socket_bind($soc, $host, $port);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        // ソケットリソースの登録
        $fd = $this->iio_driver->registerListen($soc);
        $cid = '#'.$fd;

        $this->await_cid = $cid;

        // ディスクリプタの生成
        $des = $this->createDescriptor($cid, $soc, null, null, UnitKindEnum::PROTOCOL);
        if($des === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }

        // キューの設定がない場合は抜ける
        $w_ret = $this->cycle_driven_for_engine->isSetQueue(EngineQueueEnum::ACCEPT->value, StatusEnum::START->value);
        if($w_ret === true)
        {
            // アクセプト時のキュー名設定
            $w_ret = $this->setQueueNameForStart(UnitKindEnum::ENGINE, $cid, EngineQueueEnum::ACCEPT->value);
            if($w_ret === false)
            {
                $this->logWriter('error', [__METHOD__ => "[{$cid}]".LogMessageEnum::QUEUE_START_FAIL->message($this->lang)]);
                return false;
            }
        }

        return true;
    }

    /**
     * ソケット接続
     * 
     * @param string $p_host ホスト名
     * @param int $p_port ポート番号
     * @return bool true（成功） or false（失敗）
     */
    public function connect(string $p_host, int $p_port): bool
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

        if($this->await_cid !== null)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
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
        $w_ret = socket_bind($soc, $bind_host, 0);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        // ソケットリソースの登録
        $fd = $this->iio_driver->register($soc);
        $cid = '#'.$fd;

        // ディスクリプタの生成
        $des = $this->createDescriptor($cid, $soc, $host, $p_port, UnitKindEnum::PROTOCOL);
        if($des === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }

        // キューの設定がない場合は抜ける
        $w_ret = $this->cycle_driven_for_engine->isSetQueue(EngineQueueEnum::CONNECT->value, StatusEnum::START->value);
        if($w_ret === true)
        {
            // 接続時のキュー名設定
            $w_ret = $this->setQueueNameForStart(UnitKindEnum::ENGINE, $cid, EngineQueueEnum::CONNECT->value);
            if($w_ret === false)
            {
                $this->logWriter('error', [__METHOD__ => "[{$cid}]".LogMessageEnum::QUEUE_START_FAIL->message($this->lang)]);
                return false;
            }
        }

        return true;
    }

    /**
     * 宛先固定化の接続（サーバーサイド）
     * 
     * @param string $p_host ホスト
     * @param int $p_port ポート
     * @return string 新しいCID or false（失敗）
     */
    public function acceptAffined(string $p_host, int $p_port): string|false
    {
        // 接続制限数の判定
        $cnt = $this->getClientCount();
        if($cnt >= $this->getLimitConnection())
        {
            $this->logWriter('error', [__METHOD__ => 'limit client', 'message' => LogMessageEnum::CONNECTION_LIMIT_REACHED->message($this->lang)]);
            return false;
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

        // 宛先固定
        $w_ret = socket_connect($soc, $p_host, $p_port);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => 'socket_connect', 'message' => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        // ソケットリソースの登録
        $fd = $this->iio_driver->register($soc);
        $cid = '#'.$fd;

        // ディスクリプタの生成
        $des = $this->createDescriptor($cid, $soc, $p_host, $p_port);
        if($des === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }

        return $cid;
    }

    /**
     * 宛先固定化の接続（クライアントサイド）
     * 
     * @param string $p_cid 接続ID
     * @param string $p_host ホスト
     * @param int $p_port ポート
     * @return bool true（成功） or false（失敗）
     */
    public function connectAffined(string $p_cid, string $p_host, int $p_port): bool
    {
        $soc = $this->sockets[$p_cid] ?? null;
        if($soc === null)
        {
            return false;
        }

        $this->descriptors[$p_cid]['remote']['host'] = $p_host;
        $this->descriptors[$p_cid]['remote']['port'] = $p_port;

        // 宛先固定
        $w_ret = socket_connect($soc, $p_host, $p_port);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => 'socket_connect', 'message' => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        return true;
    }

    /**
     * データ受信サイズの設定
     * 
     * ※プロトコルUNITで使用 
     * 
     * @param string $p_cid 接続ID
     * @param int $p_size 受信サイズ
     * @return bool true（成功） or false(失敗)
     */
    public function setReceivingSize(string $p_cid, int $p_size): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // 受信バッファへ設定
        $this->descriptors[$p_cid]['receiving_buffer']['requested_size'] = $p_size;

        return true;
    }

    /**
     * データ受信
     * 
     * setReceivingSizeで設定されたサイズ分を受信するまで続ける
     * 
     * ※プロトコルUNITで使用 
     * 
     * @param string $p_cid 接続ID
     * @return mixed 受信データ or null（受信中） or false（失敗）
     */
    public function receiving(string $p_cid)
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        /** @var int setReceivingSizeで設定された値 */
        $target_size = $this->descriptors[$p_cid]['receiving_buffer']['requested_size'];
        if($target_size <= 0)
        {
            return null; 
        }

        /** @var \SplQueue 受信バッファのキュー */
        $packet_queue = $this->descriptors[$p_cid]['recv_buffers'];

        /** @var string 処理中のバッファ（参照渡しで退避） */
        $current_buffer = &$this->descriptors[$p_cid]['receiving_buffer']['buffer'];
        /** @var int 処理中のバッファオフセット（参照渡しで退避） */
        $buffer_offset = &$this->descriptors[$p_cid]['receiving_buffer']['offset'];

        // 現在のバッファ残量が、要求サイズに満たない場合はキューから補給
        while((strlen($current_buffer) - $buffer_offset) < $target_size)
        {
            // パケットキューが空なら、現時点では要求サイズに満たないので即 null を返す
            if($packet_queue->isEmpty())
            {
                return null;
            }

            // キューの先頭からパケットを1つ取り出す
            $packet = $packet_queue->dequeue();
            
            // 読みかけのバッファがすでに消化済み(offsetが末尾)なら、新しいパケットで上書き
            if($buffer_offset >= strlen($current_buffer))
            {
                $current_buffer = $packet['data'];
                $buffer_offset = 0;
            }
            else
            {
                // 前のパケットの残余がある場合は、文字列結合（パケット跨ぎのストリーム化）
                $current_buffer = substr($current_buffer, $buffer_offset) . $packet['data'];
                $buffer_offset = 0;
            }
        }

        // 要求サイズ分を確実に切り出してユーザーに返す
        $result = substr($current_buffer, $buffer_offset, $target_size);
        
        // ポインタを進める
        $buffer_offset += $target_size;

        // メモリ解放：バッファが完全に空になったら、GC摩擦を避けるため初期化
        if($buffer_offset >= strlen($current_buffer))
        {
            $current_buffer = '';
            $buffer_offset = 0;
        }

        // 次回のために要求サイズをクリア（TCPモジュールの現行挙動に合わせる）
        $this->descriptors[$p_cid]['receiving_buffer']['requested_size'] = 0;

        return $result;
    }

    /**
     * 送信データの設定
     * 
     * ※プロトコルUNITで使用
     * 
     * @param string $p_cid 接続ID
     * @param string $p_data 送信データ
     * @return bool true（成功） or false（失敗）
     */
    public function setSendingData(string $p_cid, string $p_data): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $this->descriptors[$p_cid]['sending_buffer']['buffer'] = $p_data;

        return true;
    }

    /**
     * データ送信
     * 
     * setSendingDataで設定されたデータを送信するまで続ける
     * 
     * ※プロトコルUNITで使用 
     * 
     * @param string $p_cid 接続ID
     * @return bool|null true（成功） or false（失敗） or null（送信中）
     */
    public function sending(string $p_cid): ?bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $soc = $this->sockets[$p_cid];
        $send_buffer = &$this->descriptors[$p_cid]['sending_buffer']['buffer'];
        $send_offset = &$this->descriptors[$p_cid]['sending_buffer']['offset'];
        
        $total_size = strlen($send_buffer);
        
        // 送信するデータがそもそも無い場合は送信完了とみなす
        if($total_size === 0 || $send_offset >= $total_size)
        {
            $send_buffer = '';
            $send_offset = 0;
            return true;
        }

        // 残りの未送信データを切り出し
        $data_to_send = substr($send_buffer, $send_offset);

        // 下層Cドライバ、または PHPのソケット送信をコール
        // ※経路ロックされているため socket_sendto ではなく socket_send で高速射出
        $sent_bytes = @\socket_write($soc, $data_to_send, strlen($data_to_send));

        if($sent_bytes === false)
        {
            $error_code = socket_last_error($soc);
            socket_clear_error($soc);

            // 【保留エラー判定】10035(Windows) または 11(Linux:EAGAIN/EWOULDBLOCK)
            if($error_code === \SocketManager\Library\AffinedUdpManager::SOCKET_ERROR_COULDNT_COMPLETED || $error_code === \SocketManager\Library\AffinedUdpManager::SOCKET_ERROR_READ_RETRY)
            {
                // バッファとオフセットを維持して null を返し、ユーザーランドに再試行を委ねる
                return null;
            }

            // 【致命的エラー判定】それ以外のパケット単位のエラー（ネットワーク切断、ルート喪失など）
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket()]);
            return false; 
        }

        // 部分送信に成功した場合、ポインタを進める
        $send_offset += $sent_bytes;

        // すべて送信しきったかチェック
        if($send_offset >= $total_size)
        {
            // バッファのクリーンアップ
            $send_buffer = '';
            $send_offset = 0;
            return true;
        }

        // まだデータが残っている場合は、OS側の部分送信状態なので再試行を促すため null を返す
        return null;
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

        if($this->descriptors === [])
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_NO_COUNT->message($this->lang)]);
            return false;
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
            $cid = $chg['cid'];
            if($chg['type'] !== 'read')
            {   // IO エラー
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::IO_ERROR->message($this->lang), 'cid' => $cid, 'type' => $chg['type']]);
                $this->emergencyShutdown(UnitKindEnum::ENGINE, $cid);
                continue;
            }

            // リモートアドレスの設定
            $this->descriptors[$cid]['remote'] = [
                'host' => $chg['host'],
                'port' => $chg['port']
            ];

            /**
             * @var \SplQueue 受信バッファへ追加
             */
            $que = $this->descriptors[$cid]['recv_buffers'];
            if($que->count() > $this->internal_max_packets)
            {   // 件数超過で緊急切断
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::PACKETS_LIMIT_OVER->message($this->lang), 'cid' => $cid]);
                $this->emergencyShutdown(UnitKindEnum::ENGINE, $cid);
                continue;
            }
            $que->enqueue([
                'data' => $chg['data'],
                'size' => $chg['bytes'],
                'host' => $chg['host'],
                'port' => $chg['port']
            ]);

            // 最終アクセスタイムスタンプの設定
            $this->descriptors[$cid]['last_access_timestamp'] = time();
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

        $fd = substr($p_cid, 1);
        $this->iio_driver->unregister($fd);

        // ソケットリソースの取得
        $soc = $this->sockets[$p_cid];

        // ソケットの読み込み／書き込みを停止
        @socket_shutdown($soc, 2);

        // ソケットリソースの解放
        @socket_close($soc);

        // マネージャーのエントリからはずす
        unset($this->sockets[$p_cid]);
        unset($this->descriptors[$p_cid]);

        return true;
    }

    /**
     * ソケットディスクリプタの生成
     * 
     * @param string $p_cid 接続ID
     * @param mixed $p_soc ソケットリソース
     * @param ?string $p_host リモートホスト
     * @param ?int $p_port リモートポート
     * @param ?UnitKindEnum $p_restrict_unit_kind 制限UNIT種別
     * @return array|false ディスクリプタ or false（失敗）
     */
    private function createDescriptor(string $p_cid, $p_soc, ?string $p_host = null, ?int $p_port = null, ?UnitKindEnum $p_restrict_unit_kind = null)
    {
        $desc = [

            // 接続ID
            'connection_id' => $p_cid,

            /**
             * リモートアドレス
             * $param->getRemoteAddr(?string &$p_host, ?int &$p_port)で取得可能
             */
            'remote' => [
                'host' => $p_host,
                'port' => $p_port
            ],

            /**
             * IO受信バッファ  
             * [
             *      'data' => <受信データ>,
             *      'size' => <データサイズ>,
             *      'host' => <ホストIP>,
             *      'port' => <ポート番号>,
             * ]
             */
            'recv_buffers' => new SplQueue(),

            // 部分切り出しの要求サイズ
            'requested_receiving_size' => 0,

            // 部分切り出しバッファのオフセット
            'buffer_offset' => 0,

            // 部分切り出しバッファ
            'current_buffer' => '',

            // 部分切り出し用バッファ
            'receiving_buffer' => [
                'requested_size' => 0,    // 要求サイズ
                'buffer' => '',
                'offset' => 0,
            ],

            // 分割送信用バッファ
            'sending_buffer' => [
                'buffer' => '',
                'offset' => 0
            ],

            'app' => [
                'recv_buffers' => [],
                'send_buffers' => []
            ],

            // ピックアップ受信バッファ（コマンドUNIT用）
            'recv_buffer' => null,

            // ピックアップ送信バッファ（プロトコルUNIT用）
            'send_buffer' => null,

            // 経過時間タイマー
            'elapsed_timers' => [],

            // 切断パラメータ
            'close_buffer' => [],

            // リモートからの最終アクセス時間
            'last_access_timestamp' => time(),

            // アライブチェックタイムアウト調整用
            'alive_adjust_timeout' => null,

            // 強制ディスパッチフラグ
            'forced_dispatcher' => false,

            // プロトコル部ステータス情報
            UnitKindEnum::PROTOCOL->value => [
                'queue_name' => null,   // キュー名
                'status_name' => null   // ステータス名
            ],

            // コマンド部ステータス情報
            UnitKindEnum::COMMAND->value => [
                'queue_name' => null,   // キュー名
                'status_name' => null   // ステータス名
            ],

            // エンジン部ステータス情報
            UnitKindEnum::ENGINE->value => [
                'queue_name' => null,   // キュー名
                'status_name' => null   // ステータス名
            ],

            // 制限するUNIT種別
            'restrict_unit_kind' => $p_restrict_unit_kind,

            // アプリケーション層
            'user_property' => [],
        ];

        // ソケット要素の反映
        $this->sockets[$p_cid] = $p_soc;

        // ディスクリプタをセット
        $this->descriptors[$p_cid] = $desc;

        return $desc;
    }
}
