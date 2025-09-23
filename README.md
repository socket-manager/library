# SOCKET-MANAGER Library | PHP用ソケット通信フレームワーク

SOCKET-MANAGER Libraryは、**PHPで高性能なソケット通信サーバー**開発を支援するためのオープンソースフレームワークです。TCP、UDP、WebSocketなどの通信方式に対応し、**非同期イベントループ**や**コルーチン**による効率的な並列処理、**スケーラブルな構成**を実現します。

## 主な連携プロジェクト

本ライブラリは、以下の関連プロジェクトと組み合わせて利用できます。

- [demo-project](https://github.com/socket-manager/demo-project) : マインクラフトと連携できるWebSocketサーバーのデモ環境
- [websocket-project](https://github.com/socket-manager/websocket-project) : WebSocketサーバー開発環境
- [new-project](https://github.com/socket-manager/new-project) : 新規プロジェクト開発環境
- [contents-project](https://github.com/socket-manager/contents-project) : マインクラフト専用コンテンツ環境

## ドキュメント・導入ガイド

詳しい使い方やセットアップ方法は[公式ドキュメント](https://socket-manager.github.io/document/)をご覧ください。

- Laravelプロジェクトとの連携は[Laravel連携ガイド](https://socket-manager.github.io/document/laravel.html)参照

## 主要機能一覧
当フレームワークが提供する主要な機能と特徴について一覧で示します。これらの機能により、高度なスケーラビリティと柔軟な実装が可能となります。
| 項目 | 内容 |
|------|------|
| **実装形態** | **コマンドベースのスキャフォールディング（標準実装／デベロッパーによる追加構築不要）** |
| **非同期モデル** | **独自仕様のイベントループ / コルーチン対応（ステータス維持したまま処理を中断可能）** |
| **通信方式** | **TCP / UDP / WebSocket / 独自プロトコル対応** |
| **IPC形態** | **INETソケットを利用し、異なるプロトコルの共存が可能** |
| **スケールアップ** | **プロセス単位で可能（ポート変更による動的調整）** |
| **スケールアウト** | **プロセス単位で可能（複数サーバー間で負荷分散）** |
| **プラットフォーム** | **Windows / Linux（Ubuntuによる動作確認）** |

---

### 🧭 その他の特長

- **依存性の排除**：外部サービスやサードパーティ製ライブラリに依存せず、単独で動作可能  
- **独自イベントループ/コルーチン**：プロセスやスレッドに頼らず通信制御を実現  
- **設定の柔軟性**：設定ファイルや翻訳リソースの柔軟な切り替えと管理  
- **軽量な導入**：PHP と sockets モジュールのみで動作する、シンプルなセットアップ

## Contact Us
バグ報告やご要望などは<a href="mailto:lib.tech.engineer@gmail.com">`こちら`</a>から受け付けております。

## License
MIT, see <a href="https://github.com/socket-manager/library/blob/main/LICENSE">LICENSE file</a>.