<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * モジュール管理クラスのsetEngineUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace SocketManager\Library\AffinedUdp;


use SocketManager\Library\IEntryUnits;
use SocketManager\Library\UnitKindEnum;
use SocketManager\Library\ProtocolQueueEnum;


/**
 * エンジンUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class Engine implements IEntryUnits
{
    /**
     * @var const QUEUE_LIST_DEFAULT デフォルトのキュー名のリスト
     */
    protected const QUEUE_LIST_DEFAULT = [
        EngineQueueEnum::ACCEPT->value,	// ACCEPTキュー
        EngineQueueEnum::CONNECT->value // CONNECTキュー
    ];

    /**
     * @var const QUEUE_LIST_WITH_TICK TICKキューを含むキュー名のリスト
     */
    protected const QUEUE_LIST_WITH_TICK = [
        EngineQueueEnum::ACCEPT->value,	    // ACCEPTキュー
        EngineQueueEnum::CONNECT->value,    // CONNECTキュー
        EngineQueueEnum::TICK->value,	    // TICKキュー
    ];

    /**
     * UDP接続識別データ
     */
    private const UDP_CONNECTION_IDENTIFY = '';

    /**
     * @var array TICKキューのUNIT群
     */
    private ?array $tick_units = null;

    /**
     * @var BufferingProtocol プロトコル操作クラス
     */
    private BufferingProtocol $protocol;


    /**
     * コンストラクタ
     * 
     */
    public function __construct(BufferingProtocol $p_units)
    {
        $this->protocol = $p_units;
    }

    /**
     * TICKキューのUNIT設定
     * 
     * @param array $p_tick_units TICKキューのUNIT
     */
    public function setTickUnits(array $p_tick_units)
    {
        $this->tick_units = $p_tick_units;
    }

    /**
     * キューリストの取得
     * 
     * @return array キュー名のリスト
     */
    public function getQueueList(): array
    {
        $list = (array)static::QUEUE_LIST_DEFAULT;
        if($this->tick_units)
        {
            $list = (array)static::QUEUE_LIST_WITH_TICK;
        }
        return $list;
    }

    /**
     * ステータスUNITリストの取得
     * 
     * @param string $p_que キュー名
     * @return array キュー名に対応するUNITリスト
     */
    public function getUnitList(string $p_que): array
    {
        $ret = [];

        if($p_que === EngineQueueEnum::ACCEPT->value)
        {
            $ret[] = [
                'status' => EngineStatusEnum::START->value,
                'unit' => $this->responsePacket(...)
            ];
        }
        if($p_que === EngineQueueEnum::CONNECT->value)
        {
            $ret[] = [
                'status' => EngineStatusEnum::START->value,
                'unit' => $this->sendingPacket(...)
            ];
            $ret[] = [
                'status' => EngineStatusEnum::RECVING->value,
                'unit' => $this->recvingPacket(...)
            ];
        }
        if($p_que === EngineQueueEnum::TICK->value)
        {
            $ret = $this->tick_units;
        }

        return $ret;
    }

    /**
     * 初回パケットの受信
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     */
    private function responsePacket(SocketManagerParameter $p_param)
    {
        $data = null;
        $host = $port = null;
        $size = $this->protocol->recvfrom($data, $host, $port);
        if($size === null)
        {
            return EngineStatusEnum::START->value;
        }
        if($data === self::UDP_CONNECTION_IDENTIFY)
        {
            if($host === null || $port === null)
            {
                return EngineStatusEnum::START->value;
            }
            $cid = $this->protocol->acceptAffined($host, $port);
            if($cid === false)
            {
                $p_param->emergencyShutdown();
            }

            // ハンドシェイクのレスポンス
            $size = $this->protocol->sendto(self::UDP_CONNECTION_IDENTIFY, null, null, $cid);
            if($size === null)
            {
                $p_param->emergencyShutdown($cid);
            }
            if($size < strlen(self::UDP_CONNECTION_IDENTIFY))
            {
                $p_param->emergencyShutdown($cid);
            }

            // ACCEPTキューの実行
            $p_param->queueStart(UnitKindEnum::PROTOCOL, ProtocolQueueEnum::ACCEPT->value, $cid);

            // TICKキューの実行
            $p_param->queueStart(UnitKindEnum::ENGINE, EngineQueueEnum::TICK->value, $cid);
        }
        return EngineStatusEnum::START->value;
    }

    /**
     * 初回パケットの送信
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     */
    private function sendingPacket(SocketManagerParameter $p_param)
    {
        $size = $this->protocol->sendto(self::UDP_CONNECTION_IDENTIFY);
        if($size === null)
        {
            return EngineStatusEnum::START->value;
        }
        if($size < strlen(self::UDP_CONNECTION_IDENTIFY))
        {
            $p_param->emergencyShutdown();
        }

        return EngineStatusEnum::RECVING->value;
    }

    /**
     * レスポンスパケットの受信
     * 
     * @param SocketManagerParameter $p_param UNITパラメータ
     */
    private function recvingPacket(SocketManagerParameter $p_param)
    {
        $data = null;
        $host = $port = null;
        $size = $this->protocol->recvfrom($data, $host, $port);
        if($size === null)
        {
            return EngineStatusEnum::RECVING->value;
        }
        if($data === self::UDP_CONNECTION_IDENTIFY)
        {
            if($host === null || $port === null)
            {
                return null;
            }
            $w_ret = $this->protocol->connectAffined($host, $port);
            if($w_ret === false)
            {
                $p_param->emergencyShutdown();
            }

            // CONNECTキューの実行
            $p_param->queueStart(UnitKindEnum::PROTOCOL, ProtocolQueueEnum::CONNECT->value);

            // TICKキューの実行
            $p_param->queueStart(UnitKindEnum::ENGINE, EngineQueueEnum::TICK->value);

            // 制限解除
            $p_param->setRestrictUnitKind(null);
        }

        return null;
    }
}
