<?php
/**
 * simpleタイプのENUMファイル
 * 
 * フレームワーク用
 */

namespace SocketManager\Library\FrameWork;


/**
 * simpleタイプの定義
 * 
 * フレームワーク用
 */
enum SimpleEnum: string
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var TCPサーバークラス
     */
    case TCP_SERVER = 'tcp-server';

    /**
     * @var TCPクライアントクラス
     */
    case TCP_CLIENT = 'tcp-client';

    /**
     * @var UDPクラス
     */
    case UDP = 'udp';


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * ディレクトリ名の取得
     * 
     * @return string ディレクトリ名
     */
    public function directory(): string
    {
        return match($this)
        {
            self::TCP_SERVER => 'MainClass',
            self::TCP_CLIENT => 'MainClass',
            self::UDP => 'MainClass'
        };
    }

    /**
     * クラス名の取得
     * 
     * @return string クラス名
     */
    public function class(): string
    {
        return match($this)
        {
            self::TCP_SERVER => 'MainForSimpleTemplate',
            self::TCP_CLIENT => 'MainForSimpleTemplate',
            self::UDP => 'MainForSimpleTemplate'
        };
    }
}
