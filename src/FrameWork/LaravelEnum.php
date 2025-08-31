<?php
/**
 * laravelタイプのENUMファイル
 * 
 * フレームワーク用
 */

namespace SocketManager\Library\FrameWork;


/**
 * laravelタイプの定義
 * 
 * フレームワーク用
 */
enum LaravelEnum: string
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var コマンドクラス
     */
    case COMMAND = 'command';


    /**
     * 出力先ディレクトリの階層定義
     */
    const DST_DIRECTORIES =
    [
        'Console',
        'Commands'
    ];

    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * エイリアス名の取得
     * 
     * @return string エイリアス名
     */
    public function alias(): string
    {
        return match($this)
        {
            self::COMMAND => SuccessEnum::COMMAND_FOR_LARAVEL->value
        };
    }

    /**
     * ディレクトリ名の取得（取得元）
     * 
     * @return string ディレクトリ名
     */
    public function srcDirectory(): string
    {
        return match($this)
        {
            self::COMMAND => 'MainClass'
        };
    }

    /**
     * ディレクトリ名の取得（出力先）
     * 
     * @return array ディレクトリ名の階層リスト
     */
    public function dstDirectory(): array
    {
        return match($this)
        {
            self::COMMAND => self::DST_DIRECTORIES
        };
    }
}
