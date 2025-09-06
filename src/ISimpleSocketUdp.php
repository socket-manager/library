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
 * UDP通信タイプ
 */
interface ISimpleSocketUdp
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
     * @param string $p_host ホスト
     * @param string $p_port ポート
     * @param string $p_dat 送信データ
     * @return ?bool true（成功） or false（失敗） or null（ダウンタイム中）
     */
    public function sendto(string $p_host, int $p_port, string $p_dat): ?bool;

    /**
     * データ受信
     * 
     * @param ?string &$p_host ホスト
     * @param ?int &$p_port ポート
     * @return ?string 受信データ or null（なし）
     */
    public function recvfrom(?string &$p_host, ?int &$p_port): ?string;

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
