<?php
/**
 * ライブラリファイル
 * 
 * カスタムスキャフォールクラスのファイル
 */

namespace SocketManager\Library\FrameWork;

use RuntimeException;

/**
 * カスタムスキャフォールクラス
 * 
 * Usageにcustomカテゴリを追加する
 */
class CustomScaffolder
{
    /**
     * Usageの生成
     *
     * @param string $p_base_dir コマンド定義の基底ディレクトリ
     */
    public function createUsage(string $p_base_dir): string
    {
        $usage = '';

        $commands = $this->load($p_base_dir);
        if($commands === [])
        {
            return $usage;
        }

        $usage .=
<<<EOD
 \033[33mcustom\033[m\n
EOD;
        foreach($commands as $name => $command)
        {
            $usage .=
<<<EOD
  \033[32mcustom:{$command['config']['name']}\033[m <カスタム名> => {$command['config']['description']}\n
EOD;
        }

        return $usage;
    }

    /**
     * カスタムファイルの生成
     *
     * @param string $p_base_dir コマンド定義の基底ディレクトリ
     * @param string $p_typ カスタムコマンドタイプ
     * @param string $p_custom_name カスタム名
     * @param string $p_lang 言語
     * @return bool true（成功） or false（失敗）
     */
    public function createCustom(string $p_base_dir, string $p_typ, string $p_custom_name, string $p_lang): bool
    {
        $commands = $this->load($p_base_dir);
        if($commands === [] || !isset($commands[$p_typ]))
        {
            FailureEnum::COMMAND_FAIL->display(null, $p_lang);
            return false;
        }

        $ret = $this->run($commands[$p_typ], $p_custom_name, $p_lang);

        return $ret;
    }

    /**
     * コマンド一覧を読み込む
     *
     * @param string $p_base_dir コマンド定義の基底ディレクトリ
     */
    private function load(string $p_base_dir): array
    {
        $commands = [];

        foreach(glob($p_base_dir . '/*/command.php') as $file)
        {
            $config = require $file;

            if(empty($config['name']))
            {
                continue;
            }

            $commands[$config['name']] = [
                'config'   => $config,
                'dir'      => dirname($file),
                'template' => $config['template'] ?? 'template.php.tpl',
                'output'   => $config['output']   ?? null,
            ];
        }

        return $commands;
    }

    /**
     * コマンド実行
     * 
     * @param array $p_command_info コマンド情報
     * @param string $p_custom_name カスタム名
     * @param string $p_lang 言語
     */
    private function run(array $p_command_info, string $p_custom_name, string $p_lang): bool
    {
        $config       = $p_command_info['config'];
        $command_dir  = $p_command_info['dir'];
        $template_rel = $p_command_info['template'];
        $output_tpl   = $p_command_info['output'];

        if($output_tpl === null)
        {
            FailureEnum::OUTPUT_NO_DEFINITION->display(null, $p_lang);
            return false;
        }

        // CLI 引数の name
        $vars = ['name' => $p_custom_name];

        // params.php を読み込む
        $params_file = $command_dir . '/params.php';
        if(is_file($params_file))
        {
            $params = require $params_file;
            if(is_array($params))
            {
                $vars = array_merge($params, $vars);
            }
        }

        // テンプレート読み込み
        $template_path = $command_dir . '/' . $template_rel;
        if(!is_file($template_path))
        {
            FailureEnum::NO_TEMPLATE->display($template_path, $p_lang);
            return false;
        }
        $template_content = file_get_contents($template_path);

        // 出力パスをレンダリング
        $output_path = $this->render($output_tpl, $vars);

        // 既存ファイルチェック
        if(file_exists($output_path))
        {
            FailureEnum::EXISTING_FILE->display($p_custom_name, $p_lang);
            return false;
        }

        // ディレクトリ作成
        $output_dir = dirname($output_path);
        if(!is_dir($output_dir))
        {
            mkdir($output_dir, 0777, true);
        }

        // 中身をレンダリング
        $rendered = $this->render($template_content, $vars);

        // 書き込み
        file_put_contents($output_path, $rendered);

        // 成功メッセージ
        SuccessEnum::FILE->display($p_custom_name, $p_lang);

        return true;
    }

    /**
     * `<%= key %>` を置換する内部メソッド
     * 
     * @param string $p_template
     * @param array $p_vars
     */
    private function render(string $p_template, array $p_vars): string
    {
        return preg_replace_callback('/<%=\s*(\w+)\s*%>/', function ($matches) use ($p_vars) {
            $key = $matches[1];

            if(!array_key_exists($key, $p_vars))
            {
                return '';
            }

            return $p_vars[$key] ?? '';
        }, $p_template);
    }
}
