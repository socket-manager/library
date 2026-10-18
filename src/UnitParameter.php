<?php
/**
 * ライブラリファイル
 * 
 * 処理対象の接続IDをラッピングしたUNITパラメータ用ライブラリのファイル
 */

namespace SocketManager\Library;

use SocketManager\Library\PureUdp\EngineQueueEnum;

/**
 * UNITパラメータの基底クラス
 * 
 * 周期ドリブンマネージャーへ引き渡すパラメータの管理と制御を行う
 */
class UnitParameter implements IUnitParameter
{
    //--------------------------------------------------------------------------
    // プロパティ
    //--------------------------------------------------------------------------

    /**
     * ソケットマネージャー
     */
    protected ?CoreSocketManager $manager = null;

    /**
     * 周期ドリブンマネージャーの種別
     */
    protected ?UnitKindEnum $kind = null;

    /**
     * 接続ID
     */
    protected ?string $cid = null;

    /**
     * 言語設定
     * 
     * デフォルト：'ja'
     */
    protected string $lang = 'ja';

    /**
     * シンプルソケットインスタンス
     * 
     */
    public ISimpleSocketUdp|ISimpleSocketTcpServer|ISimpleSocketTcpClient|null $simple_socket = null;


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
    // インタフェース（IUnitParameter）実装
    //--------------------------------------------------------------------------

    /**
     * キュー名の取得
     * 
     * @return ?string キュー名 or null（なし）
     */
    final public function getQueueName(): ?string
    {
        return $this->manager->getQueueName($this->kind, $this->cid);
    }

    /**
     * ステータス名の取得
     * 
     * @return ?string ステータス名 or null（なし）
     */
    final public function getStatusName(): ?string
    {
        return $this->manager->getStatusName($this->kind, $this->cid);
    }

    /**
     * ステータス名の設定
     * 
     * @param ?string ステータス名 or null（なし）
     * @return bool true（成功） or false（失敗）
     */
    final public function setStatusName(?string $p_name)
    {
        return $this->manager->setStatusName($this->kind, $this->cid, $p_name);
    }


    //--------------------------------------------------------------------------
    // getter 関連
    //--------------------------------------------------------------------------

    /**
     * 待ち受けホスト名を取得
     * 
     * @return ?string 待ち受けホスト名
     */
    final public function getAwaitHost(): ?string
    {
        return $this->manager->getAwaitHost();
    }

    /**
     * 待ち受けポート番号を取得
     * 
     * @return ?int 待ち受けポート番号
     */
    final public function getAwaitPort(): ?int
    {
        return $this->manager->getAwaitPort();
    }

    /**
     * 言語コードの取得
     * 
     * @return string 言語コード
     */
    final public function getLanguage(): string
    {
        return $this->lang;
    }


    //--------------------------------------------------------------------------
    // setter 関連
    //--------------------------------------------------------------------------

    /**
     * 言語コードの設定
     * 
     * @param string 言語コード
     */
    final public function setLanguage(string $p_lang)
    {
        $this->lang = $p_lang;
        return;
    }


    //--------------------------------------------------------------------------
    // ディスクリプタ操作関連
    //--------------------------------------------------------------------------

    /**
     * 強制ディスパッチャーフラグの取得
     * 
     * @return bool true（強制ディスパッチ） or false（ディスパッチなし）
     */
    final public function getForcedDispatcher()
    {
        $w_ret = $this->manager->getProperties($this->cid, ['forced_dispatcher']);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->value,
                $this
            );
        }

        return $w_ret['forced_dispatcher'];
    }

    /**
     * 強制ディスパッチャーフラグの設定
     * 
     * @param bool true（強制ディスパッチ） or false（ディスパッチなし）
     */
    final public function setForcedDispatcher(bool $p_flag)
    {
        if($this->kind !== UnitKindEnum::COMMAND)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->value,
                $this
            );
        }

        $w_ret = $this->manager->setProperties($this->cid, ['forced_dispatcher' => $p_flag]);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->value,
                $this
            );
        }

        return;
    }

    /**
     * テンポラリバッファの取得
     * 
     * @param array $p_prop プロパティ（キー）のリスト
     * @param ?string $p_cid 接続ID
     * @return mixed バッファデータ or null（空）
     */
    final public function getTempBuff(array $p_prop, ?string $p_cid = null)
    {
        $cid = $this->cid;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }

        // ユーザープロパティの取得
        $w_ret = $this->manager->getUserProperties($cid, $p_prop);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_USER_PROPERTY_GET_FAIL->message(),
                UnitExceptionEnum::ECODE_USER_PROPERTY_GET_FAIL->value,
                $this
            );
        }

        return $w_ret;
    }

    /**
     * テンポラリバッファの設定
     * 
     * @param array $p_prop プロパティのリスト
     * @param ?string $p_cid 接続ID
     */
    final public function setTempBuff(array $p_prop, ?string $p_cid = null)
    {
        $cid = $this->cid;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }

        // ユーザープロパティの設定
        $w_ret = $this->manager->setUserProperties($cid, $p_prop);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_USER_PROPERTY_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_USER_PROPERTY_SET_FAIL->value,
                $this
            );
        }

        return;
    }

    /**
     * 全接続IDを取得
     * 
     * @param ?string $p_cid 除外する接続ID
     * @return array 接続IDのリスト
     */
    final public function getConnectionIdAll(?string $p_cid = null): array
    {
        return $this->manager->getConnectionIdAll($p_cid);
    }


    //--------------------------------------------------------------------------
    // ステートマシン操作関連
    //--------------------------------------------------------------------------

    /**
     * キューの実行状況を検査
     * 
     * @param UnitKindEnum $p_kind UNIT種別
     * @param string $p_que キュー名
     * @return bool true（実行中） or false（停止中）
     */
    final public function isExecutedQueue(UnitKindEnum $p_kind, string $p_que): bool
    {
        return $this->manager->isExecutedQueue($p_kind, $this->cid, $p_que);
    }

    /**
     * アライブチェックを行う
     * 
     * 任意のタイミングで一時的に実行したい時に利用する
     * 
     * ※既に設定済みの場合は何もせずに終了する  
     * ※実行後スローブレイクすることに注意
     * 
     * @param int $p_tout — アライブチェックタイムアウト（秒）
     */
    final public function aliveCheck(int $p_tout)
    {
        $w_ret = $this->manager->aliveCheck(UnitKindEnum::PROTOCOL, $this->cid, $p_tout);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_ALIVE_CHECK_FAIL->message(),
                UnitExceptionEnum::ECODE_ALIVE_CHECK_FAIL->value,
                $this
            );
        }

        if($this->kind === UnitKindEnum::PROTOCOL)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_THROW_BREAK->message(),
                UnitExceptionEnum::ECODE_THROW_BREAK->value,
                $this
            );
        }
    }

    /**
     * キューの切り替え
     * 
     * @param ?string $p_name キュー名（null時はアイドリングに戻す）
     */
    final public function changeQueue(?string $p_name)
    {
        $queue_name = null;
        $status_name = null;
        if($p_name !== null)
        {
            $queue_name = $p_name;
            $status_name = StatusEnum::START->value;
        }
        $names =
        [
            'queue_name' => $queue_name,
            'status_name' => $status_name
        ];
        $w_ret = $this->manager->setProperties($this->cid, [$this->kind => $names]);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->value,
                $this
            );
        }

        throw new UnitException(
            UnitExceptionEnum::ECODE_THROW_BREAK->message(),
            UnitExceptionEnum::ECODE_THROW_BREAK->value,
            $this
        );

        return;
    }

    /**
     * TICKキューのステータス設定
     * 
     * @return bool true（成功） or false (失敗)
     */
    final public function setTickStatus(?string $p_sta)
    {
        return $this->manager->setQueueAndStatus(UnitKindEnum::ENGINE, $this->cid, EngineQueueEnum::TICK->value, $p_sta);
    }

    /**
     * UNIT種別制限
     * 
     * @param UnitKindEnum $p_kind UNIT種別
     */
    final public function restrictUnitKind(UnitKindEnum $p_kind): void
    {
        $this->manager->setRestrictUnitKind($p_kind, $this->cid);
    }

    /**
     * UNIT種別制限の解除
     * 
     */
    final public function unrestrictedUnitKind(): void
    {
        $this->manager->setRestrictUnitKind(null, $this->cid);
    }


    //--------------------------------------------------------------------------
    // 送受信バッファ制御
    //--------------------------------------------------------------------------

    /**
     * 処理対象の受信データを取得
     * 
     * @return mixed 受信データ or null（データなし）
     */
    final public function getRecvData()
    {
        // ユーザープロパティの取得
        $ret = $this->manager->getProperties($this->cid, ['recv_buffer']);
        if($ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PICKUP_RECEIVE_DATA_GET_FAIL->message(),
                UnitExceptionEnum::ECODE_PICKUP_RECEIVE_DATA_GET_FAIL->value,
                $this
            );
        }
        else
        if($ret === null)
        {
            return null;
        }

        // ユーザープロパティの設定
        $w_ret = $this->manager->setProperties($cid, ['recv_buffer' => null]);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PICKUP_RECEIVE_DATA_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PICKUP_RECEIVE_DATA_SET_FAIL->value,
                $this
            );
        }

        return $ret['recv_buffer'];
    }

    /**
     * 処理対象の受信データを設定
     * 
     * @param mixed 受信データ or null（データなし）
     * @param ?string $p_cid 設定したい接続ID
     */
    final public function setRecvData($p_dat, ?string $p_cid = null)
    {
        $cid = $this->cid;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }

        // ユーザープロパティの設定
        $w_ret = $this->manager->setProperties($cid, ['recv_buffer' => $p_dat]);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PICKUP_RECEIVE_DATA_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PICKUP_RECEIVE_DATA_SET_FAIL->value,
                $this
            );
        }

        return;
    }

    /**
     * 受信データスタックへ設定
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param mixed $p_data 設定するデータ
     * @param ?bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ
     * 
     * ― null（指定なし）の場合は自動判別（プロトコルUNITでの実行：false、コマンドUNITでの実行：true）
     * 
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     * 
     * @param ?string $p_cid 設定したい接続ID
     */
    final public function setRecvStack($p_data = null, ?bool $p_convert = null, ?string $p_cid = null)
    {
        // 対象の接続IDの取得
        $cid = $this->cid;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }

        // フラグの設定
        $convert = false;
        if($p_convert !== null)
        {
            $convert = $p_convert;
        }
        else
        {
            if($this->kind === UnitKindEnum::PROTOCOL)
            {
                $convert = false;
            }
            else
            {
                $convert = true;
            }
        }

        // 受信データ設定
        $w_ret = $this->manager->setRecvStack($cid, $p_data, $convert);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_RECEIVE_DATA_STACK_FAIL->message(),
                UnitExceptionEnum::ECODE_RECEIVE_DATA_STACK_FAIL->value,
                $this
            );
        }
    }

    /**
     * 送信データスタックへ設定
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param mixed $p_data 設定するデータ
     * @param ?string $p_cid 設定したい接続ID
     */
    final public function setSendStack($p_data = null, ?string $p_cid = null)
    {
        $cid = $this->cid;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }

        // 送信データ設定
        $w_ret = $this->manager->setSendStack($cid, $p_data);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_SEND_DATA_STACK_FAIL->message(),
                UnitExceptionEnum::ECODE_SEND_DATA_STACK_FAIL->value,
                $this
            );
        }
    }

    /**
     * 全接続の送信データスタックへ設定
     * 
     * ※基本的に送受信スタック内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param mixed $p_data 送信データ
     * @param bool $p_self_remove 自身のディスクリプタの除外フラグ
     * @param mixed $p_fnc 処理対象の接続ID評価コールバック
     * @param mixed $p_param コールバックのパラメータ
     */
    final public function setSendStackAll($p_data, bool $p_self_remove = false, $p_fnc = null, $p_param = null)
    {
        if($p_fnc === null)
        {
            $w_ret = $this->manager->setSendStackAll($this->cid, $p_data, $p_self_remove);
            if($w_ret === false)
            {
                throw new UnitException(
                    UnitExceptionEnum::ECODE_SEND_DATA_STACK_FAIL->message(),
                    UnitExceptionEnum::ECODE_SEND_DATA_STACK_FAIL->value,
                    $this
                );
            }
        }
        else
        {
            $param = null;
            if($p_param !== null)
            {
                $param = clone $p_param;
            }
            $cid = null;
            if($p_self_remove === true)
            {
                $cid = $this->cid;
            }
            $cids = $this->manager->getConnectionIdAll($cid);
            foreach($cids as $cid)
            {
                if($param !== null)
                {
                    $param->setConnectionId($cid);
                    $param->setUnitKind($this->getUnitKind());
                }
                $w_ret = $p_fnc($param);
                if($w_ret === false)
                {
                    continue;
                }
                $this->setSendStack($p_data, $cid);
            }
        }
    }


    //--------------------------------------------------------------------------
    // 切断や緊急停止系
    //--------------------------------------------------------------------------

    /**
     * 切断シーケンス開始（プロトコルユニットの'CLOSE'キューを実行）
     * 
     * プロトコルUNIT実行中に呼ばれた場合は例外を投げて現在の処理を中断する
     * 
     * ※基本的に切断情報バッファ内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param mixed $p_param 切断時パラメータ
     * @param ?bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ
     * 
     * ― null（指定なし）の場合は自動判別（プロトコルUNITでの実行：false、コマンドUNITでの実行：true）
     * 
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     */
    final public function close($p_param, ?bool $p_convert = null)
    {
        // フラグの設定
        $convert = false;
        if($p_convert !== null)
        {
            $convert = $p_convert;
        }
        else
        {
            if($this->kind === UnitKindEnum::PROTOCOL)
            {
                $convert = false;
            }
            else
            {
                $convert = true;
            }
        }

        // 切断シーケンス開始
        $w_ret = $this->manager->close($this->cid, $p_param, $convert);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_CLOSE_FAIL->message(),
                UnitExceptionEnum::ECODE_CLOSE_FAIL->value,
                $this
            );
        }
    }

    /**
     * 切断パラメータ（切断シーケンスによって登録されたパラメータ）の取得
     * 
     * ※基本的に切断情報バッファ内のペイロードデータはシリアライズ化されている事を前提とする
     * 
     * @param ?bool $p_convert
     * ― 変換（シリアライズ／アンシリアライズの行使）フラグ
     * 
     * ― null（指定なし）の場合は自動判別（プロトコルUNITでの実行：false、コマンドUNITでの実行：true）
     * 
     * ― フラグ指定に関わらずシリアライザーが登録されていなければ変換はされない
     * 
     * @return mixed 切断パラメータ or null（空）
     */
    final public function getCloseParameter(?bool $p_convert = null)
    {
        // プロパティの取得
        $w_ret = $this->manager->getProperties($this->cid, ['close_buffer']);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PROPERTY_GET_FAIL->message(),
                UnitExceptionEnum::ECODE_PROPERTY_GET_FAIL->value,
                $this
            );
        }
        if($w_ret === null)
        {
            return null;
        }

        // フラグの設定
        $convert = false;
        if($p_convert !== null)
        {
            $convert = $p_convert;
        }
        else
        {
            if($this->kind === UnitKindEnum::PROTOCOL)
            {
                $convert = false;
            }
            else
            {
                $convert = true;
            }
        }

        // アンシリアライズ化
        if($convert === true)
        {
            // アンシリアライザーの取得
            $unserializer = $this->manager->getUnserializer();
            if($unserializer !== null)
            {
                $w_ret['close_buffer'] = $unserializer($w_ret['close_buffer']);
            }
        }

        return $w_ret['close_buffer'];
    }

    /**
     * プロトコルUNIT処理を中断する
     * 
     * 実行されると例外キャッチ時に切断処理は無視されて処理を継続する
     */
    final public function throwBreak()
    {
        $this->manager->throwBreak();
    }

    /**
     * 緊急停止（即時切断）
     */
    final public function emergencyShutdown()
    {
        // 例外発行
        throw new UnitException(
            UnitExceptionEnum::ECODE_EMERGENCY_SHUTDOWN->message($this->lang),
            UnitExceptionEnum::ECODE_EMERGENCY_SHUTDOWN->value,
            $this
        );
    }


    //--------------------------------------------------------------------------
    // タイマー関連
    //--------------------------------------------------------------------------

    /**
     * タイマー処理
     * 
     * @param int $p_ms タイマ値（ms）
     */
    final public function setTimeout(int $p_ms)
    {
        $timer = 0;
        $now = microtime(true) * 1000;
        $tmp = $this->getTempBuff(['__timeout']);
        if($tmp === null || (isset($tmp['__timeout']) && $tmp['__timeout'] === null))
        {
            $timer = $p_ms + $now;
            $this->setTempBuff(['__timeout' => $timer]);
        }
        else
        {
            $timer = $tmp['__timeout'];
        }

        if($timer >= $now)
        {
            $this->manager->throwBreak();
        }

        $this->setTempBuff(['__timeout' => null]);
    }

    /**
     * タイマーリセット
     * 
     * @param string $p_name タイマ名
     * @return bool true（成功） or false（失敗）
     */
    final public function resetTimer(string $p_name): bool
    {
        return $this->manager->resetTimer($this->cid, $p_name);
    }

    /**
     * タイマー（経過時間）取得
     * 
     * @param string $p_name タイマ名
     * @return int 経過時間（ms）
     */
    final public function elapsed(string $p_name): int
    {
        return $this->manager->elapsed($this->cid, $p_name);
    }

    /**
     * タイマー削除
     * 
     * @param string $p_name タイマ名
     */
    final public function removeTimer(string $p_name): void
    {
        $this->manager->removeTimer($this->cid, $p_name);
    }


    //--------------------------------------------------------------------------
    // その他
    //--------------------------------------------------------------------------

    /**
     * 現在のユーザー数を取得
     * 
     * @return int ユーザー数
     */
    final public function getClientCount(): int
    {
        return $this->manager->getClientCount();
    }

    /**
     * ログライター
     * 
     * SocketManagerで使用しているログライターと同じ
     * 
     * @param string $p_level ログレベル
     * @param array $p_param ログパラメータ
     */
    final public function logWriter(string $p_level, array $p_param)
    {
        $this->manager->logWriter($p_level, $p_param);
    }

    /**
     * ソケットマネージャーの取得
     * 
     * @return ?CoreSocketManager ソケットマネージャーのインスタンス
     */
    final public function getSocketManager(): ?CoreSocketManager
    {
        return $this->manager;
    }

    /**
     * 周期ドリブンマネージャーの種別取得
     * 
     * @return ?UnitKindEnum 種別文字列
     */
    final public function getUnitKind(): ?UnitKindEnum
    {
        return $this->kind;
    }

    /**
     * 周期ドリブンマネージャーの種別設定
     * 
     * @param UnitKindEnum $p_kind 種別文字列
     */
    final public function setUnitKind(UnitKindEnum $p_kind)
    {
        $this->kind = $p_kind;
        return;
    }

    /**
     * 接続IDの取得
     * 
     * @return string 接続ID
     */
    final public function getConnectionId(): string
    {
        return $this->cid;
    }

    /**
     * 接続IDの設定
     * 
     * @param string $p_cid 接続ID
     */
    final public function setConnectionId(string $p_cid)
    {
        $this->cid = $p_cid;
        return;
    }

    /**
     * ソケットのアドレス情報を取得
     * 
     * @param string &$p_host ホストアドレス格納先
     * @param int &$p_port ポート番号格納先
     * @param ?string $p_cid 接続ID
     * @return bool true（成功） or false（失敗）
     */
    final public function getSockName(string &$p_host, int &$p_port, ?string $p_cid = null): bool
    {
        $cid = $this->cid;
        if($p_cid !== null)
        {
            $cid = $p_cid;
        }

        return $this->manager->getSockName($cid, $p_host, $p_port);
    }

    /**
     * リモートアドレスの取得
     * 
     * @param ?string &$p_host リモートホスト
     * @param ?int &$p_port リモートポート
     */
    final public function getRemoteAddr(?string &$p_host, ?int &$p_port)
    {
        $w_ret = $this->manager->getProperties($this->cid, ['remote']);
        if($w_ret === false)
        {
            throw new UnitException(
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->message(),
                UnitExceptionEnum::ECODE_PROPERTY_SET_FAIL->value,
                $this
            );
        }

        $p_host = $w_ret['remote']['host'];
        $p_port = $w_ret['remote']['port'];
    }

}
