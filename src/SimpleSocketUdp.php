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
 * シンプルソケットクラス（UDP通信用）
 * 
 * ソケットリソースの管理と周期ドリブンの制御を行う
 */
final class SimpleSocketUdp implements ISimpleSocketUdp
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * UDP通信データ最大サイズ
     */
    private const UDP_MAX_SIZE = 65507;


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

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
     */
    public function __construct
    (
        SimpleSocketTypeEnum $p_type,
        ?string $p_host = null,
        ?int $p_port = null,
        ?int $p_downtime = null,
        ?int $p_size = null,
        ?int $p_buff_cnt = null,
        ?string $p_lang = null
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
            $this->buffer_size = min(self::UDP_MAX_SIZE, $p_size);
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
                    $this->recv($cid);
                    break;
                }
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
        if($this->udp_flg !== true)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::FOR_UDP->message($this->lang)]);
            return false;
        }

        // Create TCP/IP sream socket
        $w_ret = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
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
        if($this->host !== null && $this->port !== null)
        {
            $w_ret = socket_bind($soc, $this->host, $this->port);
            if($w_ret === false)
            {
                $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_ERROR->socket($soc)]);
                return false;
            }
        }

        // ソケットディスクリプタの生成
        $w_ret = $this->createDescriptor($soc, true);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SOCKET_CREATE_FAIL->message($this->lang)]);
            return false;
        }

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
            array_push($this->changed_descriptors, $this->descriptors[$cid]);
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
     * @return bool true（成功） or false（失敗）
     */
    private function recv(string $p_cid): bool
    {
        static $prv_downtime = 0;

        $now_downtime = hrtime(true) / 1000000;
        if(($now_downtime - $prv_downtime) < $this->downtime)
        {
            $this->logWriter('notice', [__METHOD__ => LogMessageEnum::RECEIVING_DURING_DOWNTIME->message($this->lang)]);
            return true;
        }

        // データ受信
        $soc = $this->sockets[$p_cid];
        $buf = '';
        $addr = '';
        $port = 0;
        $w_ret = @socket_recvfrom($soc, $buf, $this->buffer_size, 0, $addr, $port);
        if($w_ret === false)
        {
            $this->logWriter('error', [__METHOD__ => 'socket_recvfrom', "message" => LogMessageEnum::SOCKET_ERROR->socket($soc), 'connection id' => $p_cid]);
            return false;
        }
        $rcv_siz = strlen($buf);
        $payload_siz = $rcv_siz - 2;

        $unpack_data = unpack('nlength', $buf);
        $rcv_payload_siz = (int)$unpack_data['length'];

        // 受信サイズが一致しない場合は抜ける
        if($rcv_payload_siz !== $payload_siz)
        {
            $this->logWriter('error', [__METHOD__ => 'socket_recvfrom', "message" => LogMessageEnum::RECEIVED_SIZE_MISMATCH->message($this->lang), 'connection id' => $p_cid]);
            return false;
        }

        // 最終アクセスタイムスタンプを設定
        $this->descriptors[$p_cid]['last_access_timestamp'] = time();

        // 受信データを設定
        $buff_cnt = count($this->descriptors[$p_cid]['receive_buffers']);
        if($buff_cnt < $this->buff_cnt)
        {
            $this->descriptors[$p_cid]['receive_buffers'][] =
            [
                'addr' => $addr,
                'port' => $port,
                'payload' => substr($buf, 2)
            ];
        }
        else
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::RECEIVE_BUFFER_FULL->message($this->lang)]);
            return false;
        }

        $prv_downtime = hrtime(true) / 1000000;

        return true;
    }

    /**
     * データ送信
     * 
     * @param string $p_cid 接続ID
     * @param string $p_host ホスト
     * @param string $p_port ポート
     * @param string $p_dat 送信データ
     * @return bool true（成功） or false（失敗）
     */
    private function send(string $p_cid, string $p_host, int $p_port, string $p_dat): bool
    {
        // ソケットリソースの取得
        $soc = $this->sockets[$p_cid];

        // ヘッダ部の作成
        $len = strlen($p_dat);
        $header = pack('n', $len);
        $send_data = $header.$p_dat;
        $send_len = strlen($send_data);

        $w_ret = @socket_sendto($soc, $send_data, $send_len, 0, $p_host, $p_port);
        if($w_ret === false)
        {
            $w_ret = LogMessageEnum::SOCKET_ERROR->array($soc);
            $this->logWriter('error', [__METHOD__ => 'socket_sendto', "message" => $w_ret['message'], 'connection id' => $p_cid]);
            return false;
        }

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
     * @param string $p_host ホスト
     * @param string $p_port ポート
     * @param string $p_dat 送信データ
     * @return ?bool true（成功） or false（失敗） or null（ダウンタイム中）
     */
    public function sendto(string $p_host, int $p_port, string $p_dat): ?bool
    {
        static $prv_downtime = 0;

        $now_downtime = hrtime(true) / 1000000;
        if(($now_downtime - $prv_downtime) < $this->downtime)
        {
            return null;
        }
        $cid = '#'.($this->next_connection_id - 1);
        $buff_cnt = count($this->descriptors[$cid]['send_buffers']);
        if($buff_cnt >= $this->buff_cnt)
        {
            $this->logWriter('error', [__METHOD__ => LogMessageEnum::SEND_BUFFER_FULL->message($this->lang)]);
            return false;
        }
        $w_ret = $this->send($cid, $p_host, $p_port, $p_dat);
        if($w_ret === true)
        {
            $prv_downtime = hrtime(true) / 1000000;
            $this->descriptors[$cid]['last_access_timestamp'] = time();
        }
        return $w_ret;
    }

    /**
     * データ受信
     * 
     * @param ?string &$p_host ホスト
     * @param ?int &$p_port ポート
     * @return ?string 受信データ or null（なし）
     */
    public function recvfrom(?string &$p_host, ?int &$p_port): ?string
    {
        $cid = '#'.($this->next_connection_id - 1);
        $dat = array_shift($this->descriptors[$cid]['receive_buffers']);
        if($dat === [] || $dat === null)
        {
            return null;
        }
        $p_host = $dat['addr'];
        $p_port = $dat['port'];
        return $dat['payload'];
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
            'size' => null,
            'data' => null,
            'receiving_size' => 0
        ];

        // 送信バッファ
        $this->descriptors[$cid]['sending_buffer'] = [
            'retry' => 0,
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

