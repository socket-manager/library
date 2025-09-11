<?php
/**
 * ライブラリファイル
 * 
 * シンプルソケットインスタンス用インターフェースの定義ファイル
 */

 namespace SocketManager\Library;


/**
 * シンプルソケットインスタンス用インターフェース
 * 
 * TCP Server タイプ
 */
interface ISimpleSocketTcpServer
{
    /**
     * シンプルソケットタイプの取得
     * 
     * @return SimpleSocketTypeEnum シンプルソケットタイプ
     */
    public function getSimpleSocketType(): SimpleSocketTypeEnum;

    /**
     * データ送信
     * 
     * @param string $p_cid 接続ID
     * @param string $p_dat 送信データ
     * @return ?bool true（成功） or false（失敗） or null（ダウンタイム中）
     */
    public function send(string $p_cid, string $p_dat): ?bool;

    /**
     * データ受信
     * 
     * @param ?string &$p_cid 接続ID
     * @return ?string 受信データ or null（なし）
     */
    public function recv(?string &$p_cid): ?string;

    /**
     * ソケットクローズ
     * 
     * @param string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    public function shutdown(string $p_cid): bool;

    /**
     * ログ出力
     * 
     * シンプルソケットで使用しているログ出力と統合される
     * 
     * @param string $p_level ログレベル
     * @param array $p_param ログパラメータ
     */
    public function logWriter(string $p_level, array $p_param);
}
