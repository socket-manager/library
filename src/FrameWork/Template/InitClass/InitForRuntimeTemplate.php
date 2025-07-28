<?php
/**
 * RuntimeManager初期化クラスのファイル
 * 
 * RuntimeManagerのsetInitRuntimeManagerメソッドへ引き渡される初期化クラスのファイル
 */

namespace App\InitClass;


use SocketManager\Library\IInitRuntimeManager;
use SocketManager\Library\RuntimeManagerParameter;


/**
 * RuntimeManager初期化クラス
 * 
 * IInitRuntimeManagerインタフェースをインプリメントする
 */
class InitForRuntimeTemplate implements IInitRuntimeManager
{
    /**
     * コンストラクタ
     * 
     */
    public function __construct()
    {
    }

    /**
     * ログライターの取得
     * 
     * nullを返す場合は無効化（但し、ライブラリ内部で出力されているエラーメッセージも出力されない）
     * 
     * @return mixed "function(string $p_level, array $p_param): void" or null（ログ出力なし）
     */
    public function getLogWriter()
    {
        return null;
    }

    /**
     * 緊急停止時のコールバックの取得
     * 
     * 例外等の緊急切断時に実行される。nullを返す場合は無効化となる。
     * 
     * @return mixed "function(SocketManagerParameter $p_param)"
     */
    public function getEmergencyCallback()
    {
        return null;
    }

    /**
     * UNITパラメータインスタンスの取得
     * 
     * nullの場合はRuntimeManagerParameterのインスタンスが適用される
     * 
     * @return ?RuntimeManagerParameter RuntimeManagerParameterクラスのインスタンス（※1）
     * @see:RETURN （※1）当該クラス、あるいは当該クラスを継承したクラスも指定可
     */
    public function getUnitParameter(): ?RuntimeManagerParameter
    {
        return new RuntimeManagerParameter();
    }
}
