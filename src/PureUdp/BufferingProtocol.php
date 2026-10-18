<?php
/**
 * プロトコルUNITパラメータクラスのファイル
 * 
 * プロトコルUNITでのみ使えるバッファリング用パラメータクラス
 */
namespace SocketManager\Library\PureUdp;

use SocketManager\Library\PureUdpManager;
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
    private PureUdpManager $manager;

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
     * @return ?int 送信したバイト数。送信の保留が必要な場合にnullを返す。
     */
    public function sendto(string $p_data, ?string $p_host = null, ?int $p_port = null): ?int
    {
        $cid = $this->param->getConnectionId();
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

}
