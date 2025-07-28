<?php
/**
 * キュー名のENUMファイル
 * 
 */

namespace App\RuntimeUnits;


use SocketManager\Library\RuntimeQueueEnum;


/**
 * キュー名定義
 * 
 */
enum RuntimeQueueEnumForTemplate: string
{
    /**
     * @var 起動時のキュー名
     */
    case STARTUP = RuntimeQueueEnum::STARTUP->value;

}
