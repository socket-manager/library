<?php
/**
 * ライブラリファイル
 * 
 * シンプルソケット生成クラスのファイル
 */

namespace SocketManager\Library;

use Exception;


/**
 * シンプルソケット生成クラス
 * 
 * シンプルソケットインスタンスの生成
 */
final class SimpleSocketGenerator
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * インターバル間隔
     */
    private const INTERVAL_SPAN = 300000;


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    private ?SimpleSocketUdp $udp = null;
    private ?ISimpleSocketUdp $i_udp = null;
    private ?SimpleSocketTcpServer $tcp_server = null;
    private ?ISimpleSocketTcpServer $i_tcp_server = null;
    private ?SimpleSocketTcpClient $tcp_client = null;
    private ?ISimpleSocketTcpClient $i_tcp_client = null;

    private SimpleSocketTypeEnum $type;
    private ?string $host;
    private ?int $port;
    private ?int $downtime;
    private ?int $size;
    private ?int $limit;
    private ?int $buff_cnt;
    private ?int $retry;
    private ?int $retry_interval;

    private ?SocketManagerParameter $unit_parameter = null;
    private ?array $argv = null;
    private \Closure|string|null $log_writer = null;
    private \Closure|string|null $keep_running = null;

    /**
     * インターバル間隔計測開始時間
     * 
     */
    private float $prev_microtime = 0;

    /**
     * 言語設定
     */
    private string $lang;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param SimpleSocketTypeEnum $p_type シンプルソケットタイプ
     * @param ?string $p_host ホスト名（待受、または接続用）
     * @param ?int $p_port ポート番号（待受、または接続用）
     * @param ?int $p_downtime ダウンタイム（ms）
     * @param ?int $p_size 送受信バッファサイズ
     * @param ?int ?$p_limit 接続制限数
     * @param ?int $p_buff_cnt バッファスタック件数
     * @param ?int $p_retry リトライ回数
     * @param ?int $p_retry_interval リトライ時インターバル（μs）
     */
    public function __construct
    (
        SimpleSocketTypeEnum $p_type,
        ?string $p_host = null,
        ?int $p_port = null,
        ?int $p_downtime = null,
        ?int $p_size = null,
        ?int $p_limit = null,
        ?int $p_buff_cnt = null,
        ?int $p_retry = null,
        ?int $p_retry_interval = null
    )
    {
        $this->type = $p_type;
        $this->host = $p_host;
        $this->port = $p_port;
        $this->downtime = $p_downtime;
        $this->size = $p_size;
        $this->limit = $p_limit;
        $this->buff_cnt = $p_buff_cnt;
        $this->retry = $p_retry;
        $this->retry_interval = $p_retry_interval;

        // 言語設定
        $lang = config('app.locale', 'en');
        if($lang !== 'ja' && $lang !== 'en')
        {
            $lang = 'en';
        }
        $this->lang = $lang;

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
        if($this->type === SimpleSocketTypeEnum::UDP)
        {
            if($this->udp !== null)
            {
                $this->udp->setLogWriter($this->log_writer);
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_SERVER)
        {
            if($this->tcp_server !== null)
            {
                $this->tcp_server->setLogWriter($this->log_writer);
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_CLIENT)
        {
            if($this->tcp_client !== null)
            {
                $this->tcp_client->setLogWriter($this->log_writer);
            }
        }
    }

    /**
     * 常時実行処理の登録
     * 
     * @param \Closure|string|null $p_keep_running 常時実行処理
     *          - 第一パラメータ⇒シンプルソケットのインスタンス
     *          - 第二パラメータ以降⇒$p_argvの可変引数
     * @param mixed[] $p_argv クロージャに渡す可変引数
     */
    public function setKeepRunning(\Closure|string|null $p_keep_running, ...$p_argv)
    {
        $this->keep_running = $p_keep_running;
        $this->argv = $p_argv;
        if($this->type === SimpleSocketTypeEnum::UDP)
        {
            if($this->udp !== null)
            {
                $this->udp->setKeepRunning($this->keep_running, ...$p_argv);
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_SERVER)
        {
            if($this->tcp_server !== null)
            {
                $this->tcp_server->setKeepRunning($this->keep_running, ...$p_argv);
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_CLIENT)
        {
            if($this->tcp_client !== null)
            {
                $this->tcp_client->setKeepRunning($this->keep_running, ...$p_argv);
            }
        }
    }

    /**
     * SocketManagerとの連携
     * 
     * - UNITパラメータインスタンスの"simple_socket"プロパティにシンプルソケットインスタンスが設定されます
     * 
     * @param SocketManagerParameter $p_param UNITパラメータインスタンス
     */
    public function setUnitParameter(SocketManagerParameter $p_param)
    {
        $this->unit_parameter = $p_param;
        if($this->type === SimpleSocketTypeEnum::UDP)
        {
            if($this->udp !== null)
            {
                $p_param->simple_socket = $this->udp;
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_SERVER)
        {
            if($this->tcp_server !== null)
            {
                $p_param->simple_socket = $this->tcp_server;
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_CLIENT)
        {
            if($this->tcp_client !== null)
            {
                $p_param->simple_socket = $this->tcp_client;
            }
        }
    }

    /**
     * 生成インスタンスのインターフェースを取得
     * 
     * @return ISimpleSocketUdp|ISimpleSocketTcpServer|ISimpleSocketTcpClient|null 生成インスタンスのインターフェース or null（該当するインターフェースなし）
     */
    public function generate(): ISimpleSocketUdp|ISimpleSocketTcpServer|ISimpleSocketTcpClient|null
    {
        if($this->type === SimpleSocketTypeEnum::UDP)
        {
            try
            {
                $this->udp = new SimpleSocketUdp($this->type, $this->host, $this->port, $this->downtime, $this->size, $this->buff_cnt, $this->lang);
            }
            catch(Exception $e)
            {
                if($this->log_writer !== null)
                {
                    $log_writer = $this->log_writer;
                    $log_writer('error', ['Generate error' => $e->getMessage()]);
                }
                else
                {
                    printf("Generate error {$e->getMessage()}\n");
                }
                return null;
            }
            if($this->log_writer !== null)
            {
                $this->udp->setLogWriter($this->log_writer);
            }
            if($this->keep_running !== null)
            {
                $this->udp->setKeepRunning($this->keep_running, ...$this->argv);
            }
            if($this->unit_parameter !== null)
            {
                $this->unit_parameter->simple_socket = $this->udp;
            }
            $this->i_udp = $this->udp;
            return $this->i_udp;
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_SERVER)
        {
            try
            {
                $this->tcp_server = new SimpleSocketTcpServer($this->type, $this->host, $this->port, $this->downtime, $this->size, $this->buff_cnt, $this->lang, $this->limit);
            }
            catch(Exception $e)
            {
                if($this->log_writer !== null)
                {
                    $log_writer = $this->log_writer;
                    $log_writer('error', ['Generate error' => $e->getMessage()]);
                }
                else
                {
                    printf("Generate error {$e->getMessage()}\n");
                }
                return null;
            }
            if($this->log_writer !== null)
            {
                $this->tcp_server->setLogWriter($this->log_writer);
            }
            if($this->keep_running !== null)
            {
                $this->tcp_server->setKeepRunning($this->keep_running, ...$this->argv);
            }
            if($this->unit_parameter !== null)
            {
                $this->unit_parameter->simple_socket = $this->tcp_server;
            }
            $this->i_tcp_server = $this->tcp_server;
            return $this->i_tcp_server;
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_CLIENT)
        {
            try
            {
                $this->tcp_client = new SimpleSocketTcpClient($this->type, $this->host, $this->port, $this->downtime, $this->size, $this->buff_cnt, $this->lang, $this->limit, $this->retry, $this->retry_interval);
            }
            catch(Exception $e)
            {
                if($this->log_writer !== null)
                {
                    $log_writer = $this->log_writer;
                    $log_writer('error', ['Generate error' => $e->getMessage()]);
                }
                else
                {
                    printf("Generate error {$e->getMessage()}\n");
                }
                return null;
            }
            if($this->log_writer !== null)
            {
                $this->tcp_client->setLogWriter($this->log_writer);
            }
            if($this->keep_running !== null)
            {
                $this->tcp_client->setKeepRunning($this->keep_running, ...$this->argv);
            }
            if($this->unit_parameter !== null)
            {
                $this->unit_parameter->simple_socket = $this->tcp_client;
            }
            $this->i_tcp_client = $this->tcp_client;
            return $this->i_tcp_client;
        }

        return null;
    }

    /**
     * 周期ドリブン処理の実行
     * 
     * @param int $p_cycle_interval 周期インターバルタイム（マイクロ秒）
     * @return bool true（成功） or false（失敗）
     */
    public function cycleDriven(int $p_cycle_interval = 2000): bool
    {
        $ret = true;
        if($this->type === SimpleSocketTypeEnum::UDP)
        {
            if($this->udp !== null)
            {
                $ret = $this->udp->cycleDriven($p_cycle_interval);
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_SERVER)
        {
            if($this->tcp_server !== null)
            {
                $ret = $this->tcp_server->cycleDriven($p_cycle_interval);
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_CLIENT)
        {
            if($this->tcp_client !== null)
            {
                $ret = $this->tcp_client->cycleDriven($p_cycle_interval);
            }
        }

        $now_microtime = hrtime(true);
        if(($now_microtime - $this->prev_microtime) >= self::INTERVAL_SPAN)
        {
            // 周期インターバル
            usleep($p_cycle_interval);
            $this->prev_microtime = $now_microtime;
        }

        return $ret;
    }

    /**
     * ソケット全クローズ
     * 
     * @return bool true（成功） or false（失敗）
     */
    public function shutdownAll(): bool
    {
        $ret = true;
        if($this->type === SimpleSocketTypeEnum::UDP)
        {
            if($this->udp !== null)
            {
                $ret = $this->udp->shutdownAll();
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_SERVER)
        {
            if($this->tcp_server !== null)
            {
                $ret = $this->tcp_server->shutdownAll();
            }
        }
        else
        if($this->type === SimpleSocketTypeEnum::TCP_CLIENT)
        {
            if($this->tcp_client !== null)
            {
                $ret = $this->tcp_client->shutdownAll();
            }
        }

        return $ret;
    }
}

