<?php
/**
 * Exceptionクラスのファイル
 * 
 * UNIT用の例外
 */
namespace SocketManager\Library;

use Exception;


/**
 * Exceptionクラス
 * 
 * UNIT用の例外
 */
class UnitException extends Exception
{
    //--------------------------------------------------------------------------
    // 定数
    //--------------------------------------------------------------------------

    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    // UNITパラメータ
    private ?IUnitParameter $param;


    //--------------------------------------------------------------------------
    // メソッド
    //--------------------------------------------------------------------------

    /**
     * コンストラクタ
     * 
     * @param string $p_msg メッセージ
     * @param int $p_cod コード
     * @param ?IUnitParameter $p_param UNITパラメータ
     */
    public function __construct(string $p_msg = '', int $p_cod = 0, ?IUnitParameter $p_param = null)
    {
        parent::__construct($p_msg, $p_cod);
        $this->param = $p_param;
    }

    /**
     * 例外識別子の取得
     * 
     * @return string 例外識別子
     */
    public function getIdentifier(): string
    {
        return 'UNIT:Exception';
    }

    /**
     * 例外メッセージ配列の取得
     * 
     * @return array 例外メッセージ配列
     */
    public function getArrayMessage(): array
    {
        $que = null;
        $sta = null;
        if($this->param !== null)
        {
            $que = $this->param->getQueueName();
            $sta = $this->param->getStatusName();
        }
        $ret =
        [
            'cod' => $this->getCode(),
            'msg' => $this->getMessage(),
            'que' => $que,
            'sta' => $sta,
            'trace' => $this->getTraceAsString()
        ];

        return $ret;
    }
}
