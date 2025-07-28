<?php
/**
 * ステータスUNIT登録クラスのファイル
 * 
 * RuntimeManagerのsetRuntimeUnitsメソッドへ引き渡されるクラスのファイル
 */

namespace App\RuntimeUnits;


use SocketManager\Library\IEntryUnits;
use App\RuntimeUnits\RuntimeQueueEnumForTemplate;
use App\RuntimeUnits\RuntimeStatusEnumForTemplate;
use SocketManager\Library\RuntimeManagerParameter;


/**
 * ランタイムUNIT登録クラス
 * 
 * IEntryUnitsインタフェースをインプリメントする
 */
class RuntimeForTemplate implements IEntryUnits
{
    /**
     * @var const QUEUE_LIST キュー名のリスト
     */
    protected const QUEUE_LIST = [
        RuntimeQueueEnumForTemplate::STARTUP->value     // 起動処理のキュー
    ];


    /**
     * コンストラクタ
     * 
     */
    public function __construct()
    {
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

        if($p_que === RuntimeQueueEnumForTemplate::STARTUP->value)
        {
            $ret[] = [
                'status' => RuntimeStatusEnumForTemplate::START->value,
                'unit' => $this->getStartupStart()
            ];
        }

        return $ret;
    }


    /**
     * 以降はステータスUNITの定義（"STARTUP"キュー）
     */

    /**
     * ステータス名： START
     * 
     * 処理名：起動処理開始
     * 
     * @param RuntimeManagerParameter $p_param UNITパラメータ
     * @return ?string 遷移先のステータス名
     */
    protected function getStartupStart()
    {
        return function(RuntimeManagerParameter $p_param): ?string
        {
            $p_param->logWriter('debug', ['STARTUP' => 'START']);

            return null;
        };
    }

}
