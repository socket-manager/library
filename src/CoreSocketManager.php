<?php
/**
 * ライブラリファイル
 * 
 * ソケットマネージャー基底クラスのファイル
 */

namespace SocketManager\Library;


use Socket;
use Exception;


/**
 * ソケットマネージャー基底クラス
 * 
 * ソケットリソースの管理と周期ドリブンの制御を行う
 * 
 * 周期ドリブンマネージャーを「プロトコル」部と「コマンド」部に分類して管理する
 */
abstract class CoreSocketManager
{
    //--------------------------------------------------------------------------
    // 【定数（ソケット関連エラーコード）】
    //--------------------------------------------------------------------------

    /**
     * ソケット操作を完了できなかった
     */
    protected const SOCKET_ERROR_COULDNT_COMPLETED = 10035;

    /**
     * 相手先による切断
     */
    protected const SOCKET_ERROR_PEER_SHUTDOWN = [10053, 10054, 104];

    /**
     * ソケット受信のリトライが必要
     * （Resource temporarily unavailable）
     */
    protected const SOCKET_ERROR_READ_RETRY = 11;

    /**
     * ソケット接続処理の継続
     * （Operation now in progress）
     */
    protected const SOCKET_ERROR_CONNECT_PROGRESS = 115;

    /**
     * ソケット接続中の送信
     */
    protected const SOCKET_ERROR_SENDING_WHILE_CONNECTED = 10057;


    //--------------------------------------------------------------------------
    // 【定数（その他）】
    //--------------------------------------------------------------------------

    /**
     * EXCEPTIONクラス名（UNIT処理用）
     */
    protected const E_CLASS_NAME_FOR_UNIT = 'SocketManager\Library\UnitException';

    /**
     * インターバル間隔
     */
    protected const INTERVAL_SPAN = 30000000;


    //--------------------------------------------------------------------------
    // 【プロパティ】
    //--------------------------------------------------------------------------

    /**
     * 待ち受け用ホスト
     */
    protected string $await_host = '127.0.0.1';

    /**
     * 待ち受け用ポート
     */
    protected int $await_port = 10000;

    /**
     * ディスクリプタのリスト
     */
    protected array $descriptors = [];

    /**
     * 周期ドリブンマネージャー（プロトコルUNIT用）
     */
    protected CycleDrivenManager $cycle_driven_for_protocol;

    /**
     * 周期ドリブンマネージャー（コマンドUNIT用）
     */
    protected CycleDrivenManager $cycle_driven_for_command;

    /**
     * 周期ドリブンマネージャー（エンジンUNIT用）
     */
    protected ?CycleDrivenManager $cycle_driven_for_engine = null;

    /**
     * ログライター
     * 
     */
    protected $log_writer = null;

    /**
     * シリアライザー
     * 
     * ペイロード部のシリアライズ処理
     */
    protected $serializer = null;

    /**
     * アンシリアライザー
     * 
     * ペイロード部のアンシリアライズ処理
     */
    protected $unserializer = null;

    /**
     * コマンドディスパッチャー
     * 
     * 受信バッファスタックにデータがある場合実行される
     */
    protected $command_dispatcher = null;

    /**
     * 緊急停止時のコールバック
     * 
     * 例外等の緊急切断時に実行される
     */
    protected $emergency_callback = null;

    /**
     * 言語設定
     * 
     * デフォルト：'ja'
     */
    protected string $lang = 'ja';

    /**
     * 制限接続数
     * 
     */
    protected int $limit_connection = 10;

    /**
     * インターバル間隔計測開始時間
     * 
     */
    protected float $prev_intervaltime = 0;

    /**
     * MAX受信パケット件数
     */
    protected int $internal_max_packets = 64;

    /**
     * UNITパラメータ
     */
    // protected ?UnitParameter $unit_parameter = null; // @@@


    //--------------------------------------------------------------------------
    // 【メソッド】
    //--------------------------------------------------------------------------


    //--------------------------------------------------------------------------
    // 初期化関連
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param ?string $p_host 待ち受け用ホスト名
     * @param ?int $p_port 待ち受け用ポート番号
     * @param ?int $p_limit 接続制限数
     */
    public function __construct
    (
        ?string $p_host = null,
        ?int $p_port = null,
        ?int $p_limit = null
    )
    {
        // ホスト名の設定
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
                    $host = null;
                }
            }
            $this->await_host = $host;
        }

        // ポート番号の設定
        if($p_port !== null)
        {
            $this->await_port = $p_port;
        }

        // 接続制限数の設定
        if($p_limit !== null)
        {
            $this->limit_connection = $p_limit;
        }

        // 言語設定
        $w_ret = config('app.locale', 'en');
        if($w_ret !== 'ja' && $w_ret !== 'en')
        {
            $w_ret = 'en';
        }
        $this->lang = $w_ret;

        // 周期ドリブンマネージャーの設定
        $this->cycle_driven_for_protocol = new CycleDrivenManager();
        $this->cycle_driven_for_command = new CycleDrivenManager();
        $this->cycle_driven_for_engine = null;

        //--------------------------------------------------------------------------
        // 出力バッファの初期化
        //--------------------------------------------------------------------------

        ob_implicit_flush(true);
        while(ob_get_level() > 0)
        {
            ob_end_flush();
        }

        return;
    }

    /**
     * IInitManagerによる初期化
     * 
     * @param IInitManager $p_init IInitManagerのインスタンス
     */
    abstract public function initManager(IInitManager $p_init);

    /**
     * IEntryUnitsによるUNIT登録（プロトコル用）
     * 
     * @param IEntryUnits $p_entry IEntryUnitsのインスタンス
     */
    public function setProtocolUnits(IEntryUnits $p_entry)
    {
        // キューリストの取得
        $ques = $p_entry->getQueueList();

        foreach($ques as $que)
        {
            // UNITリストの取得
            $units = $p_entry->getUnitList($que);
            foreach($units as $unit)
            {
                $this->cycle_driven_for_protocol->addStatusUnit($que, $unit['status'], $unit['unit']);
            }
        }
    }

    /**
     * IEntryUnitsによるUNIT登録（コマンド用）
     * 
     * @param IEntryUnits $p_entry IEntryUnitsのインスタンス
     */
    public function setCommandUnits(IEntryUnits $p_entry)
    {
        // キューリストの取得
        $ques = $p_entry->getQueueList();
        foreach($ques as $que)
        {
            // UNITリストの取得
            $units = $p_entry->getUnitList($que);
            foreach($units as $unit)
            {
                $this->cycle_driven_for_command->addStatusUnit($que, $unit['status'], $unit['unit']);
            }
        }
    }

    /**
     * IEntryUnitsによるUNIT登録（エンジン用）
     * 
     * @param IEntryUnits $p_entry IEntryUnitsのインスタンス
     */
    public function setEngineUnits(IEntryUnits $p_entry)
    {
        $this->cycle_driven_for_engine = new CycleDrivenManager();

        // キューリストの取得
        $ques = $p_entry->getQueueList();
        foreach($ques as $que)
        {
            // UNITリストの取得
            $units = $p_entry->getUnitList($que);
            foreach($units as $unit)
            {
                $this->cycle_driven_for_engine->addStatusUnit($que, $unit['status'], $unit['unit']);
            }
        }
    }


    //--------------------------------------------------------------------------
    // getter 関連
    //--------------------------------------------------------------------------

    /**
     * 待ち受けホスト名を取得
     * 
     * @return ?string 待ち受けホスト名
     */
    public function getAwaitHost(): ?string
    {
        return $this->await_host;
    }

    /**
     * 待ち受けポート番号を取得
     * 
     * @return ?int 待ち受けポート番号
     */
    public function getAwaitPort(): ?int
    {
        return $this->await_port;
    }

    /**
     * シリアライザーの取得
     * 
     * 送信データ（ペイロード部）のシリアライズ処理
     * 
     * @return mixed 関数（あるいは関数名） or null（空）
     */
    private function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * アンシリアライザーの取得
     * 
     * 受信データ（ペイロード部）のアンシリアライズ処理
     * 
     * @return mixed 関数（あるいは関数名） or null（空）
     */
    public function getUnserializer()
    {
        return $this->unserializer;
    }


    //--------------------------------------------------------------------------
    // ディスクリプタ操作関連
    //--------------------------------------------------------------------------

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
     * 全接続IDを取得
     * 
     * @param ?string $p_cid 除外する接続ID
     * @return array 接続IDのリスト
     */
    public function getConnectionIdAll(?string $p_cid = null): array
    {
        $dess = $this->descriptors;

        // 指定された接続IDを除く
        if($p_cid !== null)
        {
            unset($dess[$p_cid]);
        }

        return array_keys($dess);
    }

    /**
     * ソケットディスクリプタの存在を検査
     * 
     * @param string $p_cid 接続ID
     * @return bool true（存在する） or false（存在しない）
     */
    public function isExistDescriptor(string $p_cid): bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        return true;
    }


    //--------------------------------------------------------------------------
    // ステートマシン操作関連
    //--------------------------------------------------------------------------

    /**
     * キュー名の取得
     * 
     * @param UnitKindEnum $p_kind 取得対象種別
     * @param string $p_cid 接続ID
     * @return ?string キュー名 or null（なし）
     */
    public function getQueueName(UnitKindEnum $p_kind, string $p_cid): ?string
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return null;
        }

        return $this->descriptors[$p_cid][$p_kind->value]['queue_name'];
    }

    /**
     * ステータス名の取得
     * 
     * @param UnitKindEnum $p_kind 取得対象種別
     * @param string $p_cid 接続ID
     * @return ?string ステータス名 or null（なし）
     */
    public function getStatusName(UnitKindEnum $p_kind, string $p_cid): ?string
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return null;
        }

        return $this->descriptors[$p_cid][$p_kind->value]['status_name'];
    }

    /**
     * ステータス名の設定
     * 
     * @param UnitKindEnum $p_kind 設定対象種別
     * @param string $p_cid 接続ID
     * @param ?string $p_name ステータス名 or null（なし）
     * @return bool true（成功） or false（失敗）
     */
    public function setStatusName(UnitKindEnum $p_kind, string $p_cid, ?string $p_name): bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $this->descriptors[$p_cid][$p_kind->value]['status_name'] = $p_name;
        return true;
    }

    /**
     * キュー名の設定（処理開始用）
     * 
     * @param UnitKindEnum $p_kind 設定対象種別
     * @param string $p_cid 接続ID
     * @param ?string $p_name キュー名 or null（なし）
     * @return bool true（成功） or false (失敗)
     */
    protected function setQueueNameForStart(UnitKindEnum $p_kind, string $p_cid, ?string $p_name): bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $kind = $p_kind->value;

        // キュー名の設定
        $this->descriptors[$p_cid][$kind]['queue_name'] = $p_name;

        // ステータス名の設定
        if($p_name === null)
        {
            $this->descriptors[$p_cid][$kind]['status_name'] = null;
        }
        else
        {
            $this->descriptors[$p_cid][$kind]['status_name'] = StatusEnum::START->value;
        }

        return true;
    }

    /**
     * キューとステータスの設定
     * 
     * @param UnitKindEnum $p_kind 設定対象種別
     * @param string $p_cid 接続ID
     * @param ?string $p_que キュー名
     * @param ?string $p_sta ステータス名
     * @return bool true（成功） or false (失敗)
     */
    public function setQueueAndStatus(UnitKindEnum $p_kind, string $p_cid, ?string $p_que, ?string $p_sta): bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $kind = $p_kind->value;

        // キュー名の設定
        $this->descriptors[$p_cid][$kind]['queue_name'] = $p_que;

        // ステータス名の設定
        if($p_que === null)
        {
            $this->descriptors[$p_cid][$kind]['status_name'] = null;
        }
        else
        {
            $this->descriptors[$p_cid][$kind]['status_name'] = $p_sta;
        }

        return true;
    }

    /**
     * キューの実行状況を検査
     * 
     * @param UnitKindEnum $p_kind UNIT種別
     * @param string $p_cid 接続ID
     * @param string $p_que_nm キュー名
     * @return ?bool true（実行中） or false（停止中） or null（実行制限中）
     */
    public function isExecutedQueue(UnitKindEnum $p_kind, string $p_cid, string $p_que_nm): ?bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // UNIT種別による実行制限中
        if($p_kind === $this->descriptors[$p_cid]['restrict_unit_kind'])
        {
            return null;
        }

        $kind = $p_kind->value;
        $que = $this->descriptors[$p_cid][$kind]['queue_name'];
        $sta = $this->descriptors[$p_cid][$kind]['status_name'];
        if($p_que_nm === $que && $sta !== null)
        {
            return true;
        }
        return false;
    }

    /**
     * UNIT実行中の検査
     * 
     * @param UnitKindEnum $p_kind 調査対象種別
     * @param string $p_cid 接続ID
     * @return ?bool true（実行中） or false（未実行） or null（実行制限中）
     */
    protected function isExecutingSequence(UnitKindEnum $p_kind, string $p_cid): ?bool
    {
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // UNIT種別による実行制限中
        if($p_kind === $this->descriptors[$p_cid]['restrict_unit_kind'])
        {
            return null;
        }

        $kind = $p_kind->value;

        // キュー名の取得
        $que_nam = $this->descriptors[$p_cid][$kind]['queue_name'];

        // ステータス名の取得
        $sta_nam = $this->descriptors[$p_cid][$kind]['status_name'];

        // 実行中
        if(isset($que_nam) && isset($sta_nam))
        {
            return true;
        }

        return false;
    }

    /**
     * アライブチェックを行う
     * 
     * 任意のタイミングで一時的に実行したい時に利用する
     * 
     * ※既に設定済みの場合は何もせずに終了する
     * 
     * @param UnitKindEnum $p_kind UNIT種別
     * @param string $p_cid 接続ID
     * @param int $p_tout アライブチェックタイムアウト（秒）
     * @return bool true（成功） or false（失敗）
     */
    public function aliveCheck(UnitKindEnum $p_kind, string $p_cid, int $p_tout)
    {
        // キューの設定がない場合は抜ける
        $w_ret = $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::ALIVE->value, StatusEnum::START->value);
        if($w_ret === false)
        {
            return true;
        }

        // 実行中のキュー名を取得
        $nam = $this->getQueueName($p_kind, $p_cid);

        // 現在のタイムアウト値を取得
        $w_ret = $this->getProperties($p_cid, ['alive_adjust_timeout']);
        if($w_ret === false)
        {
            return false;
        }

        // 設定済みの場合は抜ける
        if($nam === ProtocolQueueEnum::ALIVE->value && isset($w_ret['alive_adjust_timeout']))
        {
            return true;
        }

        // アライブチェックを開始する
        $w_ret = $this->setQueueNameForStart($p_kind, $p_cid, ProtocolQueueEnum::ALIVE->value);
        if($w_ret === false)
        {
            return false;
        }

        // タイムアウトを設定
        $w_ret = $this->setProperties($p_cid, ['alive_adjust_timeout' => $p_tout]);
        if($w_ret === false)
        {
            return false;
        }

        return true;
    }

    /**
     * ソケット切断シーケンスの開始
     * 
     * プロトコルUNIT実行中に呼ばれた場合は例外を投げて現在の処理を中断する
     * 
     * @param string $p_cid 接続ID
     * @param mixed $p_param 切断パラメータ
     * @param bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ  
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     * @param bool $p_throw_break スローブレイクフラグ
     * 
     * @return bool true（成功） or false（失敗）
     */
    public function close(string $p_cid, $p_param = null, bool $p_convert = true, bool $p_throw_break = true): bool
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // キューの設定がない場合は抜ける
        $w_ret = $this->cycle_driven_for_protocol->isSetQueue(ProtocolQueueEnum::CLOSE->value, StatusEnum::START->value);
        if($w_ret === false)
        {
            return true;
        }

        // シリアライザーの実行
        $param = $p_param;
        $serializer = $this->serializer;
        if($p_convert === true && $serializer !== null)
        {
            $param = $serializer($param);
        }

        // 切断パラメータの設定
        $w_ret = $this->setProperties($p_cid, ['close_buffer' => $param]);
        if($w_ret === false)
        {
            return false;
        }

        // 切断シーケンスを実行
        $this->descriptors[$p_cid][UnitKindEnum::PROTOCOL->value]['queue_name'] = ProtocolQueueEnum::CLOSE->value;
        $this->descriptors[$p_cid][UnitKindEnum::PROTOCOL->value]['status_name'] = StatusEnum::START->value;

        // プロトコルUNIT実行中は例外を投げて中断する
        if($p_throw_break)
        {
            $w_ret = $this->unit_parameter->getUnitKind();
            if($w_ret === UnitKindEnum::PROTOCOL)
            {
                $this->throwBreak();
            }
        }

        return true;
    }

    /**
     * プロトコルUNIT処理を中断する
     * 
     * 実行されると例外キャッチ時に切断処理は無視されて処理を継続する
     */
    public function throwBreak()
    {
        // 例外発行
        throw new UnitException(
            UnitExceptionEnum::ECODE_THROW_BREAK->message($this->lang),
            UnitExceptionEnum::ECODE_THROW_BREAK->value,
            $this->unit_parameter
        );
    }

    /**
     * UNITの実行
     * 
     * @param UnitKindEnum $p_kind UNIT種別
     * @param string $p_cid 接続ID
     * @return ?bool true（成功） or false（失敗） or null（実行制限中）
     */
    protected function executeUnit(UnitKindEnum $p_kind, string $p_cid): ?bool
    {
        // UNIT種別による実行制限中
        if($p_kind === $this->descriptors[$p_cid]['restrict_unit_kind'])
        {
            return null;
        }

        // 周期ドリブンマネージャーの取得
        $cycle_driven = null;
        if($p_kind === UnitKindEnum::PROTOCOL)
        {
            $cycle_driven = $this->cycle_driven_for_protocol;
        }
        else
        if($p_kind === UnitKindEnum::COMMAND)
        {
            $cycle_driven = $this->cycle_driven_for_command;
        }
        else
        if($p_kind === UnitKindEnum::ENGINE)
        {
            $cycle_driven = $this->cycle_driven_for_engine;
        }
        else
        {
            return false;
        }

        // UNITの実行
        $this->unit_parameter->setUnitKind($p_kind);
        try
        {
            $w_ret = $cycle_driven->cycleDriven($this->unit_parameter);
            if($w_ret === false)
            {
                $que = $this->getQueueName($p_kind, $p_cid);
                $sta = $this->getStatusName($p_kind, $p_cid);
                $this->logWriter('warning', [__METHOD__ => LogMessageEnum::UNIT_NO_SETTING->message($this->lang)."[{$que}][{$sta}]"]);
            }
        }
        catch(UnitException | Exception $e)
        {
            $w_ret = get_class($e);
            if($w_ret === self::E_CLASS_NAME_FOR_UNIT)
            {
                if($e->getCode() !== UnitExceptionEnum::ECODE_THROW_BREAK->value)
                {
                    $this->logWriter('error', $e->getArrayMessage());
                    $this->emergencyShutdown($p_kind, $p_cid);
                    return false;
                }
            }
            else
            {
                $this->logWriter('error', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $this->emergencyShutdown($p_kind, $p_cid);
                return false;
            }
        }

        return true;
    }

    /**
     * UNIT種別制限の設定
     * 
     * @param ?UnitKindEnum $p_kind UNIT種別（null:制限解除）
     * @param string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    public function setRestrictUnitKind(?UnitKindEnum $p_kind, string $p_cid): bool
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        $this->descriptors[$p_cid]['restrict_unit_kind'] = $p_kind;
    }


    //--------------------------------------------------------------------------
    // システムコール関連
    //--------------------------------------------------------------------------

    /**
     * ソケットセレクト
     * 
     * @param int $p_utimer ブロッキングタイム（マイクロ秒）
     * @return bool true（成功） or false（失敗）
     */
    abstract protected function select($p_utimer = 0): bool;

    /**
     * ソケットクローズ
     * 
     * @param string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    abstract public function shutdown(string $p_cid): bool;

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
     * 緊急停止
     * 
     * @param UnitKindEnum $p_kind 対象種別
     * @param string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    protected function emergencyShutdown(UnitKindEnum $p_kind, string $p_cid): bool
    {
        // UNITパラメータをエンジン種別として設定
        $this->unit_parameter->setUnitKind($p_kind);

        $this->unit_parameter->setConnectionId($p_cid);

        // 緊急停止時コールバックを実行
        $emergency_callback = $this->emergency_callback;
        if($emergency_callback !== null)
        {
            $emergency_callback($this->unit_parameter);
        }

        return $this->shutdown($p_cid);
    }

    /**
     * ソケットのアドレス情報を取得
     * 
     * @param ?string $p_cid 接続ID or null（待ち受けソケット）
     * @param string &$p_host ホストアドレス格納先
     * @param int &$p_port ポート番号格納先
     * @return bool true（成功） or false（失敗）
     */
    public function getSockName(?string $p_cid, string &$p_host, int &$p_port): bool
    {
        // ディスクリプタが存在しない場合は抜ける
        if(!isset($this->sockets[$p_cid]))
        {
            return false;
        }

        return socket_getsockname($this->sockets[$p_cid], $p_host, $p_port);
    }


    //--------------------------------------------------------------------------
    // 送受信バッファ操作
    //--------------------------------------------------------------------------

    /**
     * 受信データスタックから取得
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param string $p_cid 接続ID
     * @param bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ
     * 
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     * 
     * @return mixed 受信した最新のペイロードデータ or null（空） or false（失敗）
     */
    protected function getRecvStack(string $p_cid, bool $p_convert = true)
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // １件もなければ抜ける
        $cnt = count($this->descriptors[$p_cid]['app']['recv_buffers']);
        if($cnt <= 0)
        {
            return null;
        }

        // １件分取得
        $buf = array_shift($this->descriptors[$p_cid]['app']['recv_buffers']);

        $dat = $buf;
        if($p_convert === true)
        {
            // アンシリアライザーの実行
            $unserializer = $this->unserializer;
            if($unserializer !== null)
            {
                $dat = $unserializer($buf);
            }
        }

        return $dat;
    }

    /**
     * 受信データスタックへ設定
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param string $p_cid 接続ID
     * @param mixed $p_data 設定するデータ
     * @param bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ
     * 
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     * 
     * @return bool true（成功） or false（失敗）
     */
    public function setRecvStack(string $p_cid, $p_data = null, bool $p_convert = false): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // シリアライザーの実行
        $data = $p_data;
        $serializer = $this->serializer;
        if($p_convert === true && $serializer !== null)
        {
            $data = $serializer($data);
        }

        // 最後尾に追加
        array_push($this->descriptors[$p_cid]['app']['recv_buffers'], $data);

        return true;
    }

    /**
     * 送信データスタックから取得
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param string $p_cid 接続ID
     * @param bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ
     * 
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     * 
     * @return mixed 送信データスタックエントリ or null（空） or false（失敗）
     */
    protected function getSendStack(string $p_cid, bool $p_convert = false)
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // １件もなければ抜ける
        $cnt = count($this->descriptors[$p_cid]['app']['send_buffers']);
        if($cnt <= 0)
        {
            return null;
        }

        // １件分取得
        $buf = array_shift($this->descriptors[$p_cid]['app']['send_buffers']);

        // アンシリアライザーの実行
        $data = $buf;
        if($p_convert === true)
        {
            $unserializer = $this->unserializer;
            if($unserializer !== null)
            {
                $data = $unserializer($buf);
            }
        }

        return $data;
    }

    /**
     * 送信データスタックへ設定
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param string $p_cid 接続ID
     * @param mixed $p_data 送信データ
     * @return bool true（成功） or false（失敗）
     */
    public function setSendStack(string $p_cid, $p_data): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // シリアライズ
        $data = $p_data;
        $serializer = $this->serializer;
        if($serializer !== null)
        {
            $data = $serializer($data);
        }

        // 送信データスタックへ追加
        array_push($this->descriptors[$p_cid]['app']['send_buffers'], $data);

        return true;
    }

    /**
     * 全接続の送信データスタックへ設定
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param string $p_cid 接続ID
     * @param mixed $p_data 送信データ
     * @param bool $p_self_remove 自身の接続の除外フラグ
     * @return bool true（成功） or false（失敗）
     */
    public function setSendStackAll(string $p_cid, $p_data, bool $p_self_remove = false): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }

        // 全ディスクリプタを退避
        $dess = $this->descriptors;

        // 自身を除く
        if($p_self_remove === true)
        {
            unset($dess[$p_cid]);
        }

        // 全ディスクリプタでループ
        foreach($dess as $cid => $des)
        {
            // 送信データスタックへ追加
            $w_ret = $this->setSendStack($cid, $p_data);
            if($w_ret === false)
            {
                return false;
            }
        }

        return true;
    }


    //--------------------------------------------------------------------------
    // タイマ関連
    //--------------------------------------------------------------------------

    /**
     * タイマーリセット
     * 
     * @param string $p_cid 接続ID
     * @param string $p_name タイマ名
     * @return bool true（成功） or false（失敗）
     */
    public function resetTimer(string $p_cid, string $p_name): bool
    {
        // ディスクリプタが存在しなければ抜ける
        if(!isset($this->descriptors[$p_cid]))
        {
            return false;
        }
        $this->descriptors[$p_cid]['elapsed_timers'][$p_name] = microtime(true);
        return true;
    }

    /**
     * タイマー（経過時間）取得
     * 
     * @param string $p_cid 接続ID
     * @param string $p_name タイマ名
     * @return int 経過時間（ms）
     */
    public function elapsed(string $p_cid, string $p_name): int
    {
        if(!isset($this->descriptors[$p_cid]['elapsed_timers'][$p_name]))
        {
            return 0;
        }
        return (int)((microtime(true) - $this->descriptors[$p_cid]['elapsed_timers'][$p_name]) * 1000);
    }

    /**
     * タイマー削除
     * 
     * @param string $p_cid 接続ID
     * @param string $p_name タイマ名
     */
    public function removeTimer(string $p_cid, string $p_name): void
    {
        unset($this->descriptors[$p_cid]['elapsed_timers'][$p_name]);
    }


    //--------------------------------------------------------------------------
    // 内部処理
    //--------------------------------------------------------------------------

    /**
     * 現在のクライアント数を取得
     * 
     * @return int クライアント数
     */
    public function getClientCount(): int
    {
        return count($this->descriptors);
    }

    /**
     * ログ出力
     * 
     * SocketManagerで使用しているチャンネル名と同じになる
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
            if (!isset($this->descriptors[$cid])) {
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

            // 最終アクセスタイムスタンプを取得
            $w_ret = $this->getProperties($cid, ['last_access_timestamp']);
            if($w_ret === false)
            {
                return false;
            }
            $timestamp = $w_ret['last_access_timestamp'];

            // UNITパラメータへ接続IDを設定
            $this->unit_parameter->setConnectionId($cid);

            // アライブチェック
            if($alive_check === 0)  // プロトコル部実行中のタイムアウトを検査
            {
                if($flg_exec === true && $p_alive_interval > 0)
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
                    // コマンドUNITの実行
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
        }

        return true;
    }

}
