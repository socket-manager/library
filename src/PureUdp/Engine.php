<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * SocketManagerのsetProtocolUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace SocketManager\Library\PureUdp;


use SocketManager\Library\IEntryUnits;
use SocketManager\Library\StatusEnum;


/**
 * エンジンUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class Engine implements IEntryUnits
{
    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        EngineQueueEnum::TICK->value,	    // TICKキュー
    ];

    /**
     * @var array TICKキューのUNIT群
     */
    private array $tick_units;

    /**
     * コンストラクタ
     * 
     */
    public function __construct(array $p_tick_units)
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
        return (array)static::QUEUE_LIST;
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

        if($p_que === EngineQueueEnum::TICK->value)
        {
            $ret = $this->tick_units;
        }

        return $ret;
    }
}
