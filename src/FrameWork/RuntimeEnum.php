<?php
/**
 * runtimeタイプのENUMファイル
 * 
 * フレームワーク用
 */

namespace SocketManager\Library\FrameWork;


/**
 * runtimeタイプの定義
 * 
 * フレームワーク用
 */
enum RuntimeEnum: string
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var 初期化クラス
     */
    case INIT = 'init';

    /**
     * @var UNITパラメータクラス
     */
    case PARAMETER = 'parameter';

    /**
     * @var UNITクラス
     */
    case UNITS = 'units';

    /**
     * @var メインクラス
     */
    case MAIN = 'main';


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * ディレクトリ名の取得
     * 
     * @return string ディレクトリ名
     */
    public function directory(): string
    {
        return match($this)
        {
            self::INIT => 'InitClass',
            self::PARAMETER => 'UnitParameter',
            self::UNITS => 'RuntimeUnits',
            self::MAIN => 'MainClass'
        };
    }

    /**
     * クラス名の取得
     * 
     * @return string クラス名
     */
    public function class(): string
    {
        return match($this)
        {
            self::INIT => 'InitForRuntimeTemplate',
            self::PARAMETER => 'ParameterForRuntimeTemplate',
            self::UNITS => 'RuntimeForTemplate',
            self::MAIN => 'MainForRuntimeTemplate'
        };
    }

    /**
     * Enum名の取得（キュー定義）
     * 
     * @return string Enum名（キュー定義）
     */
    public function enumQueue(): string
    {
        return match($this)
        {
            self::UNITS => 'RuntimeQueueEnumForTemplate'
        };
    }

    /**
     * Enum名の取得（ステータス定義）
     * 
     * @return string Enum名（ステータス定義）
     */
    public function enumStatus(): string
    {
        return match($this)
        {
            self::UNITS => 'RuntimeStatusEnumForTemplate'
        };
    }
}
