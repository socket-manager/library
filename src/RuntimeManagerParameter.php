<?php
/**
 * ライブラリファイル
 * 
 * ランタイムマネージャーのUNITパラメータ用ライブラリのファイル
 */

namespace SocketManager\Library;


/**
 * UNITパラメータの基底クラス
 * 
 * 周期ドリブンマネージャーへ引き渡すパラメータの管理と制御を行う
 */
class RuntimeManagerParameter implements IUnitParameter
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * ランタイムマネージャー
     */
    private ?RuntimeManager $manager = null;

    /**
     * 言語設定
     * 
     * デフォルト：'ja'
     */
    private string $lang = 'ja';


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param ?string $p_lang 言語コード
     */
    public function __construct(?string $p_lang = null)
    {
        // 言語設定
        if($p_lang !== null)
        {
            $this->lang = $p_lang;
        }
    }


    //--------------------------------------------------------------------------
    // インタフェース（IUnitParameter）実装
    //--------------------------------------------------------------------------

    /**
     * キュー名の取得
     * 
     * @return ?string キュー名 or null（なし）
     */
    final public function getQueueName(): ?string
    {
        $w_ret = $this->manager->getQueueName();
        return $w_ret;
    }

    /**
     * キュー名の設定
     * 
     * @param ?string $p_name キュー名 or null（なし）
     */
    public function setQueueName(?string $p_name)
    {
        $this->manager->setQueueName($p_name);
        return;
    }

    /**
     * ステータス名の取得
     * 
     * @return ?string ステータス名 or null（なし）
     */
    final public function getStatusName(): ?string
    {
        $w_ret = $this->manager->getStatusName();
        return $w_ret;
    }

    /**
     * ステータス名の設定
     * 
     * @param ?string ステータス名 or null（なし）
     */
    final public function setStatusName(?string $p_name)
    {
        $this->manager->setStatusName($p_name);
        return;
    }


    //--------------------------------------------------------------------------
    // 切断や緊急停止系
    //--------------------------------------------------------------------------

    /**
     * UNIT処理を中断する
     * 
     * 実行されると例外キャッチ時に緊急停止処理は無視されて処理を継続する
     */
    final public function throwBreak()
    {
        $this->manager->throwBreak();
    }

    /**
     * 緊急停止（即時切断）
     */
    final public function emergencyShutdown()
    {
        // 例外発行
        throw new UnitException(
            UnitExceptionEnum::ECODE_EMERGENCY_SHUTDOWN->message($this->lang),
            UnitExceptionEnum::ECODE_EMERGENCY_SHUTDOWN->value,
            $this
        );
    }


    //--------------------------------------------------------------------------
    // その他
    //--------------------------------------------------------------------------

    /**
     * ログライター
     * 
     * SocketManagerで使用しているログライターと同じ
     * 
     * @param string $p_level ログレベル
     * @param array $p_param ログパラメータ
     */
    final public function logWriter(string $p_level, array $p_param)
    {
        $this->manager->logWriter($p_level, $p_param);
    }

    /**
     * キューの実行状況を検査
     * 
     * @param string $p_que キュー名
     * @return bool true（実行中） or false（停止中）
     */
    final public function isExecutedQueue(string $p_que): bool
    {
        $w_ret = $this->manager->isExecutedQueue($p_que);
        return $w_ret;
    }

    /**
     * 言語コードの取得
     * 
     * @return string 言語コード
     */
    final public function getLanguage(): string
    {
        $w_ret = $this->lang;
        return $w_ret;
    }

    /**
     * 言語コードの設定
     * 
     * @param string 言語コード
     */
    final public function setLanguage(string $p_lang)
    {
        $this->lang = $p_lang;
        return;
    }

    /**
     * ランタイムマネージャーの取得
     * 
     * @return ?RuntimeManager ランタイムマネージャーのインスタンス
     */
    final public function getRuntimeManager(): ?RuntimeManager
    {
        $w_ret = $this->manager;
        return $w_ret;
    }

    /**
     * ランタイムマネージャーの設定
     * 
     * @param RuntimeManager $p_mng ランタイムマネージャーのインスタンス
     */
    final public function setRuntimeManager(RuntimeManager $p_mng)
    {
        $this->manager = $p_mng;
        return;
    }

}
