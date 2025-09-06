<?php
/**
 * シンプルソケットタイプのEnumファイル
 * 
 * ライブラリ用
 */

namespace SocketManager\Library;


/**
 * シンプルソケットタイプのEnum定義
 * 
 */
enum SimpleSocketTypeEnum: string
{
    /**
     * @var UDP通信タイプ
     */
    case UDP = 'udp';

    /**
     * 待受フラグを返す
     * 
     * @return ?bool true（待受） or false（送信）
     */
    public function isAwait(): ?bool
    {
        return match($this)
        {
            default => null
        };
    }

    /**
     * UDPフラグを返す
     * 
     * @return ?bool true（UDP） or false（TCP）
     */
    public function isUdp(): ?bool
    {
        return match($this)
        {
            self::UDP => true,
            default => null
        };
    }
};
