<?php
/**
 * プロトコルUNITパラメータクラスのファイル
 * 
 * プロトコルUNITでのみ使えるバッファリング用パラメータクラス
 */
namespace SocketManager\Library\AffinedUdp;

use SocketManager\Library\AffinedUdpManager;
use SocketManager\Library\UnitException;
use SocketManager\Library\UnitExceptionEnum;


/**
 * プロトコルUNITパラメータクラス
 * 
 * プロトコルUNITでのみ使えるバッファリング用パラメータクラス
 */
final class BufferingProtocol implements IProtocolParameter
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    // ソケットマネージャー
    private AffinedUdpManager $manager;

    // UNITパラメータ
    private SocketManagerParameter $param;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct()
    {
    }


    /**
     * SocketManagerParameterインスタンスの設定
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     */
    public function setSocketManagerParameter(SocketManagerParameter $p_param)
    {
        $this->param = $p_param;
        $this->manager = $p_param->getSocketManager();
    }


    //--------------------------------------------------------------------------
    // インターフェースの実装
    //--------------------------------------------------------------------------

    /**
     * データ受信サイズの設定
     * 
     * @param int $p_size 受信サイズ
     */
    final public function setReceivingSize(int $p_size)
    {
        $cid = $this->param->getConnectionId();
        $w_ret = $this->manager->setReceivingSize($cid, $p_size);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_RECEIVING_SIZE_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_RECEIVING_SIZE_SET_FAIL->value,
                $this->param
            );
        }
    }

    /**
     * データ受信
     * 
     * setReceivingSizeで設定されたサイズ分を受信するまで続ける
     * 
     * @return mixed 受信データ or null（受信中）
     */
    final public function receiving()
    {
        $cid = $this->param->getConnectionId();
        $w_ret = $this->manager->receiving($cid);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_RECEIVING_FAIL->message(),
                UnitExceptionEnum::ECODE_RECEIVING_FAIL->value,
                $this->param
            );
        }

        return $w_ret;
    }

    /**
     * 送信データの設定
     * 
     * @param string $p_data 送信データ
     */
    final public function setSendingData(string $p_data)
    {
        // 送信データの設定
        $cid = $this->param->getConnectionId();
        $w_ret = $this->manager->setSendingData($cid, $p_data);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_SENDING_DATA_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_SENDING_DATA_SET_FAIL->value,
                $this->param
            );
        }
    }

    /**
     * データ送信
     * 
     * setSendingDataで設定されたデータを送信するまで続ける
     * 
     * @return mixed true（成功） or null（送信中）
     */
    final public function sending()
    {
        // データ送信
        $cid = $this->param->getConnectionId();
        $w_ret = $this->manager->sending($cid);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_SENDING_FAIL->message(),
                UnitExceptionEnum::ECODE_SENDING_FAIL->value,
                $this->param
            );
        }

        return $w_ret;
    }

    /**
     * 処理対象の送信データを取得
     * 
     * @return mixed 送信データ or null（データなし）
     */
    final public function getSendData()
    {
        // ユーザープロパティの取得
        $cid = $this->param->getConnectionId();
        $w_ret = $this->manager->getProperties($cid, ['send_buffer']);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PICKUP_SEND_DATA_GET_FAIL->message(),
                UnitExceptionEnum::ECODE_PICKUP_SEND_DATA_GET_FAIL->value,
                $this->param
            );
        }

        return $w_ret['send_buffer'];
    }

    /**
     * データ受信
     * 
     * @param ?string &$p_data 受信データ
     * @param ?string &$p_host 送信元ホスト
     * @param ?int    &$p_port 送信元ポート
     * @return ?int 受信データバイト数。データがない場合はnull。
     */
    public function recvfrom(?string &$p_data, ?string &$p_host, ?int &$p_port): ?int
    {
        $cid = $this->param->getConnectionId();
        $w_ret = $this->manager->recvfrom($cid, $p_data, $p_host, $p_port);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_RECEIVING_FAIL->message(),
                UnitExceptionEnum::ECODE_RECEIVING_FAIL->value,
                $this->param
            );
        }
        return $w_ret;
    }

    /**
     * データ送信
     * 
     * @param string $p_data  送信データ
     * @param ?string $p_host 送信先ホスト（nullの場合は最後に受信したリモートホストが適用される）
     * @param ?int $p_port    送信先ポート（nullの場合は最後に受信したリモートポートが適用される）
     * @param ?string $p_cid  接続ID
     * @return ?int 送信したバイト数。送信の保留が必要な場合にnullを返す。
     */
    public function sendto(string $p_data, ?string $p_host = null, ?int $p_port = null, ?string $p_cid = null): ?int
    {
        $cid = null;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }
        else
        {
            $cid = $this->param->getConnectionId();
        }
        $w_ret = $this->manager->sendto($cid, $p_data, $p_host, $p_port);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_SENDING_FAIL->message(),
                UnitExceptionEnum::ECODE_SENDING_FAIL->value,
                $this->param
            );
        }
        return $w_ret;
    }

    /**
     * 宛先固定化の接続（サーバーサイド）
     * 
     * @param string $p_host ホスト
     * @param int $p_port ポート
     * @return string 新しいCID or false（失敗）
     */
    public function acceptAffined(string $p_host, int $p_port): string|false
    {
        $w_ret = $this->manager->acceptAffined($p_host, $p_port);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_METHOD_CALL_FAIL->message(),
                UnitExceptionEnum::ECODE_METHOD_CALL_FAIL->value,
                $this->param
            );
        }
        return $w_ret;
    }

    /**
     * 宛先固定化の接続（クライアントサイド）
     * 
     * @param string $p_host ホスト
     * @param int $p_port ポート
     * @return bool true（成功） or false（失敗）
     */
    public function connectAffined(string $p_host, int $p_port): bool
    {
        $w_ret = $this->manager->connectAffined($this->param->getConnectionId(), $p_host, $p_port);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_METHOD_CALL_FAIL->message(),
                UnitExceptionEnum::ECODE_METHOD_CALL_FAIL->value,
                $this->param
            );
        }
        return $w_ret;
    }

}
