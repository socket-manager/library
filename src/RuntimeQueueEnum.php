<?php
/**
 * ランタイム用のキュー名のENUMファイル
 * 
 * ライブラリ用
 */

namespace SocketManager\Library;


/**
 * ランタイム用の規定のキュー名定義
 * 
 * ライブラリ用
 */
enum RuntimeQueueEnum: string
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var アクセプト時のキュー名
     */
    case STARTUP = 'startup';


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

}
