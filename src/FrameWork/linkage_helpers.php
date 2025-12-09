<?php

use SocketManager\Library\FrameWork\Worker;

if(!function_exists('config'))
{
    /**
     * 設定値の取得
     * 
     * @param string $p_key 設定値のキー
     * @param mixed $p_default 設定値がなかった時のデフォルト
     * @return mixed 設定値
     */
    function config(string $p_key, $p_default = null)
    {
        $ret = Worker::getConfig($p_key, $p_default);
        return $ret;
    }
}

if(!function_exists('__'))
{
    /**
     * メッセージの取得
     * 
     * @param string $p_key メッセージのキー
     * @param array $p_placeholder プレースホルダ
     * @return string メッセージ
     */
    function __(string $p_key, array $p_placeholder = [])
    {
        $ret = Worker::getMessage($p_key, $p_placeholder);
        return $ret;
    }
}

if(!function_exists('get_mime_type'))
{
    /**
     * ファイルのMIMEタイプの取得
     * 
     * @param string $p_path ファイルパス
     * @return string MIMEタイプ
     */
    function get_mime_type(string $p_path): string
    {
        $mime_types = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm'
        ];

        // MIMEタイプの取得
        $ext = strtolower(pathinfo($p_path, PATHINFO_EXTENSION));
        $mime_type = $mime_types[$ext] ?? 'application/octet-stream';

        return $mime_type;
    }
}
