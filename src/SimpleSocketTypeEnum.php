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
     * @var string TCP Server タイプ
     */
    case TCP_SERVER = 'tcp_server';

    /**
     * @var string TCP Client タイプ
     */
    case TCP_CLIENT = 'tcp_client';

    /**
     * @var string UDP通信タイプ
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
            self::TCP_SERVER => true,
            self::TCP_CLIENT => false,
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
            self::TCP_SERVER => false,
            self::TCP_CLIENT => false,
            self::UDP => true,
            default => null
        };
    }
};
