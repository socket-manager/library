<?php
/**
 * ライブラリファイル
 * 
 * I/O ドライバ抽象化クラス関連ファイル
 */

namespace SocketManager\Library\FrameWork;


/**
 * I/O ドライバ抽象化クラスのインターフェース
 * 
 */
interface IIoDriver
{
    public function register($p_sock, bool $p_is_udp, bool $p_is_client): int;
    public function registerListen($p_sock): int;
    public function registerUdpListen($p_sock): int;
    public function unregister($p_handle): void;
    public function waitEvents(int $p_timeout = 0): array|false;
    public function getSockName($p_handle, string &$p_ip_buf, int &$p_port);
}
