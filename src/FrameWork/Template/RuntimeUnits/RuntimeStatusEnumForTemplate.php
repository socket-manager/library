<?php
/**
 * ステータス名のENUMファイル
 * 
 * StatusEnumの定義を除いて自由定義
 */

namespace App\RuntimeUnits;


use SocketManager\Library\StatusEnum;


/**
 * ランタイムUNITステータス名定義
 * 
 * ランタイムUNITのステータス予約名はSTART（処理開始）のみ
 */
enum RuntimeStatusEnumForTemplate: string
{
    /**
     * @var string 処理開始時のステータス共通
     */
    case START = StatusEnum::START->value;

}
