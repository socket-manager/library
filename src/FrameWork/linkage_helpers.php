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
