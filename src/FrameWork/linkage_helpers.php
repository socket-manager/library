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
