<?php
/**
 * メイン処理クラスのファイル
 * 
 * RuntimeManagerの実行
 */

namespace App\MainClass;

use SocketManager\Library\RuntimeManager;
use SocketManager\Library\FrameWork\Console;


/**
 * メイン処理クラス
 * 
 * RuntimeManagerの初期化と実行
 */
class MainForRuntimeTemplate extends Console
{
    /**
     * @var string $identifer アプリケーション識別子
     */
    protected string $identifer = 'app:template-application';

    /**
     * @var string $description コマンド説明
     */
    protected string $description = 'Command description';


    /**
     * アプリケーション起動
     * 
     */
    public function exec()
    {
        // ランタイムマネージャーのインスタンス設定
        $manager_runtime = new RuntimeManager();

        /***********************************************************************
         * ランタイムマネージャーの初期設定
         * 
         * ランタイムUNITクラス等のインスタンスをここで設定します
         **********************************************************************/

        /**
         * 初期化クラスの設定
         * 
         * $manager_runtime->setInitRuntimeManager()メソッドで初期化クラスを設定します
         */

        /**
         * ランタイムUNITの設定
         * 
         * $manager_runtime->setRuntimeUnits()メソッドでランタイムUNITクラスを設定します
         */

        /***********************************************************************
         * ランタイムマネージャーの実行
         * 
         * 周期ドリブン処理を実行します
         **********************************************************************/

        // ノンブロッキングループ
        while(true)
        {
            // 周期ドリブン
            $ret = $manager_runtime->cycleDriven();
            if($ret === false)
            {
                goto finish;
            }
        }

finish:
        return;
    }
}
