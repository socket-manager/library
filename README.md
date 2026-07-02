# PHP で WebSocket / TCP / UDP / REST-API を構築するための高性能リアルタイム通信フレームワークのコアライブラリ
※ **REST-API / RESTful-API サーバー開発にも対応**  
※ **IPC（プロセス間通信）・カスタムコマンド作成機能にも対応**

SOCKET-MANAGER Library は、**PHPで高性能なソケット通信サーバー**を構築するためのオープンソースフレームワークです。  
TCP、UDP、WebSocket などの通信方式に加え、**REST-API / RESTful-API サーバー開発にも正式対応**しました。

SOCKET-MANAGER Library は、ゼロコピー設計（Union）と同期ランタイム、
FFI + 独自 IO ドライバを組み合わせたハイパフォーマンスモードにより、

- **WebSocket：100,000 同時接続 / 平均 0.553ms / 18,000〜20,000 rps**
- **TCP：100,000 同時接続 / 平均 0.525ms / 19,000〜21,500 rps**
- **CPU 割当なし・メモリ 128MB のまま 6 時間耐久・エラーゼロ**

という実運用スケールの性能を確認しています。

高スループット・低レイテンシ・軽量性・堅牢性の四要素を PHP 単体で満たす通信基盤として設計されています。

フレームワーク本体には **非同期イベントループ**、**コルーチン**、そして **ビルトインのステートマシン** が統合されており、リアルタイム通信だけでなく、REST-API における **Chunked Transfer / SSE / Range送信** など、状態遷移を伴う高度な API 処理も安定して実装できます。

さらに、**IPC（プロセス間通信）** によるマルチサーバー連携や、**カスタムコマンド作成機能** によるプロジェクト固有のスキャフォールディングにも対応しています。

---

## 【 ハイパフォーマンスモード（High Performance Mode） 】

SOCKET-MANAGER Library には、独自 IO ドライバと同期ランタイムを組み合わせた  
**ハイパフォーマンスモード（High Performance Mode）** が搭載されています。

- **100,000 同時接続を 0.52〜0.55ms（サブミリ秒帯）で処理**
- **最大 40,521 ラウンド（約 4 億回の往復通信）でもエラーなし**
- **ゼロコピー設計（Union）により高負荷時でも安定したレイテンシ**
- **Windows でも 50,000 同時接続を 0.43〜0.50ms で処理**
- **FFI + select互換モードの自動切替による高速 IO**

リアルタイム通信に必要な **高性能・堅牢性・再現性** を PHP で実現するための  
フレームワーク内蔵モードです。

詳細はこちら：  
https://socket-manager.github.io/document/high-performance.html

---

## 【 WebSocket スケール性能 】

SOCKET-MANAGER Framework の基盤上に WebSocket プロトコルをステートマシンを使ってビジネスロジック実装した実運用に近い構成で測定したスケール特性を公開しています。

- **100,000 同時接続（ping/pong）で平均 0.553ms**
- **18,000〜20,000 rps（10,000 接続あたり 1,800〜2,000 rps）**
- **CPU 割当なし・メモリ 128MB のまま 6 時間耐久・エラーゼロ**
- **ラウンド間隔なしの連続バーストでも破綻なし**

（※ WebSocket のスケール性能は、ペイロード 100 バイトの ping/pong フレームを用いた測定です）

詳細はこちら：  
https://socket-manager.github.io/document/scale-test.html

---

## 【 純粋 TCP スケール性能 】

SOCKET-MANAGER Framework の基盤上に TCP エコー処理をステートマシンで実装した実運用想定の構成で測定したスケール特性を公開しています。

- **100,000 同時接続（TCP echo）で平均 0.525ms**
- **19,000〜21,500 rps（10,000 接続あたり 1,900〜2,150 rps）**
- **CPU 割当なし・メモリ 128MB のまま 6 時間耐久・エラーゼロ**
- **ラウンド間隔なしの連続バーストでも破綻なし**

（※ TCP のスケール性能は、ペイロード 100 バイトの echo 処理を用いた測定です）

詳細はこちら：  
https://socket-manager.github.io/document/pure-tcp-scale.html

---

## 【 主な連携プロジェクト 】

本ライブラリは、以下の関連プロジェクトと組み合わせて利用できます。

- [demo-project](https://github.com/socket-manager/demo-project) : マインクラフトと連携できる WebSocket サーバーのデモ環境
- [websocket-project](https://github.com/socket-manager/websocket-project) : WebSocket サーバー開発環境
- [new-project](https://github.com/socket-manager/new-project) : 新規プロジェクト開発環境
- [contents-project](https://github.com/socket-manager/contents-project) : マインクラフト専用コンテンツ環境
- [rest-api](https://github.com/socket-manager/rest-api) : **REST-API / RESTful-API サーバー開発環境（PSR-7準拠）**
- [launcher](https://github.com/socket-manager/launcher) : SOCKET-MANAGER Launcher （GUI & CLI ランチャー）

---

## 【 ドキュメント・導入ガイド 】

詳しい使い方やセットアップ方法は [公式ドキュメント](https://socket-manager.github.io/document/) をご覧ください。

- Laravel プロジェクトとの連携は  
  [Laravel連携ガイド](https://socket-manager.github.io/document/laravel.html) を参照  
- REST-API / RESTful-API 開発については  
  [REST-API 開発環境](https://github.com/socket-manager/rest-api) を参照  
- IPC（プロセス間通信）については  
  [IPC（プロセス間通信）](https://socket-manager.github.io/document/ipc.html) を参照  
- カスタムコマンド作成機能については  
  [カスタムコマンド作成機能](https://socket-manager.github.io/document/custom-command.html) を参照

---

## 【 主要機能一覧 】

当フレームワークが提供する主要な機能と特徴について一覧で示します。  
これらの機能により、高度なスケーラビリティと柔軟な実装が可能となります。

| 項目 | 内容 |
|------|------|
| **実装形態** | **コマンドベースのスキャフォールディング（標準実装／デベロッパーによる追加構築不要）** |
| **非同期モデル** | **独自仕様のイベントループ / コルーチン対応（ステータス維持したまま処理を中断可能）** |
| **通信方式** | **TCP / UDP / WebSocket / 独自プロトコル対応** |
| **ゼロコピー設計（Union）** | **大量接続時でも GC 負荷を抑制** |
| **高性能 IO ドライバ** | **FFI + 独自拡張 / select 互換モードの自動切替による高速処理（ハイパフォーマンスモード）** |
| **WebSocket / TCP スケール性能** | **100,000 同時接続・サブミリ秒レイテンシ** |
| **REST-API 対応** | **PSR-7準拠の REST-API / RESTful-API を実装可能（イベントハンドラ型 / ステートマシン型）** |
| **ステートマシン** | **Chunked / SSE / Range送信など、状態遷移を伴う API 処理を確実に制御** |
| **IPC形態** | **INETソケットを利用したプロセス間通信（異なるプロトコルの共存が可能）** |
| **カスタムコマンド** | **command.php / params.php / template.php.tpl によるプロジェクト固有コマンドの追加** |
| **スケールアップ** | **プロセス単位で可能（ポート変更による動的調整）** |
| **スケールアウト** | **プロセス単位で可能（複数サーバー間で負荷分散）** |
| **プラットフォーム** | **PHP が動作する環境であれば利用可能（Windows / Linux で動作確認済み）** |

---

### 🧭 その他の特長

- **依存性の排除**：外部サービスやサードパーティ製ライブラリに依存せず、単独で動作可能  
- **独自イベントループ / コルーチン**：プロセスやスレッドに頼らず通信制御を実現  
- **設定の柔軟性**：設定ファイルや翻訳リソースの柔軟な切り替えと管理  
- **軽量な導入**：PHP と sockets モジュールのみで動作するシンプルなセットアップ  
- **REST-API との親和性**：ステートマシンにより、Chunked Transfer や SSE の分割送信を安定制御  
- **ゼロコピー設計により、10万同時接続でも安定したレイテンシを維持**
- **6 時間耐久・エラーゼロのスケール特性**
- **Web / ゲーム / IoT / REST-API など幅広い用途に対応**

---

## 【 Contact Us 】
バグ報告やご要望などは <a href="mailto:lib.tech.engineer@gmail.com">`こちら`</a> から受け付けております。

---

## 【 License 】
MIT, see <a href="https://github.com/socket-manager/library/blob/main/LICENSE">LICENSE file</a>.
