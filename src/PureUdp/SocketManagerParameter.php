<?php
/**
 * ライブラリファイル
 * 
 * 処理対象の接続IDをラッピングしたUNITパラメータ用ライブラリのファイル
 */

namespace SocketManager\Library\PureUdp;

use SocketManager\Library\UnitKindEnum;
use SocketManager\Library\CoreSocketManager;
use SocketManager\Library\PureUdpManager;
use SocketManager\Library\UnitParameter;


/**
 * UNITパラメータの基底クラス
 * 
 * 周期ドリブンマネージャーへ引き渡すパラメータの管理と制御を行う
 *
 * 以下は継承プロパティ  
 * @property PureUdpManager $manager - CoreSocketManagerの継承クラスのインスタンス
 * @property ?UnitKindEnum $kind - 周期ドリブンマネージャーの種別
 * @property ?string $cid - 接続ID
 * @property string $lang - 言語設定
 */
class SocketManagerParameter extends UnitParameter
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * @var ?BufferingProtocol $protocol プロトコルUNITパラメータ
     */
    private ?BufferingProtocol $protocol = null;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     */
    public function __construct()
    {
    }


    //--------------------------------------------------------------------------
    // その他
    //--------------------------------------------------------------------------

    /**
     * ローカルアドレスの取得
     * 
     * @param ?string &$p_host リモートホスト
     * @param ?int &$p_port リモートポート
     * @return bool true（成功） or false（失敗）
     */
    final public function getLocalAddr(?string &$p_host, ?int &$p_port): bool
    {
        if($this->manager->getAwaitSocket() !== null)
        {
            $p_host = $this->manager->getAwaitHost();
            $p_port = $this->manager->getAwaitPort();
            return true;
        }
        return $this->manager->getSockName($this->cid, $p_host, $p_port);
    }

    /**
     * プロトコルインターフェースの設定
     * 
     * @param IProtocolParameter $p_protocol プロトコルインターフェース
     */
    final public function setProtocolInterface(IProtocolParameter $p_protocol)
    {
        $this->protocol = $p_protocol;
    }

    /**
     * ソケットマネージャーの設定
     * 
     * @param CoreSocketManager $p_mng ソケットマネージャーのインスタンス
     */
    final public function setSocketManager(CoreSocketManager $p_mng)
    {
        $this->manager = $p_mng;
        $this->protocol->setSocketManagerParameter($this);
        return;
    }

    /**
     * IProtocolParameterインタフェースの取得
     * 
     * @return IProtocolParameter
     */
    final public function protocol(): IProtocolParameter
    {
        if($this->kind === UnitKindEnum::PROTOCOL && $this->protocol !== null)
        {
            return $this->protocol;
        }
        throw new UnitException(
            UnitExceptionEnum::ECODE_METHOD_CALL_FAIL->message(),
            UnitExceptionEnum::ECODE_METHOD_CALL_FAIL->value,
            $this
        );
    }
}
