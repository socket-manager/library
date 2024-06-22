<?php

use SocketManager\Library\FrameWork\Worker;

if(!function_exists('config'))
{
    /**
     * 設定値の取得
     * 
     * @param string $p_key 設定値のキー
     * @param mixed $default 設定値がなかった時のデフォルト
     * @return mixed 設定値
     */
    function config(string $p_key, $default = null)
    {
        $keys = explode('.', $p_key);
        $ret = Worker::$settings;
        foreach($keys as $key)
        {
            if(!isset($ret[$key]))
            {
                return $default;
            }
            $ret = $ret[$key];
        }
        return $ret;
    }
}
