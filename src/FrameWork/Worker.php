<?php
/**
 * ライブラリファイル
 * 
 * workerコマンド用クラスのファイル
 */

namespace SocketManager\Library\FrameWork;

use Exception;


/**
 * workerコマンド用クラス
 * 
 * フレームワーク上の制御コマンド
 */
class Worker
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var string $path workerコマンドのカレントディレクトリ
     */
    private string $path;

    /**
     * @var array $params コマンドライン引数
     */
    private array $params = [];

    /**
     * @var string $laravel_command Laravelコマンド名
     */
    private string $laravel_command = 'artisan';

    /**
     * @var bool $is_laravel Laravelフラグ
     */
    private bool $is_laravel = false;

    /**
     * @var array $consoles コンソール継承クラスのインスタンスリスト
     */
    private array $consoles = [];

    /**
     * @var IConsole $console コンソールクラスのインタフェース
     */
    private ?IConsole $console = null;

    /**
     * @var CraftEnum $craft_enum CraftEnumのキャスト用
     */
    private CraftEnum $craft_enum;

    /**
     * @var RuntimeEnum $runtime_enum RuntimeEnumのキャスト用
     */
    private RuntimeEnum $runtime_enum;

    /**
     * @var SimpleEnum $simple_enum SimpleEnumのキャスト用
     */
    private SimpleEnum $simple_enum;

    /**
     * @var LaravelEnum $laravel_enum LaravelEnumのキャスト用
     */
    private LaravelEnum $laravel_enum;

    /**
     * @var SuccessEnum $success_enum SuccessEnumのキャスト用
     */
    private SuccessEnum $success_enum;

    /**
     * @var SuccessEnum $success_sub_enum SuccessEnumのキャスト用（キュー／ステータス名用）
     */
    private SuccessEnum $success_sub_enum;

    /**
     * @var array $settings 設定ファイルの読み込み先
     */
    public static array $settings = [];

    /**
     * @var string $lang 言語設定
     */
    public string $lang = 'ja';

    /**
     * @var array $locales メッセージファイルの読み込み先
     */
    public static array $locales = [];


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param string $p_path workerコマンドのカレントディレクトリ
     * @param array $p_params コマンドライン引数
     */
    public function __construct(string $p_path, array $p_params)
    {
        $this->path = $p_path;
        $this->params = $p_params;

        if(file_exists($this->path.DIRECTORY_SEPARATOR.$this->laravel_command))
        {
            $this->is_laravel = true;
        }
    }

    /**
     * workerのメイン処理
     * 
     * @return bool true（成功） or false（失敗）
     */
    public function working(): bool
    {
        // 環境のロード
        $this->loadSetting($this->is_laravel);  // 設定ファイルの読み込み
        $this->loadHelper($this->is_laravel);   // ヘルパーの読み込み

        // タイムゾーンの設定
        $timezone = self::getConfig('app.timezone', 'UTC');
        date_default_timezone_set($timezone);

        // 言語設定
        $w_ret = self::getConfig('app.locale', 'en');
        if($w_ret !== 'ja' && $w_ret !== 'en')
        {
            $w_ret = 'en';
        }
        $this->lang = $w_ret;

        // メッセージファイルの読み込み
        $this->loadLocale($this->is_laravel);

        // Usage表示
        if(count($this->params) < 2)
        {
            $usage = UsageEnum::HEADER->message($this->is_laravel, $this->lang);

            // Laravel環境の場合
            if($this->is_laravel === true)
            {
                $usage .= UsageEnum::CRAFT->message($this->is_laravel, $this->lang);
                $usage .= UsageEnum::RUNTIME->message($this->is_laravel, $this->lang);
                $usage .= UsageEnum::SIMPLE->message($this->is_laravel, $this->lang);
                $usage .= UsageEnum::LARAVEL->message($this->is_laravel, $this->lang);
                $usage .= UsageEnum::SEPARATOR->message($this->is_laravel, $this->lang);
                printf($usage);
                require_once($this->path.'/artisan');
                return false;
            }

            // メインクラスのリストを設定
            $this->setMainClassList();

            // メインクラスのUsageを生成
            $usage .= UsageEnum::MAIN->message($this->is_laravel, $this->lang);
            foreach($this->consoles as $console)
            {
                // コンソールクラスの設定
                $this->console = $console;

                // 識別子判定
                $identifer = $this->console->getIdentifer();
                if(strlen($identifer) <= 0)
                {
                    continue;
                }

                // コマンド説明の取得
                $description = $this->console->getDescription();

                // Usage文字列の生成
                $w_usage = UsageEnum::MAIN_IDENTIFER->replace($identifer);
                $w_usage_len = 50 - (strlen($w_usage) - 8);
                $w_usage .= str_pad('', $w_usage_len, ' ');
                $usage .= "{$w_usage}{$description}\n";
            }
            if(count($this->consoles) <= 0)
            {
                $usage .= UsageEnum::MAIN_EMPTY->message($this->is_laravel, $this->lang);
            }

            // Usage表示
            $usage .= UsageEnum::CRAFT->message($this->is_laravel, $this->lang);
            $usage .= UsageEnum::RUNTIME->message($this->is_laravel, $this->lang);
            $usage .= UsageEnum::SIMPLE->message($this->is_laravel, $this->lang);
            $usage .= UsageEnum::LARAVEL->message($this->is_laravel, $this->lang);
            printf($usage);
            return false;
        }

        // コロンセパレータの判定
        $parts = explode(':', $this->params[1]);
        if(count($parts) !== 2)
        {
            goto laravel_check;
        }

        // コマンド判定
        $cmd_nm = null;
        $cmds = CommandEnum::cases();
        foreach($cmds as $cmd)
        {
            if($cmd->name() === $parts[0])
            {
                $cmd_nm = $cmd->name();
            }
        }

        // 実行できないコマンドの判定
        if($this->is_laravel === true)
        {
            if($parts[1] === CraftEnum::SETTING->value || $parts[1] === CraftEnum::LOCALE->value)
            {
                $cmd_nm = null;
            }
        }
        else
        {
            if($cmd_nm === CommandEnum::LARAVEL->value)
            {
                $cmd_nm = null;
            }
        }

        // 該当するコマンドがなかった時
        if($cmd_nm === null)
        {
            goto laravel_check;
        }

        // コマンドの実行
        $w_ret = null;
        switch($cmd_nm)
        {
            // クラフトの実行
            case CommandEnum::CRAFT->value:
                $w_ret = $this->craftExecution($parts[1]);
                break;
            // ランタイムコマンドの実行
            case CommandEnum::RUNTIME->value:
                $w_ret = $this->runtimeExecution($parts[1]);
                break;
            // シンプルソケットコマンドの実行
            case CommandEnum::SIMPLE->value:
                $w_ret = $this->simpleExecution($parts[1]);
                break;
            // Laravel操作の実行
            case CommandEnum::LARAVEL->value:
                $w_ret = $this->laravelExecution($parts[1]);
                break;
            default:
                goto laravel_check;
                break;
        }
        if($w_ret === false)
        {
            return false;
        }

        return true;

laravel_check:
        if($this->is_laravel === true)
        {
            require_once($this->path.'/artisan');
            return true;
        }

        //--------------------------------------------------------------------------
        // MainClassの実行
        //--------------------------------------------------------------------------

        $this->setMainClassList();

        foreach($this->consoles as $console)
        {
            // 識別子の一致確認
            $w_ret = $console->getIdentifer();
            if($w_ret === $this->params[1])
            {
                $this->console = $console;
            }
            $console = null;
        }

        // MainClass実行
        if($this->console !== null)
        {
            $msg = $this->console->getErrorMessage();
            if($msg !== null)
            {
                $msg->display(null, $this->lang);
                return false;
            }
            $this->console->exec();
            return true;
        }

        FailureEnum::COMMAND_FAIL->display(null, $this->lang);
        return false;
    }

    /**
     * クラフトコマンドの実行
     * 
     * @param string $p_typ クラフトタイプ
     * @param string $p_sep ディレクトリセパレータ
     * @return bool true（成功） or false（失敗）
     */
    private function craftExecution(string $p_typ, string $p_sep = DIRECTORY_SEPARATOR)
    {
        // クラス名引数の存在チェック
        if(count($this->params) < 3)
        {
            FailureEnum::NO_CLASS_NAME->display(null, $this->lang);
            return false;
        }

        // 一致するEnum値を取得
        $craft_enum = null;
        $typs = CraftEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $p_typ)
            {
                $craft_enum = $typ;
                break;
            }
        }
        if($craft_enum === null)
        {
            FailureEnum::COMMAND_FAIL->display(null, $this->lang);
            return false;
        }
        $this->craft_enum = $craft_enum;

        // パスの生成
        $dir = $this->craft_enum->directory();
        $full_path = $this->path.$p_sep.'app'.$p_sep.$dir;
        if($this->craft_enum === CraftEnum::SETTING)
        {
            $full_path = $this->path.$p_sep.$dir;
        }
        else
        if($this->craft_enum === CraftEnum::LOCALE)
        {
            $full_path = $this->path.$p_sep.$dir.$p_sep.$this->lang;
        }

        // ファイル存在チェック
        $create_file = $full_path.$p_sep.$this->params[2].'.php';
        if(file_exists($create_file))
        {
            if($this->craft_enum === CraftEnum::SETTING || $this->craft_enum === CraftEnum::LOCALE)
            {
                FailureEnum::EXISTING_FILE->display($this->params[2], $this->lang);
            }
            else
            {
                FailureEnum::EXISTING_CLASS->display($this->params[2], $this->lang);
            }
            return false;
        }

        // ディレクトリ生成
        if($this->craft_enum === CraftEnum::LOCALE)
        {
            $locale_path = $this->path.$p_sep.$dir;
            if(is_dir($locale_path) === false)
            {
                mkdir($locale_path);
            }
        }
        if(is_dir($full_path) === false)
        {
            mkdir($full_path);
        }

        // テンプレートのパスを取得
        $class = $this->craft_enum->class();
        $template_path = $this->path.$p_sep.'vendor'.$p_sep.'socket-manager'.$p_sep.'library'.$p_sep.'src'.$p_sep.'FrameWork'.$p_sep.'Template'.$p_sep.$dir.$p_sep;

        // ファイル作成
        $file_data = file_get_contents($template_path.$class.'.php');
        $file_data = str_replace($class, $this->params[2], $file_data);
        if($this->craft_enum->name === 'MAIN')
        {
            // 識別子の変換
            $app_name = strtolower($this->params[2][0]);
            $cnt = strlen($this->params[2]);
            for($i = 1; $i < $cnt; $i++)
            {
                if(ctype_upper($this->params[2][$i]))
                {
                    $app_name .= '-'.strtolower($this->params[2][$i]);
                }
                else
                {
                    $app_name .= $this->params[2][$i];
                }
            }
            $file_data = str_replace('template-server', $app_name, $file_data);
        }
        else
        if($this->craft_enum->name === 'PROTOCOL')
        {
            // ステータス用のEnum名を変換
            $class = $this->craft_enum->enumStatus();
            $file_data = str_replace($class, $this->params[2].'StatusEnum', $file_data);
        }
        file_put_contents($create_file, $file_data);

        // 成功メッセージ
        $success_enum = null;
        $typs = SuccessEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $this->craft_enum->value)
            {
                $success_enum = $typ;
                break;
            }
        }
        $this->success_enum = $success_enum;
        $this->success_enum->display($this->params[2], $this->lang);

        // Enumファイル作成（キュー名）
        if($this->craft_enum->name === 'PROTOCOL' || $this->craft_enum->name === 'COMMAND')
        {
            // ファイル存在チェック
            $create_file = $full_path.$p_sep.$this->params[2].'QueueEnum.php';
            if(file_exists($create_file))
            {
                FailureEnum::EXISTING_ENUM->display($this->params[2].'QueueEnum', $this->lang);
                return false;
            }

            // ファイル作成
            $class = $this->craft_enum->enumQueue();
            $file_data = file_get_contents($template_path.$class.'.php');
            $file_data = str_replace($class, $this->params[2].'QueueEnum', $file_data);
            file_put_contents($create_file, $file_data);

            // 成功メッセージ
            $success_enum = null;
            $typs = SuccessEnum::cases();
            foreach($typs as $typ)
            {
                if($typ->value === $this->success_enum->value.'_queue_enum')
                {
                    $success_enum = $typ;
                    break;
                }
            }
            $this->success_sub_enum = $success_enum;
            $this->success_sub_enum->display($this->params[2].'QueueEnum');
        }

        // Enumファイル作成（ステータス名）
        if($this->craft_enum->name === 'PROTOCOL' || $this->craft_enum->name === 'COMMAND')
        {
            // ファイル存在チェック
            $create_file = $full_path.$p_sep.$this->params[2].'StatusEnum.php';
            if(file_exists($create_file))
            {
                FailureEnum::EXISTING_ENUM->display($this->params[2].'StatusEnum', $this->lang);
                return false;
            }

            // ファイル作成
            $class = $this->craft_enum->enumStatus();
            $file_data = file_get_contents($template_path.$class.'.php');
            $file_data = str_replace($class, $this->params[2].'StatusEnum', $file_data);
            file_put_contents($create_file, $file_data);

            // 成功メッセージ
            $success_enum = null;
            $typs = SuccessEnum::cases();
            foreach($typs as $typ)
            {
                if($typ->value === $this->success_enum->value.'_status_enum')
                {
                    $success_enum = $typ;
                    break;
                }
            }
            $this->success_sub_enum = $success_enum;
            $this->success_sub_enum->display($this->params[2].'StatusEnum');
        }
        return true;
    }

    /**
     * ランタイムコマンドの実行
     * 
     * @param string $p_typ ランタイムタイプ
     * @param string $p_sep ディレクトリセパレータ
     * @return bool true（成功） or false（失敗）
     */
    private function runtimeExecution(string $p_typ, string $p_sep = DIRECTORY_SEPARATOR)
    {
        // クラス名引数の存在チェック
        if(count($this->params) < 3)
        {
            FailureEnum::NO_CLASS_NAME->display(null, $this->lang);
            return false;
        }

        // 一致するEnum値を取得
        $runtime_enum = null;
        $typs = RuntimeEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $p_typ)
            {
                $runtime_enum = $typ;
                break;
            }
        }
        if($runtime_enum === null)
        {
            FailureEnum::COMMAND_FAIL->display(null, $this->lang);
            return false;
        }
        $this->runtime_enum = $runtime_enum;

        // パスの生成
        $dir = $this->runtime_enum->directory();
        $full_path = $this->path.$p_sep.'app'.$p_sep.$dir;

        // ファイル存在チェック
        $create_file = $full_path.$p_sep.$this->params[2].'.php';
        if(file_exists($create_file))
        {
            FailureEnum::EXISTING_CLASS->display($this->params[2], $this->lang);
            return false;
        }

        // ディレクトリ生成
        if(is_dir($full_path) === false)
        {
            mkdir($full_path);
        }

        // テンプレートのパスを取得
        $class = $this->runtime_enum->class();
        $template_path = $this->path.$p_sep.'vendor'.$p_sep.'socket-manager'.$p_sep.'library'.$p_sep.'src'.$p_sep.'FrameWork'.$p_sep.'Template'.$p_sep.$dir.$p_sep;

        // ファイル作成
        $file_data = file_get_contents($template_path.$class.'.php');
        $file_data = str_replace($class, $this->params[2], $file_data);
        if($this->runtime_enum->name === 'MAIN')
        {
            // 識別子の変換
            $app_name = strtolower($this->params[2][0]);
            $cnt = strlen($this->params[2]);
            for($i = 1; $i < $cnt; $i++)
            {
                if(ctype_upper($this->params[2][$i]))
                {
                    $app_name .= '-'.strtolower($this->params[2][$i]);
                }
                else
                {
                    $app_name .= $this->params[2][$i];
                }
            }
            $file_data = str_replace('template-application', $app_name, $file_data);
        }
        else
        if($this->runtime_enum->name === 'UNITS')
        {
            // ステータス用のEnum名を変換
            $class = $this->runtime_enum->enumStatus();
            $file_data = str_replace($class, $this->params[2].'StatusEnum', $file_data);

            // キュー用のEnum名を変換
            $class = $this->runtime_enum->enumQueue();
            $file_data = str_replace($class, $this->params[2].'QueueEnum', $file_data);
        }
        file_put_contents($create_file, $file_data);

        // 成功メッセージ
        $success_enum = null;
        $typs = SuccessEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $this->runtime_enum->value)
            {
                $success_enum = $typ;
                break;
            }
        }
        $this->success_enum = $success_enum;
        $this->success_enum->display($this->params[2], $this->lang);

        // Enumファイル作成（キュー名）
        if($this->runtime_enum->name === 'UNITS')
        {
            // ファイル存在チェック
            $create_file = $full_path.$p_sep.$this->params[2].'QueueEnum.php';
            if(file_exists($create_file))
            {
                FailureEnum::EXISTING_ENUM->display($this->params[2].'QueueEnum', $this->lang);
                return false;
            }

            // ファイル作成
            $class = $this->runtime_enum->enumQueue();
            $file_data = file_get_contents($template_path.$class.'.php');
            $file_data = str_replace($class, $this->params[2].'QueueEnum', $file_data);
            file_put_contents($create_file, $file_data);

            // 成功メッセージ
            $success_enum = null;
            $typs = SuccessEnum::cases();
            foreach($typs as $typ)
            {
                if($typ->value === $this->success_enum->value.'_queue_enum')
                {
                    $success_enum = $typ;
                    break;
                }
            }
            $this->success_sub_enum = $success_enum;
            $this->success_sub_enum->display($this->params[2].'QueueEnum');
        }

        // Enumファイル作成（ステータス名）
        if($this->runtime_enum->name === 'UNITS')
        {
            // ファイル存在チェック
            $create_file = $full_path.$p_sep.$this->params[2].'StatusEnum.php';
            if(file_exists($create_file))
            {
                FailureEnum::EXISTING_ENUM->display($this->params[2].'StatusEnum', $this->lang);
                return false;
            }

            // ファイル作成
            $class = $this->runtime_enum->enumStatus();
            $file_data = file_get_contents($template_path.$class.'.php');
            $file_data = str_replace($class, $this->params[2].'StatusEnum', $file_data);
            file_put_contents($create_file, $file_data);

            // 成功メッセージ
            $success_enum = null;
            $typs = SuccessEnum::cases();
            foreach($typs as $typ)
            {
                if($typ->value === $this->success_enum->value.'_status_enum')
                {
                    $success_enum = $typ;
                    break;
                }
            }
            $this->success_sub_enum = $success_enum;
            $this->success_sub_enum->display($this->params[2].'StatusEnum');
        }
        return true;
    }

    /**
     * シンプルソケットコマンドの実行
     * 
     * @param string $p_typ シンプルソケットタイプ
     * @param string $p_sep ディレクトリセパレータ
     * @return bool true（成功） or false（失敗）
     */
    private function simpleExecution(string $p_typ, string $p_sep = DIRECTORY_SEPARATOR)
    {
        // クラス名引数の存在チェック
        if(count($this->params) < 3)
        {
            FailureEnum::NO_CLASS_NAME->display(null, $this->lang);
            return false;
        }

        // 一致するEnum値を取得
        $simple_enum = null;
        $typs = SimpleEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $p_typ)
            {
                $simple_enum = $typ;
                break;
            }
        }
        if($simple_enum === null)
        {
            FailureEnum::COMMAND_FAIL->display(null, $this->lang);
            return false;
        }
        $this->simple_enum = $simple_enum;

        // パスの生成
        $dir = $this->simple_enum->directory();
        $full_path = $this->path.$p_sep.'app'.$p_sep.$dir;

        // ファイル存在チェック
        $create_file = $full_path.$p_sep.$this->params[2].'.php';
        if(file_exists($create_file))
        {
            FailureEnum::EXISTING_CLASS->display($this->params[2], $this->lang);
            return false;
        }

        // ディレクトリ生成
        if(is_dir($full_path) === false)
        {
            mkdir($full_path);
        }

        // テンプレートのパスを取得
        $class = $this->simple_enum->class();
        $template_path = $this->path.$p_sep.'vendor'.$p_sep.'socket-manager'.$p_sep.'library'.$p_sep.'src'.$p_sep.'FrameWork'.$p_sep.'Template'.$p_sep.$dir.$p_sep;

        // ファイル作成
        $file_data = file_get_contents($template_path.$class.'.php');
        $file_data = str_replace($class, $this->params[2], $file_data);
        if($p_typ === SimpleEnum::TCP_SERVER->value)
        {
            $file_data = str_replace('ISimpleSocket', 'ISimpleSocketTcpServer', $file_data);
            $file_data = str_replace('SimpleSocketTypeEnum::', 'SimpleSocketTypeEnum::TCP_SERVER', $file_data);
        }
        else
        if($p_typ === SimpleEnum::TCP_CLIENT->value)
        {
            $file_data = str_replace('ISimpleSocket', 'ISimpleSocketTcpClient', $file_data);
            $file_data = str_replace('SimpleSocketTypeEnum::', 'SimpleSocketTypeEnum::TCP_CLIENT', $file_data);
        }
        else
        if($p_typ === SimpleEnum::UDP->value)
        {
            $file_data = str_replace('ISimpleSocket', 'ISimpleSocketUdp', $file_data);
            $file_data = str_replace('SimpleSocketTypeEnum::', 'SimpleSocketTypeEnum::UDP', $file_data);
        }

        // 識別子の変換
        $app_name = strtolower($this->params[2][0]);
        $cnt = strlen($this->params[2]);
        for($i = 1; $i < $cnt; $i++)
        {
            if(ctype_upper($this->params[2][$i]))
            {
                $app_name .= '-'.strtolower($this->params[2][$i]);
            }
            else
            {
                $app_name .= $this->params[2][$i];
            }
        }
        $file_data = str_replace('template-application', $app_name, $file_data);

        file_put_contents($create_file, $file_data);

        // 成功メッセージ
        $success_enum = null;
        $typs = SuccessEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $this->simple_enum->value)
            {
                $success_enum = $typ;
                break;
            }
        }
        $this->success_enum = $success_enum;
        $this->success_enum->display($this->params[2], $this->lang);

        return true;
    }

    /**
     * Laravel操作コマンドの実行
     * 
     * @param string $p_typ Laravelコマンドタイプ
     * @param string $p_sep ディレクトリセパレータ
     * @return bool true（成功） or false（失敗）
     */
    private function laravelExecution(string $p_typ, string $p_sep = DIRECTORY_SEPARATOR)
    {
        // クラス名引数の存在チェック
        if(count($this->params) < 3)
        {
            FailureEnum::NO_CLASS_NAME->display(null, $this->lang);
            return false;
        }

        // 一致するEnum値を取得
        $laravel_enum = null;
        $typs = LaravelEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $p_typ)
            {
                $laravel_enum = $typ;
                break;
            }
        }
        if($laravel_enum === null)
        {
            FailureEnum::COMMAND_FAIL->display(null, $this->lang);
            return false;
        }
        $this->laravel_enum = $laravel_enum;

        // 取得元ディレクトリのフルパス
        $dir = $this->laravel_enum->srcDirectory();
        $src_path = $this->path.$p_sep.'app'.$p_sep.$dir;

        // 取得元クラスの存在チェック
        $src_file = $src_path.$p_sep.$this->params[2].'.php';
        if(!file_exists($src_file))
        {
            $this->craftExecution('main');
        }

        // 出力先ディレクトリの作成
        $dirs = $this->laravel_enum->dstDirectory();
        $dst_path = $this->path.$p_sep.'app';
        foreach($dirs as $dir)
        {
            // 階層ごとのパス
            $dst_path = $dst_path.$p_sep.$dir;

            // ディレクトリ作成
            if(is_dir($dst_path) === false)
            {
                mkdir($dst_path);
            }
        }

        // 出力先クラスの存在チェック
        $dst_file = $dst_path.$p_sep.$this->params[2].'.php';
        if(file_exists($dst_file))
        {
            FailureEnum::EXISTING_CLASS->display($this->params[2], $this->lang);
            return false;
        }

        // ファイル作成
        $file_data = file_get_contents($src_file);
        $file_data = preg_replace('/(namespace[ ]+)App\\\MainClass/', '$1App\\\Console\\\Commands', $file_data);
        $file_data = preg_replace('/(use[ ]+)SocketManager\\\Library\\\FrameWork\\\Console/', '$1Illuminate\\\Console\\\Command', $file_data);
        $file_data = preg_replace('/string[ ]+\$identifer/', '\$signature', $file_data);
        $file_data = preg_replace('/string[ ]+\$description/', '\$description', $file_data);
        $file_data = str_replace('$this->identifer', '$this->signature', $file_data);
        $file_data = preg_replace('/(extends[ ]+)Console/', '$1Command', $file_data);
        $file_data = str_replace('$this->getParameter', '$this->argument', $file_data);
        $file_data = str_replace('exec', 'handle', $file_data);
        file_put_contents($dst_file, $file_data);

        // 成功メッセージ
        $success_enum = null;
        $typs = SuccessEnum::cases();
        foreach($typs as $typ)
        {
            if($typ->value === $this->laravel_enum->alias())
            {
                $success_enum = $typ;
                break;
            }
        }
        $this->success_enum = $success_enum;
        $this->success_enum->display($this->params[2], $this->lang);

        return true;
    }

    /**
     * メインクラスをリストへ登録
     * 
     * @param string $p_sep ディレクトリセパレータ
     */
    private function setMainClassList(string $p_sep = DIRECTORY_SEPARATOR)
    {
        // メインクラスのリストを取得
        $main_class = CraftEnum::MAIN->directory();
        $file_list = glob("{$this->path}{$p_sep}app{$p_sep}{$main_class}{$p_sep}*.php");

        // メインクラス登録のループ
        foreach($file_list as $file)
        {
            // クラス名の取得
            $pattern = "@\\{$p_sep}([^\\{$p_sep}]+).php$@";
            preg_match($pattern, $file, $matches);
            $class = "App\\{$main_class}\\{$matches[1]}";

            // インスタンスリストへ追加
            $this->consoles[] = new $class($this->params);
        }
    }

    /**
     * 設定ファイルの読み込み
     * 
     * @param bool $p_is_laravel Laravelフラグ true（Laravel） or false（Laravel以外）
     * @param string $p_sep ディレクトリセパレータ
     */
    private function loadSetting(bool $p_is_laravel, string $p_sep = DIRECTORY_SEPARATOR)
    {
        if($p_is_laravel === true)
        {
            $full_path = "{$this->path}{$p_sep}config{$p_sep}app.php";
            $file_data = file_get_contents($full_path);
            $file_data = str_replace('<?php', '', $file_data);
            static::$settings['app'] = eval($file_data);
            return;
        }

        $file_path = "{$this->path}{$p_sep}".CraftEnum::SETTING->directory()."{$p_sep}";
        $file_list = glob("{$file_path}*.php");

        // ファイルリストでループ
        foreach($file_list as $file)
        {
            // ファイル名の取得
            $pattern = "@\\{$p_sep}([^\\{$p_sep}]+).php$@";
            preg_match($pattern, $file, $matches);
            static::$settings[$matches[1]] = require("{$file_path}{$matches[1]}.php");
        }
    }

    /**
     * メッセージファイルの読み込み
     * 
     * @param bool $p_is_laravel Laravelフラグ true（Laravel） or false（Laravel以外）
     * @param string $p_sep ディレクトリセパレータ
     */
    private function loadLocale(bool $p_is_laravel, string $p_sep = DIRECTORY_SEPARATOR)
    {
        if($p_is_laravel === true)
        {
            return;
        }

        $file_path = "{$this->path}{$p_sep}".CraftEnum::LOCALE->directory()."{$p_sep}{$this->lang}{$p_sep}";
        $file_list = glob("{$file_path}*.php");

        // ファイルリストでループ
        foreach($file_list as $file)
        {
            // ファイル名の取得
            $pattern = "@\\{$p_sep}([^\\{$p_sep}]+).php$@";
            preg_match($pattern, $file, $matches);
            static::$locales[$matches[1]] = require("{$file_path}{$matches[1]}.php");
        }
    }

    /**
     * ヘルパーの読み込み
     * 
     * @param bool $p_is_laravel Laravelフラグ true（Laravel） or false（Laravel以外）
     */
    private function loadHelper(bool $p_is_laravel)
    {
        if($p_is_laravel === true)
        {
            return;
        }
        require_once(__DIR__.DIRECTORY_SEPARATOR.'linkage_helpers.php');
    }

    /**
     * 設定値の取得
     * 
     * @param string $p_key 設定値のキー
     * @param mixed $p_default 設定値がなかった時のデフォルト
     * @return mixed 設定値
     */
    public static function getConfig(string $p_key, $p_default = null)
    {
        $keys = explode('.', $p_key);
        $ret = static::$settings;
        foreach($keys as $key)
        {
            if(!isset($ret[$key]))
            {
                return $p_default;
            }
            $ret = $ret[$key];
        }
        return $ret;
    }

    /**
     * メッセージの取得
     * 
     * @param string $p_key メッセージのキー
     * @param array $p_placeholder プレースホルダ
     * @return string メッセージ
     */
    public static function getMessage(string $p_key, array $p_placeholder = [])
    {
        $keys = explode('.', $p_key);
        $ret = static::$locales;
        foreach($keys as $key)
        {
            if(!isset($ret[$key]))
            {
                return $p_key;
            }
            foreach($p_placeholder as $name => $val)
            {
                $ret[$key] = str_replace(':'.$name, $val, $ret[$key]);
            }
            $ret = $ret[$key];
        }
        return $ret;
    }
}
