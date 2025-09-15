<?php
/**
 * メイン処理クラスのファイル
 * 
 * シンプルソケットの実行
 */

namespace App\MainClass;

use SocketManager\Library\ISimpleSocket;
use SocketManager\Library\SimpleSocketGenerator;
use SocketManager\Library\SimpleSocketTypeEnum;
use SocketManager\Library\FrameWork\Console;


/**
 * メイン処理クラス
 * 
 * シンプルソケットの初期化と実行
 */
class MainForSimpleTemplate extends Console
{
    /**
     * @var string $identifer アプリケーション識別子
     */
    protected string $identifer = 'app:template-application {port_no?}';

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
        // 引数の取得
        $port_no = $this->getParameter('port_no');

        /***********************************************************************
         * シンプルソケットジェネレータの初期設定
         * 
         * ジェネレータインスタンスの生成や各種設定をここで実行します
         **********************************************************************/
        $generator = new SimpleSocketGenerator(SimpleSocketTypeEnum::, 'localhost', $port_no);

        /**
         * ログライターの登録（任意）
         * 
         * ログライターが使いたい場合に$generator->setLogWriter()メソッドで登録します
         * SocketManager初期化クラスのログライターをそのままお使い頂けます
         */
        $generator->setLogWriter
        (
            /**
             * ログライター
             * 
             * @param string $p_level ログレベル（debug、info、notice、warning、errorなど）
             * @param array $p_param 連想配列形式のログ内容
             * @return void
             */
            function(string $p_level, array $p_param): void
            {
            }
        );

        /**
         * SocketManagerとの連携（任意）
         * 
         * UNITパラメータインスタンスの"simple_socket"プロパティにシンプルソケットインスタンスが設定され
         * コマンドディスパッチャーやステータスUNIT内で使えるようになります
         * 
         * $generator->setUnitParameter()メソッドでUNITパラメータクラスを設定します
         */

        /**
         * 常時実行処理の登録（任意）
         * 
         * 常時実行処理がある場合に$generator->setKeepRunning()メソッドで登録します
         */
        $generator->setKeepRunning
        (
            /**
             * 常時実行処理
             * 
             * @param ISimpleSocket $p_simple_socket シンプルソケットインスタンス
             * @param mixed[] $p_argv 可変引数（setKeepRunningメソッドの第二引数以降のものが渡される）
             * @return void
             */
            function(ISimpleSocket $p_simple_socket): void
            {
            }
        );

        /**
         * シンプルソケットインスタンスの生成
         * 
         * この手続きが行われた時点でインスタンスが生成され有効になります
         */
        $w_ret = $generator->generate();
        if($w_ret === null)
        {
            goto finish;
        }

        /***********************************************************************
         * シンプルソケットの実行
         * 
         * 周期ドリブン処理を実行します
         **********************************************************************/

        // ノンブロッキングループ
        while(true)
        {
            // 周期ドリブン
            $ret = $generator->cycleDriven();
            if($ret === false)
            {
                goto finish;
            }
        }

finish:
        $generator->shutdownAll();
        return;
    }
}
