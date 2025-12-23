<?php
/**
 * 失敗コード定義のENUMファイル
 * 
 * フレームワークのコマンド実行用
 */

namespace SocketManager\Library\FrameWork;


/**
 * 失敗コード定義
 * 
 * フレームワークのコマンド実行用
 */
enum FailureEnum: int
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    /**
     * @var int コマンド不正
     */
    case COMMAND_FAIL = 10;

    /**
     * @var int 存在するクラス
     */
    case EXISTING_CLASS = 20;

    /**
     * @var int 存在するEnum
     */
    case EXISTING_ENUM = 30;

    /**
     * @var int 引数の"?"指定の間違い
     */
    case ARGUMENT_QUESTION_FAIL = 40;

    /**
     * @var int クラス名が指定されていない
     */
    case NO_CLASS_NAME = 50;

    /**
     * @var int 存在しないクラス
     */
    case NON_EXISTENT_CLASS = 60;

    /**
     * @var int 存在するファイル
     */
    case EXISTING_FILE = 70;

    /**
     * @var int output定義がない（カスタムコマンド用）
     */
    case OUTPUT_NO_DEFINITION = 80;

    /**
     * @var int テンプレートがない（カスタムコマンド用）
     */
    case NO_TEMPLATE = 90;

    /**
     * @var int カスタム名がない（カスタムコマンド用）
     */
    case NO_CUSTOM_NAME = 100;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * メッセージの取得
     * 
     * @param ?string $p_file ファイル名
     * @param string $p_lang 言語
     * @return string メッセージ
     */
    public function message(?string $p_file = null, string $p_lang = 'ja'): string
    {
        $msg = null;
        if($p_lang === 'ja')
        {
            $msg = match($this)
            {
                self::COMMAND_FAIL => '指定されたコマンドは存在しません',
                self::EXISTING_CLASS => '出力先のクラスファイルが既に存在します',
                self::EXISTING_ENUM => '出力先のEnumファイルが既に存在します',
                self::ARGUMENT_QUESTION_FAIL => '"?"有りの引数の後ろに"?"無しの引数は追加できません',
                self::NO_CLASS_NAME => 'クラス名が指定されていません',
                self::NON_EXISTENT_CLASS => '指定されたクラスファイルが存在しません',
                self::EXISTING_FILE => '出力先のファイルが既に存在します',
                self::OUTPUT_NO_DEFINITION => 'output が command.php で定義されていません',
                self::NO_TEMPLATE => 'テンプレートが見つかりません',
                self::NO_CUSTOM_NAME => 'カスタム名が指定されていません'
            };
        }
        else
        if($p_lang === 'en')
        {
            $msg = match($this)
            {
                self::COMMAND_FAIL => 'The specified command does not exist',
                self::EXISTING_CLASS => 'The destination class file already exists',
                self::EXISTING_ENUM => 'The destination enum file already exists',
                self::ARGUMENT_QUESTION_FAIL => 'Arguments without "?" cannot be added after arguments with "?"',
                self::NO_CLASS_NAME => 'class name not specified',
                self::NON_EXISTENT_CLASS => 'Specified class file does not exist',
                self::EXISTING_FILE => 'Output file already exists',
                self::OUTPUT_NO_DEFINITION => 'output is not defined in command.php',
                self::NO_TEMPLATE => 'template not found',
                self::NO_CUSTOM_NAME => 'No custom name specified'
            };
        }

        // ファイル名を追加
        if($p_file !== null)
        {
            $msg .= ' '."\033[33m({$p_file})\033[m";
        }
        return $msg;
    }

    /**
     * メッセージ表示
     * 
     * @param ?string $p_file ファイル名
     * @param string $p_lang 言語
     */
    public function display(?string $p_file = null, string $p_lang = 'ja')
    {
        printf("\033[31m[\033[m\033[31mfailure\033[m\033[31m]\033[m %s\n", $this->message($p_file, $p_lang));
    }
}
