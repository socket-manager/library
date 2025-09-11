<?php
/**
 * ライブラリファイル
 * 
 * シンプルソケットクラスのファイル
 */

namespace SocketManager\Library;


use Socket;
use Exception;


/**
 * シンプルソケットクラス（TCP Server 用）
 * 
 * ソケットリソースの管理と周期ドリブンの制御を行う
 */
final class SimpleSocketTcpServer implements ISimpleSocketTcpServer
{
    //--------------------------------------------------------------------------
    // 定数（ソケット関連エラーコード）
    //--------------------------------------------------------------------------

    /**
     * ソケット操作を完了できなかった
     */
    private const SOCKET_ERROR_COULDNT_COMPLETED = 10035;

    /**
     * 相手先による切断
     */
    private const SOCKET_ERROR_PEER_SHUTDOWN = [10053, 10054, 104];

    /**
     * ソケット受信のリトライが必要
     * （Resource temporarily unavailable）
     */
    private const SOCKET_ERROR_READ_RETRY = 11;


    //--------------------------------------------------------------------------
    // 定数（その他）
    //--------------------------------------------------------------------------

    /**
     * TCP通信データ最大サイズ
     */
    private const TCP_MAX_SIZE = 65495;


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * 待ち受け用ソケットの接続ID
     */
    private ?string $await_connection_id = null;

    /**
     * 【ディスクリプタのリスト】
     * 
     * 内訳は以下の通り
     *------------------------------------------------------------------
     * 接続ID（<'#' + 番号>形式）
     *
     * 'connection_id' => 接続ID（string）,	
     *
     *------------------------------------------------------------------
     * 送信バッファスタック
     * 
     * 基本的にはコマンドUNITでスタックされプロトコルUNITでスタックされたデータを送信する
     *
     * 'send_buffers' => 送信データ配列（array）,
     *
     *------------------------------------------------------------------
     * 受信バッファスタック
     * 
     * 基本的にはプロトコルUNITでスタックされコマンドUNITで抽出される
     * 
     * 'recv_buffers' => 受信データ配列（array）,
     *
     *------------------------------------------------------------------
     * 受信バッファ
     * 
     * 'receiving_buffer' => [
     * 
     *		'size' => 受信サイズ（int）,
     *
     *		'data' => 受信データ（string）,
     *
     *		'receiving_size' => 受信中のサイズ（int）
     *
     * ]
     *
     *------------------------------------------------------------------
     * 送信バッファ
     * 
     * 'sending_buffer' => [
     * 
     *		'retry' => リトライ回数（int）,
     * 
     *		'data' => 送信データ（string）,
     *
     * ]
     *
     *------------------------------------------------------------------
     * 最終アクセス日時
     * 
     * 'last_access_timestamp' => タイムスタンプ（int）,
     * 
     *------------------------------------------------------------------
     * 強制ディスパッチフラグ
     * 
     * 'forced_dispatcher' => true（ディスパッチ実施） or false（実施しない）,
     * 
     *------------------------------------------------------------------
     * ユーザープロパティ（自由定義）
     * 
     * 'user_property' => プロパティ配列（array）,
     * 
     */
    private array $descriptors = [];

    /**
     * ソケットリソースのリスト
     */
    private array $sockets = [];

    /**
     * 受信スタックエリア
     */
    private array $receive_buffers = [];

    /**
     * 前回のSELECT状態が格納される
     */
    private $changed_descriptors = [];

    /**
     * NEXT接続ID
     * 
     */
    private int $next_connection_id = 0;

    /**
     * 言語設定
     * 
     * デフォルト：'ja'
     */
    private string $lang = 'ja';

    /**
     * 制限接続数
     * 
     */
    private int $limit_connection = 10;

    /**
     * シンプルソケットタイプ
     * 
     * Enum値
     */
    private SimpleSocketTypeEnum $simple_socket_type;

    /**
     * UDPフラグ
     * 
     * true（UDP） or false（TCP）
     */
    private bool $udp_flg = false;

    /**
     * ホスト
     */
    private ?string $host = '127.0.0.1';

    /**
     * ポート
     */
    private ?int $port = 15000;

    /**
     * ダウンタイム（ms）
     */
    private int $downtime = 100;

    /**
     * バッファサイズ
     */
    private int $buffer_size = 255;

    /**
     * バッファスタック件数
     */
    private int $buff_cnt = 1;

    /**
     * 常時実行処理
     * 
     */
    private \Closure|string|null $keep_running = null;

    /**
     * 常時実行処理に渡す可変引数
     * 
     */
    private ?array $argv = null;

    /**
     * ログライター
     * 
     */
    private \Closure|string|null $log_writer = null;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param SimpleSocketTypeEnum $p_type シンプルソケットタイプ
     * @param ?string $p_host ホスト名（待ち受け、または送信用）
     * @param ?int $p_port ポート番号（待ち受け、または送信用）
     * @param ?int $p_downtime ダウンタイム（ms）
     * @param ?int $p_size バッファサイズ
     * @param ?int ?$p_buff_cnt バッファスタック件数
     * @param ?string $p_lang 言語コード
     * @param ?int $p_limit 接続制限数
     */
    public function __construct
    (
        SimpleSocketTypeEnum $p_type,
        ?string $p_host = null,
        ?int $p_port = null,
        ?int $p_downtime = null,
        ?int $p_size = null,
        ?int $p_buff_cnt = null,
        ?string $p_lang = null,
        ?int $p_limit = null
    )
    {
        // シンプルソケットタイプの設定
        $this->simple_socket_type = $p_type;

        // UDPフラグの設定
        $this->udp_flg = $p_type->isUdp();

        // ホスト名の設定
        $this->host = $p_host;

        // ポート番号の設定
        $this->port = $p_port;

        // ダウンタイムの設定
        if($p_downtime !== null)
        {
            $this->downtime = $p_downtime;
        }

        // バッファサイズの設定
        if($p_size !== null)
        {
            $this->buffer_size = min(self::TCP_MAX_SIZE, $p_size);
        }

        // バッファスタック件数
        if($p_buff_cnt !== null)
        {
            $this->buff_cnt = $p_buff_cnt;
        }

        // 言語設定
        if($p_lang !== null)
        {
            $this->lang = $p_lang;
        }

        // 接続制限数の設定
        if($p_limit !== null)
        {
            $this->limit_connection = $p_limit;
        }

        //--------------------------------------------------------------------------
        // 出力バッファの初期化
        //--------------------------------------------------------------------------

        ob_implicit_flush(true);
        while(ob_get_level() > 0)
        {
            ob_end_flush();
        }

        // ソケット作成
        $this->create();

        return;
    }

    /**
     * ログライターの登録
     * 
     * @param \Closure|string|null $p_log_writer ログライター
     */
    public function setLogWriter(\Closure|string|null $p_log_writer)
    {
        $this->log_writer = $p_log_writer;
    }

    /**
     * 常時実行処理の登録
     * 
     * @param \Closure|string|null $p_keep_running 常時実行処理
     *          第一パラメータ⇒シンプルソケットのインスタンス
     *          第二パラメータ以降⇒$p_argvの可変引数
     * @param mixed[] $p_argv 常時実行処理に渡す可変引数
     */
    public function setKeepRunning(\Closure|string|null $p_keep_running, ...$p_argv)
    {
        $this->keep_running = $p_keep_running;
        $this->argv = $p_argv;
    }

    /**
     * 周期ドリブン処理の実行
     * 
     * @param int $p_cycle_interval 周期インターバルタイム（マイクロ秒）
     * @return bool true（成功） or false（失敗）
     */
    public function cycleDriven(): bool
    {
        // ソケットセレクト
        $w_ret = $this->select();
        if($w_ret === false)
        {
            return false;
        }

        // 全ディスクリプタの取得
        $dess = $this->descriptors;

        // ディスクリプタでループ
        foreach($dess as $cid => $des)
        {
            // SELECTイベントが入ったディスクリプタでループ
            foreach($this->changed_descriptors as $chg)
            {
                // 接続IDが一致
                if($cid === $chg['connection_id'])
                {
                    $this->read($cid);
                    break;
                }
            }

            $is_sending = $this->isWriting($cid);
            if($is_sending !== true)
            {
                $cnt = count($this->descriptors[$cid]['send_buffers']);
                if($cnt > 0)
                {
                    $buf = array_shift($this->descriptors[$cid]['send_buffers']);
                    $this->write($cid, $buf['data']);
                }
            }
            else
            {
                $this->write($cid, null);
            }
        }

        $fnc = $this->keep_running;
        if($fnc !== null)
        {
            try
            {
                $fnc($this, ...$this->argv);
            }
            catch(Exception $e)
            {
                $this->logWriter('error', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return false;
            }
        }

        return true;
    }

    /**
     * プロパティの取得（ディスクリプタ内）
     * 
     * @param string $p_cid 接続ID
     * @param array $p_prop プロパティ名のリスト
     * @return array プロパティのリスト or null（空） or false（失敗）
     */
    public function getProperties(string $p_cid, array $p_prop)
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // プロパティの取得
        $ret = [];
        foreach($p_prop as $key)
        {
            if(!isset($this->descriptors[$p_cid][$key]))
            {
                return null;
            }

            $ret[$key] = $this->descriptors[$p_cid][$key];
        }

        return $ret;
    }

    /**
     * プロパティの設定（ディスクリプタ内）
     * 
     * @param string $p_cid 接続ID
     * @param array $p_prop プロパティのリスト
     * @return bool true（成功） or false（失敗）
     */
    public function setProperties(string $p_cid, array $p_prop): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // プロパティの設定
        foreach($p_prop as $key => $val)
        {
            $this->descriptors[$p_cid][$key] = $val;
        }

        return true;
    }

    /**
     * ユーザープロパティの取得（ディスクリプタ内）
     * 
     * @param string $p_cid 接続ID
     * @param array $p_prop ユーザープロパティのリスト
     * @return mixed ユーザープロパティデータ or null（空） or false（失敗）
     */
    public function getUserProperties(string $p_cid, array $p_prop)
    {
        // ソケットディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $ret = [];

        // ユーザープロパティの取得
        foreach($p_prop as $key)
        {
            if(!isset($this->descriptors[$p_cid]['user_property'][$key]))
            {
                return null;
            }

            $ret[$key] = $this->descriptors[$p_cid]['user_property'][$key];
        }

        return $ret;
    }

    /**
     * ユーザープロパティの設定（ディスクリプタ内）
     * 
     * @param string $p_cid 接続ID
     * @param array $p_prop ユーザープロパティのリスト
     * @return bool true（成功） or false（失敗）
     */
    public function setUserProperties(string $p_cid, array $p_prop): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // プロパティの設定
        foreach($p_prop as $key => $val)
        {
            $this->descriptors[$p_cid]['user_property'][$key] = $val;
        }

        return true;
    }

    /**
     * ログ出力
     * 
     * SocketWrapperで使用しているチャンネル名と同じになる
     * 
     * @param string $p_level ログレベル
     * @param array $p_param ログパラメータ
     */
    public function logWriter(string $p_level, array $p_param)
    {
        $log_writer = $this->log_writer;
        if($log_writer !== null)
        {
            $log_writer($p_level, $p_param);
        }
    }


    //--------------------------------------------------------------------------
    // システムコール関連
    //--------------------------------------------------------------------------

    /**
     * ソケット作成
     * 
     * @return bool true（成功） or false（失敗）
     */
    private function create(): bool
    {
        if($this->udp_flg === true)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::FOR_TCP->message($this->lang)]);
            return false;
        }

        // Create TCP/IP sream socket
        $w_ret = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket()]);
            return false;
        }
        $soc = $w_ret;

        // reuseable port
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_REUSEADDR, 1);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // send buffer
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_SNDBUF, $this->buffer_size);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // receive buffer
        $w_ret = socket_set_option($soc, SOL_SOCKET, SO_RCVBUF, $this->buffer_size);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_OPTION_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // bind socket to specified host
        $w_ret = socket_bind($soc, $this->host, $this->port);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        // listen to port
        $w_ret = socket_listen($soc);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
            return false;
        }

        // ソケットディスクリプタの生成
        $w_ret = $this->createDescriptor($soc, true);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }
        $des = $w_ret;

        // 待ち受けソケットの接続IDの設定
        $this->await_connection_id = $des['connection_id'];

        return true;
    }

    /**
     * ソケットセレクト
     * 
     * @param int $p_utimer ブロッキングタイム（マイクロ秒）
     * @return bool true（成功） or false（失敗）
     */
    private function select($p_utimer = 0): bool
    {
        //--------------------------------------------------------------------------
        // 処理対象のソケットがない場合は抜ける
        //--------------------------------------------------------------------------

        $cnt = count($this->sockets);
        if($cnt <= 0)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_NO_COUNT->message($this->lang)]);
            return false;
        }

        //--------------------------------------------------------------------------
        // セレクト実行
        //--------------------------------------------------------------------------

        $nul = null;
        $chgs = $this->sockets;
        $exp = null;
        $w_ret = @socket_select($chgs, $nul, $exp, 0, $p_utimer);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket()]);
            return false;
        }

        $cid = null;
        $this->changed_descriptors = array();
        foreach($chgs as $chg)
        {
            // ソケットの接続IDを取り出す
            foreach($this->sockets as $no => $soc)
            {
                if($chg == $soc)
                {
                    $cid = $no;
                }
            }
            if($cid == $this->await_connection_id)
            {
                $soc = @socket_accept($this->sockets[$this->await_connection_id]);
                if($soc === false)
                {
                    $w_soc = $this->sockets[$this->await_connection_id];
                    $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($w_soc)]);
                    return false;
                }

                // 制限接続数の判定
                $cnt = count($this->descriptors) - 1;
                if($cnt >= $this->limit_connection)
                {
                    $this->logWriter('notice', [__METHOD__ => LogMessageEnum::CONNECTION_LIMIT_REACHED->message($this->lang)]);
                    @socket_close($soc);
                    return true;
                }

                // ソケットディスクリプタの生成
                $w_ret = $this->createDescriptor($soc);
                if($w_ret === false)
                {
                    $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
                    return false;
                }
            }
            else
            {
                array_push($this->changed_descriptors, $this->descriptors[$cid]);
            }
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

        // ソケットリソースの取得
        $soc = $this->sockets[$p_cid];

        // ソケットの読み込み／書き込みを停止
        @socket_shutdown($soc, 2);

        // ソケットリソースの解放
        @socket_close($soc);

        // エントリからはずす
        unset($this->sockets[$p_cid]);
        unset($this->descriptors[$p_cid]);

        return true;
    }

    /**
     * ソケット全クローズ
     * 
     * @return bool true（成功） or false（失敗）
     */
    public function shutdownAll(): bool
    {
        foreach($this->descriptors as $cid => $des)
        {
            $w_ret = $this->shutdown($cid);
            if($w_ret === false)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * データ受信
     * 
     * @param string $p_cid 接続ID
     * @return ?bool true（成功） or false（失敗） or null（受信中）
     */
    private function read(string $p_cid): ?bool
    {
        static $prv_downtime = 0;

        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $buf = $this->descriptors[$p_cid]['receiving_buffer'];
        if($buf['len'] <= 0 && $buf['receiving_size'] <= 0)
        {
            $now_downtime = hrtime(true) / 1000000;
            if(($now_downtime - $prv_downtime) < $this->downtime)
            {
                $this->logWriter('debug', [__METHOD__ => LogMessageEnum::RECEIVING_DURING_DOWNTIME->message($this->lang)]);
                return true;
            }
        }

        // データ受信
        $soc = $this->sockets[$p_cid];
        if($buf['size'] === null)
        {
            $buf['size'] = 2;
        }
        $w_ret = @socket_read($soc, $buf['size']);
        if($w_ret === false)
        {
            $err = LogMessageEnum::SOCKET_ERROR->array($soc);

            // ソケット受信のリトライが必要
            if($err['code'] === self::SOCKET_ERROR_READ_RETRY)
            {
                return null;
            }

            // ソケット操作を完了できなかった
            if($err['code'] === self::SOCKET_ERROR_COULDNT_COMPLETED)
            {
                return null;
            }

            // 相手からの切断を判定
            foreach(self::SOCKET_ERROR_PEER_SHUTDOWN as $cod)
            {
                if($err['code'] === $cod)
                {
                    $this->logWriter('notice', [__METHOD__ => $err['message']]);
                    return false;
                }
            }
        }

        $rcv = $w_ret;
        $len = strlen($rcv);
        $buf['receiving_size'] += $len;
        if($buf['receiving_size'] >= $buf['size'])
        {
            if($buf['len'] <= 0)
            {
                $unpack_data = unpack('nlength', $rcv);
                $this->descriptors[$p_cid]['receiving_buffer']['len'] = (int)$unpack_data['length'];
                $this->descriptors[$p_cid]['receiving_buffer']['size'] = (int)$unpack_data['length'];
                $this->descriptors[$p_cid]['receiving_buffer']['data'] = '';
                $this->descriptors[$p_cid]['receiving_buffer']['receiving_size'] = 0;
            }
            else
            {
                $this->descriptors[$p_cid]['receiving_buffer']['data'] .= $rcv;
                $this->descriptors[$p_cid]['receiving_buffer']['receiving_size'] = $buf['receiving_size'];
                // 受信データを設定
                $buff_cnt = count($this->receive_buffers);
                if($buff_cnt < $this->buff_cnt)
                {
                    $this->receive_buffers[] =
                    [
                        'cid' => $p_cid,
                        'data' => $this->descriptors[$p_cid]['receiving_buffer']['data']
                    ];
                }
                else
                {
                    $this->logWriter('error', [__METHOD__ => LogMessageEnum::RECEIVE_BUFFER_FULL->message($this->lang)]);
                    $this->shutdown($p_cid);
                    return false;
                }
                $this->descriptors[$p_cid]['receiving_buffer']['len'] = 0;
                $this->descriptors[$p_cid]['receiving_buffer']['size'] = null;
                $this->descriptors[$p_cid]['receiving_buffer']['data'] = null;
                $this->descriptors[$p_cid]['receiving_buffer']['receiving_size'] = 0;
            }
        }
        else
        {
            $this->descriptors[$p_cid]['receiving_buffer']['data'] .= $rcv;
            $this->descriptors[$p_cid]['receiving_buffer']['receiving_size'] = $buf['receiving_size'];
        }

        // 最終アクセスタイムスタンプを設定
        $this->descriptors[$p_cid]['last_access_timestamp'] = time();

        $prv_downtime = hrtime(true) / 1000000;

        return true;
    }

    /**
     * 送信中確認
     * 
     * @param string $p_cid 接続ID
     * @return bool true（送信中） or false（アイドリング）
     */
    private function isWriting(string $p_cid): bool
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $buf = $this->descriptors[$p_cid]['sending_buffer'];
        if($buf['size'] !== null)
        {
            return true;
        }
        return false;
    }

    /**
     * データ送信
     * 
     * @param string $p_cid 接続ID
     * @param ?string $p_dat 送信データ or null（送信中）
     * @return ?bool true（成功） or false（失敗） or null（送信中）
     */
    private function write(string $p_cid, ?string $p_dat): ?bool
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // データ送信
        $soc = $this->sockets[$p_cid];
        $buf = $this->descriptors[$p_cid]['sending_buffer'];
        if($buf['size'] === null)
        {
            $len = strlen($p_dat);
            $header = pack('n', $len);
            $send_data = $header.$p_dat;
            $send_len = strlen($send_data);
            $buf['size'] = $send_len;
            $buf['data'] = $send_data;
        }
        $w_ret = @socket_write($soc, $buf['data'], $buf['size']);
        if($w_ret === false)
        {
            $w_ret = LogMessageEnum::SOCKET_ERROR->array($soc);
            if($w_ret['code'] === self::SOCKET_ERROR_READ_RETRY)
            {
                return null;
            }
            if($w_ret['code'] === self::SOCKET_ERROR_COULDNT_COMPLETED)
            {
                return null;
            }
            $this->logWriter('notice', [__METHOD__ => 'socket_write', "message" => $w_ret['message'], 'connection id' => $p_cid]);
            return false;
        }

        // 最終アクセスタイムスタンプを設定
        $this->descriptors[$p_cid]['last_access_timestamp'] = time();

        // 送信完了でない場合
        if($w_ret < $buf['size'])
        {
            // 送信バッファに次回送信分をセットする
            $dat = substr($buf['data'], $w_ret);
            $this->descriptors[$p_cid]['sending_buffer']['size'] = $buf['size'] - $w_ret;
            $this->descriptors[$p_cid]['sending_buffer']['data'] = $dat;
            return null;
        }

        // 送信バッファを初期化
        $this->descriptors[$p_cid]['sending_buffer']['size'] = null;
        $this->descriptors[$p_cid]['sending_buffer']['data'] = null;

        return true;
    }


    //--------------------------------------------------------------------------
    // インターフェース実装
    //--------------------------------------------------------------------------

    /**
     * シンプルソケットタイプの取得
     * 
     * @return SimpleSocketTypeEnum シンプルソケットタイプ
     */
    public function getSimpleSocketType(): SimpleSocketTypeEnum
    {
        return $this->simple_socket_type;
    }

    /**
     * データ送信
     * 
     * @param string $p_cid 接続ID
     * @param string $p_dat 送信データ
     * @return ?bool true（成功） or false（失敗） or null（ダウンタイム中）
     */
    public function send(string $p_cid, string $p_dat): ?bool
    {
        static $prv_downtime = 0;

        $now_downtime = hrtime(true) / 1000000;
        if(($now_downtime - $prv_downtime) < $this->downtime)
        {
            return null;
        }

        $buff_cnt = count($this->descriptors[$p_cid]['send_buffers']);
        if($buff_cnt >= $this->buff_cnt)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SEND_BUFFER_FULL->message($this->lang)]);
            return false;
        }

        $this->descriptors[$p_cid]['send_buffers'][] =
        [
            'data' => $p_dat
        ];

        $prv_downtime = hrtime(true) / 1000000;

        return true;
    }

    /**
     * データ受信
     * 
     * @param ?string &$p_cid 接続ID
     * @return ?string 受信データ or null（なし）
     */
    public function recv(?string &$p_cid): ?string
    {
        $dat = array_shift($this->receive_buffers);
        if($dat === [] || $dat === null)
        {
            return null;
        }

        $p_cid = $dat['cid'];
        return $dat['data'];
    }


    //--------------------------------------------------------------------------
    // 内部処理
    //--------------------------------------------------------------------------

    /**
     * ソケットディスクリプタの生成
     * 
     * @param Socket $p_socket ソケットリソース
     * @return array|bool ディスクリプタ or false（失敗）
     */
    private function createDescriptor(Socket $p_socket)
    {
        // ソケットの接続IDを生成
        $cid = '#'.$this->next_connection_id;

        // ソケット要素の反映
        $this->sockets[$cid] = $p_socket;

        $this->descriptors[$cid] = [];

        // 接続ID
        $this->descriptors[$cid]['connection_id'] = $cid;

        // 送信バッファスタック
        $this->descriptors[$cid]['send_buffers'] = [];

        // 受信バッファスタック
        $this->descriptors[$cid]['receive_buffers'] = [];

        // 受信バッファ
        $this->descriptors[$cid]['receiving_buffer'] = [
            'len' => 0,
            'size' => null,
            'data' => null,
            'receiving_size' => 0
        ];

        // 送信バッファ
        $this->descriptors[$cid]['sending_buffer'] = [
            'retry' => 0,
            'size' => null,
            'data' => null,
        ];

        // 切断情報バッファ
        $this->descriptors[$cid]['close_buffer'] = null;

        // 最終アクセス日時
        $this->descriptors[$cid]['last_access_timestamp'] = time();

        // アライブチェックタイムアウト調整用
        $this->descriptors[$cid]['alive_adjust_timeout'] = null;

        // ユーザープロパティ（自由定義）
        $this->descriptors[$cid]['user_property'] = [];

        // ノンブロッキングの設定
        $w_ret = socket_set_nonblock($p_socket);
        if($w_ret === false) {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::NONBLOCK_SETTING_FAIL->message($this->lang)]);
            return false;
        }

        // NEXT接続IDのカウントアップ
        $this->next_connection_id++;

        return $this->descriptors[$cid];
    }

}

