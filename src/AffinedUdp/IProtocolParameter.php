<?php
/**
 * ライブラリファイル
 * 
 * プロトコルパラメータインタフェースのファイル
 */

 namespace SocketManager\Library\AffinedUdp;


/**
 * プロトコルパラメータインタフェース
 * 
 * 周期ドリブンマネージャーへ引き渡すパラメータの管理と制御を行う
 */
interface IProtocolParameter
{
    /**
     * データ受信サイズの設定
     * 
     * @param int $p_size 受信サイズ
     */
    public function setReceivingSize(int $p_size);

    /**
     * データ受信
     * 
     * setReceivingSizeで設定されたサイズ分を受信するまで続ける
     * 
     * @return mixed 受信データ or null（受信中）
     */
    public function receiving();

    /**
     * 送信データの設定
     * 
     * @param string $p_data 送信データ
     */
    public function setSendingData(string $p_data);

    /**
     * データ送信
     * 
     * setSendingDataで設定されたデータを送信するまで続ける
     * 
     * @return mixed true（成功） or null（送信中）
     */
    public function sending();

    /**
     * 処理対象の送信データを取得
     * 
     * @return mixed 送信データ or null（データなし）
     */
    public function getSendData();
}
