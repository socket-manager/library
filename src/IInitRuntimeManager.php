<?php
/**
 * ライブラリファイル
 * 
 * RuntimeManagerクラス初期化用インタフェースのファイル
 */

 namespace SocketManager\Library;


/**
 * RuntimeManagerクラス初期化用インタフェース
 * 
 * RuntimeManagerクラス初期化時にインプリメントしてsetInitRuntimeManagerメソッドへ渡すための定義
 * 
 * ※グローバル関数名指定も可
 */
interface IInitRuntimeManager
{
    /**
     * ログライターの取得
     * 
     * nullを返した場合は無効化される（但し、ライブラリ内部で出力されているエラーメッセージも出力されない）
     * 
     * @return mixed "function(string $p_level, array $p_param): void" or null（ログ出力なし）
     * 
     *----------------------------------------------------------------------------------------------------
     * 【ログライター関数仕様】
     * 
     * 引数1：string $p_level ログレベル（※1）
     * 
     * 引数2：array $p_param ログパラメータ
     * 
     * 戻り値：なし
     * 
     * （※1）当該ライブラリで使用しているレベル種別⇒"debug" or "info" or "notice" or "warning" or "error"
     * 
     *----------------------------------------------------------------------------------------------------
     */
    public function getLogWriter();

    /**
     * 緊急停止時のコールバックの取得
     * 
     * UNIT処理以外で緊急切断が発生した場合に実行される。
     * 
     * 発生要因⇒相手先による切断・コマンドディスパッチャーでの例外発生・アライブチェックタイムアウト
     * 
     * ※nullを返した場合は無効化される。
     * 
     * @return mixed "function(SocketManagerParameter $p_param)" or null（緊急停止時処理なし）
     * 
     *----------------------------------------------------------------------------------------------------
     * 【緊急停止時のコールバック関数仕様】
     * 
     * 引数1：SocketManagerParameter（※1） $p_param UNITパラメータ
     * 
     * 戻り値：なし
     * 
     * （※1）当該クラス、あるいは当該クラスを継承したクラスも指定可
     * 
     *----------------------------------------------------------------------------------------------------
     */
    public function getEmergencyCallback();

    /**
     * UNITパラメータインスタンスの取得
     * 
     * nullを返した場合はRuntimeManagerParameterのインスタンスが適用される
     * 
     * @return ?RuntimeManagerParameter
     * ― RuntimeManagerParameterクラスのインスタンス
     * 
     * ― 当該クラス、あるいは当該クラスを継承したクラスも指定可
     */
    public function getUnitParameter(): ?RuntimeManagerParameter;

}
