# SOCKET-MANAGER Library
SOCKET-MANAGER Framework（ソケットマネージャーフレームワーク）はソケット通信サーバーの開発を支援するための環境で、このライブラリはそのフレームワークの基盤となるものです。<br />
以下のプロジェクトと連携して使います。<br />

<ul>
    <li><a href="https://github.com/socket-manager/demo-project">demo-project</a>（マインクラフトと連携できるWebsocketサーバーのデモ環境）</li>
    <li><a href="https://github.com/socket-manager/websocket-project">websocket-project</a>（Websocketサーバーの開発環境）</li>
    <li><a href="https://github.com/socket-manager/new-project">new-project</a>（新規プロジェクト開発環境）</li>
    <li><a href="https://github.com/socket-manager/contents-project">contents-project</a>（マインクラフト専用のコンテンツ環境）</li>
</ul>

詳しい使い方は<a href="https://socket-manager.github.io/document/">こちら</a>をご覧ください。<br />

Laravelプロジェクトと連携する場合は<a href="https://socket-manager.github.io/document/laravel.html">こちら</a>をご覧ください。

## 主要機能一覧
当フレームワークが提供する主要な機能と特徴について一覧で示します。これらの機能により、高度なスケーラビリティと柔軟な実装が可能となります。
| 項目 | 内容 |
|------|------|
| **実装形態** | **コマンドベースのスキャフォールディング** |
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