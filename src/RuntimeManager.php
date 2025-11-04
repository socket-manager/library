<?php
/**
 * ライブラリファイル
 * 
 * ランタイムマネージャークラスのファイル
 */

namespace SocketManager\Library;


use Exception;


/**
 * ランタイムマネージャークラス
 * 
 * ランタイムリソースの管理と周期ドリブンの制御を行う
 */
class RuntimeManager
{
    //--------------------------------------------------------------------------
    // 定数（その他）
    //--------------------------------------------------------------------------

    /**
     * EXCEPTIONクラス名（UNIT処理用）
     */
    private const E_CLASS_NAME_FOR_UNIT = 'SocketManager\Library\UnitException';

    /**
     * インターバル間隔
     */
    private const INTERVAL_SPAN = 10000000;


    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * ディスクリプタ
     * 
     */
    private array $descriptor = [];

    /**
     * ログライター
     * 
     */
    private $log_writer = null;

    /**
     * 緊急停止時のコールバック
     * 
     * 例外等の緊急停止時に実行される
     */
    private $emergency_callback = null;

    /**
     * 言語設定
     * 
     * デフォルト：'ja'
     */
    private string $lang = 'ja';

    /**
     * インターバル間隔計測開始時間
     * 
     */
    private float $prev_microtime = 0;

    /**
     * 周期ドリブンマネージャー（ランタイム用）
     */
    private CycleDrivenManager $cycle_driven;

    /**
     * RuntimeManager用として扱うUNITパラメータ
     */
    private ?RuntimeManagerParameter $unit_parameter = null;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param ?string $p_que_name — キュー名 or null（なし）
     */
    public function __construct
    (
        ?string $p_que_name = RuntimeQueueEnum::STARTUP->value
    )
    {
        // 言語設定
        $w_ret = config('app.locale', 'en');
        if($w_ret !== 'ja' && $w_ret !== 'en')
        {
            $w_ret = 'en';
        }
        $this->lang = $w_ret;

        // UNITパラメータの設定
        $this->unit_parameter = new RuntimeManagerParameter($this->lang);
        $this->unit_parameter->setRuntimeManager($this);

        // 周期ドリブンマネージャーの設定
        $this->cycle_driven = new CycleDrivenManager();

        // 周期インターバル
        $this->prev_microtime = hrtime(true);

        // キュー名の設定（処理開始用）
        $this->setQueueNameForStart($p_que_name);

        return;
    }

    /**
     * IInitRuntimeManagerによる初期化
     * 
     * @param IInitRuntimeManager $p_init IInitRuntimeManagerのインスタンス
     */
    public function setInitRuntimeManager(IInitRuntimeManager $p_init)
    {
        // ログライターの登録
        $w_ret = $p_init->getLogWriter();
        if($w_ret !== null)
        {
            $this->log_writer = $w_ret;
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
            $this->unit_parameter->setRuntimeManager($this);
            $this->unit_parameter->setLanguage($this->lang);
        }
    }

    /**
     * IEntryUnitsによるUNIT登録（ランタイム用）
     * 
     * @param IEntryUnits $p_entry IEntryUnitsのインスタンス
     */
    public function setRuntimeUnits(IEntryUnits $p_entry)
    {
        // キューリストの取得
        $ques = $p_entry->getQueueList();

        foreach($ques as $que)
        {
            // UNITリストの取得
            $units = $p_entry->getUnitList($que);
            foreach($units as $unit)
            {
                $this->cycle_driven->addStatusUnit($que, $unit['status'], $unit['unit']);
            }
        }
    }

    /**
     * 周期ドリブン処理の実行
     * 
     * @param int $p_cycle_interval 周期インターバルタイム（マイクロ秒）
     * @return bool true（成功） or false（失敗、または停止）
     */
    public function cycleDriven(int $p_cycle_interval = 2000): bool
    {
        // UNITの実行
        try
        {
            $w_ret = $this->cycle_driven->cycleDriven($this->unit_parameter);
            if($w_ret === false)
            {
                $que = $this->getQueueName();
                $sta = $this->getStatusName();
                $this->logWriter('warning', [__METHOD__ => LogMessageEnum::UNIT_NO_SETTING->message($this->lang)."[{$que}][{$sta}]"]);
            }
        }
        catch(UnitException | Exception $e)
        {
            $w_ret = get_class($e);
            if($w_ret === self::E_CLASS_NAME_FOR_UNIT)
            {
                if($e->getCode() === UnitExceptionEnum::ECODE_THROW_BREAK->value)
                {
                    $this->logWriter('notice', $e->getArrayMessage());
                }
                else
                if($e->getCode() === UnitExceptionEnum::ECODE_FINISH_SHUTDOWN->value)
                {
                    $this->logWriter('info', $e->getArrayMessage());
                    return false;
                }
                else
                {
                    $this->logWriter('error', $e->getArrayMessage());
                    return false;
                }
            }
            else
            {
                $this->logWriter('error', ['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

                // 緊急停止時コールバックを実行
                $callback = $this->emergency_callback;
                if($callback !== null)
                {
                    $callback($this->unit_parameter);
                }

                return false;
            }
        }

        $now_microtime = hrtime(true);
        if(($now_microtime - $this->prev_microtime) >= self::INTERVAL_SPAN)
        {
            // 周期インターバル
            usleep($p_cycle_interval);
            $this->prev_microtime = $now_microtime;
        }

        return true;
    }

    /**
     * キュー名の取得
     * 
     * @return ?string キュー名 or null（なし）
     */
    public function getQueueName(): ?string
    {
        $w_ret = $this->descriptor['queue_name'];
        return $w_ret;
    }

    /**
     * キュー名の設定
     * 
     * @param ?string $p_name キュー名 or null（なし）
     */
    public function setQueueName(?string $p_name)
    {
        $this->descriptor['queue_name'] = $p_name;
        return;
    }

    /**
     * ステータス名の取得
     * 
     * @return ?string ステータス名 or null（なし）
     */
    public function getStatusName(): ?string
    {
        $w_ret = $this->descriptor['status_name'];
        return $w_ret;
    }

    /**
     * ステータス名の設定
     * 
     * @param ?string $p_name ステータス名 or null（なし）
     */
    public function setStatusName(?string $p_name)
    {
        $this->descriptor['status_name'] = $p_name;
        return;
    }

    /**
     * キューの実行状況を検査
     * 
     * @param string $p_que_nm キュー名
     * @return bool true（実行中） or false（停止中）
     */
    public function isExecutedQueue(string $p_que_nm): bool
    {
        $que = $this->getQueueName();
        $sta = $this->getStatusName();
        if($p_que_nm === $que && $sta !== null)
        {
            return true;
        }
        return false;
    }

    /**
     * ログ出力
     * 
     * RuntimeManagerで使用しているチャンネル名と同じになる
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
     * UNIT処理を中断する
     * 
     * 実行されると例外キャッチ時に緊急停止処理は無視されて処理を継続する
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
     * キュー名の設定（処理開始用）
     * 
     * @param ?string $p_name キュー名 or null（なし）
     * @return bool true（成功） or false (失敗)
     */
    private function setQueueNameForStart(?string $p_name): bool
    {
        // キュー名の設定
        $this->descriptor['queue_name'] = $p_name;

        // ステータス名の設定
        if($p_name === null)
        {
            $this->descriptor['status_name'] = null;
        }
        else
        {
            $this->descriptor['status_name'] = StatusEnum::START->value;
        }

        return true;
    }

}
