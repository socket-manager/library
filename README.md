# SOCKET-MANAGER Library：PHP用ソケット通信フレームワーク
※ **REST-API / RESTful-API サーバー開発にも対応**

SOCKET-MANAGER Library は、**PHPで高性能なソケット通信サーバー**を構築するためのオープンソースフレームワークです。  
TCP、UDP、WebSocket などの通信方式に加え、**REST-API / RESTful-API サーバー開発にも正式対応**しました。

フレームワーク本体には **非同期イベントループ**、**コルーチン**、そして **ビルトインのステートマシン**が統合されており、リアルタイム通信だけでなく、REST-API における **Chunked Transfer / SSE / Range送信** など、状態遷移を伴う高度な API 処理も安定して実装できます。

---

## 【 主な連携プロジェクト 】

本ライブラリは、以下の関連プロジェクトと組み合わせて利用できます。

- [demo-project](https://github.com/socket-manager/demo-project) : マインクラフトと連携できるWebSocketサーバーのデモ環境
- [websocket-project](https://github.com/socket-manager/websocket-project) : WebSocketサーバー開発環境
- [new-project](https://github.com/socket-manager/new-project) : 新規プロジェクト開発環境
- [contents-project](https://github.com/socket-manager/contents-project) : マインクラフト専用コンテンツ環境
- [rest-api](https://github.com/socket-manager/rest-api) : **REST-API / RESTful-API サーバー開発環境（PSR-7準拠）**

---

## 【 ドキュメント・導入ガイド 】

詳しい使い方やセットアップ方法は[公式ドキュメント](https://socket-manager.github.io/document/)をご覧ください。

- Laravel プロジェクトとの連携は  
  [Laravel連携ガイド](https://socket-manager.github.io/document/laravel.html) を参照  
- REST-API / RESTful-API 開発については  
  [REST-API 開発環境](https://github.com/socket-manager/rest-api) を参照

---

## 【 主要機能一覧 】
当フレームワークが提供する主要な機能と特徴について一覧で示します。これらの機能により、高度なスケーラビリティと柔軟な実装が可能となります。
| 項目 | 内容 |
|------|------|
| **実装形態** | **コマンドベースのスキャフォールディング（標準実装／デベロッパーによる追加構築不要）** |
| **非同期モデル** | **独自仕様のイベントループ / コルーチン対応（ステータス維持したまま処理を中断可能）** |
| **通信方式** | **TCP / UDP / WebSocket / 独自プロトコル対応** |
| **REST-API 対応** | **PSR-7準拠の REST-API / RESTful-API を実装可能（イベントハンドラ型 / ステートマシン型）** |
| **ステートマシン** | **Chunked / SSE / Range送信など、状態遷移を伴う API 処理を確実に制御** |
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
- **REST-API との親和性**：ステートマシンにより、Chunked Transfer や SSE の分割送信を安定制御  
- **Web / ゲーム / IoT など幅広い用途に対応**

---

## 【 Contact Us 】
バグ報告やご要望などは<a href="mailto:lib.tech.engineer@gmail.com">`こちら`</a>から受け付けております。

---

## 【 License 】
MIT, see <a href="https://github.com/socket-manager/library/blob/main/LICENSE">LICENSE file</a>.