<?php
/**
 * ライブラリファイル
 * 
 * プロトコルパラメータインタフェースのファイル
 */

 namespace SocketManager\Library\PureUdp;


/**
 * プロトコルパラメータインタフェース
 * 
 * 周期ドリブンマネージャーへ引き渡すパラメータの管理と制御を行う
 */
interface IProtocolParameter
{
    /**
     * データ受信
     * 
     * @param ?string &$p_data 受信データ
     * @param ?string &$p_host 送信元ホスト
     * @param ?int    &$p_port 送信元ポート
     * @return ?int 受信データバイト数。データがない場合はnull。
     */
    public function recvfrom(?string &$p_data, ?string &$p_host, ?int &$p_port): ?int;

    /**
     * データ送信
     * 
     * @param string $p_data  送信データ
     * @param ?string $p_host 送信先ホスト（nullの場合は最後に受信したリモートホストが適用される）
     * @param ?int $p_port    送信先ポート（nullの場合は最後に受信したリモートポートが適用される）
     * @return ?int 送信したバイト数。送信の保留が必要な場合にnullを返す。
     */
    public function sendto(string $p_data, ?string $p_host = null, ?int $p_port = null): ?int;

    /**
     * 処理対象の送信データを取得
     * 
     * @return mixed 送信データ or null（データなし）
     */
    public function getSendData();
}
