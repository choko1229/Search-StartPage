# search.choko1229.net

PHP 8.2+、MySQL 8+ / MariaDB 10.11+、HTML/CSS/Vanilla JavaScriptによる検索スタートページ。
ComposerとNode.jsの実行は不要です。

**現在はPhase 1〜2を検証済み、Phase 3を実装中です。Version 1.0は未完成です。**
Web/AI検索は利用できます。OAuthログイン・同期などの後続Phaseの機能はまだ実装していません。
段階ごとの検証が完了するまで次のPhaseへ進みません。

再起動・中断後は最初に [progress.md](progress.md) を確認してください。
[AGENTS.md](AGENTS.md) にCodexの再開手順を定めています。

## 開発用Docker環境

Docker DesktopのLinuxコンテナエンジンを起動してください。
Windowsで `Virtual Machine Platform not enabled` と表示される場合は、
管理者PowerShellで `wsl --install --no-distribution` を実行し、Windowsを再起動します。
ファームウェアで仮想化が無効の場合は、そちらの有効化も必要です。

```powershell
./tests/docker.ps1
```

PHP 8.2 + MySQL 8とPHP 8.2 + MariaDB 10.11の独立環境を構築し、
PHP構文、基盤ユニットテスト、実DBとHTTP Installerの統合テストを実行します。
生成したDBパスワードはプロセス環境変数だけに保持します。
本番の認証情報は不要です。各実行で別名のComposeプロジェクトとボリュームを作ります。
テストコンテナは結果確認用に残り、スクリプトは既存データを削除しません。
8080/8081を使う前の検証環境が起動中なら、同じPowerShellで `docker compose stop` を実行してから再実行します。

- MySQL版: `http://localhost:8080`
- MariaDB版: `http://localhost:8081`
- DBポートはホストへ公開しません。

手動でInstallerを確認する場合：

```powershell
./tests/docker.ps1 -StartOnly
docker compose exec --user www-data app-mysql php bin/setup-key.php
```

環境確認画面に表示されたキーを入力します。DBホストは `mysql`、DB名・ユーザーは `startpage`、
DBパスワードはこのPowerShellの `$env:TEST_DB_PASSWORD` です。
MariaDB版はサービス名 `app-mariadb`、DBホスト `mariadb` です。
Compose操作はこのPowerShellのプロジェクト名とパスワード環境変数を引き継いで実行してください。

## PHPを直接実行する場合

PHP 8.2以上と `pdo_mysql` / `curl` / `json` / `openssl` / `mbstring` / `fileinfo` が必要です。

```sh
php tests/lint.php
php tests/run.php
php bin/setup-key.php
php -S 127.0.0.1:8080 -t public bin/serve.php
```

専用の空のDBを準備し、ブラウザで `http://localhost:8080/` を開きます。
内蔵Webサーバーは開発専用です。

## Installer

1. 必須拡張と書き込み権限を確認し、CLIで生成したセットアップキーを入力。
2. MySQL / MariaDBの接続情報を入力。DBはあらかじめ作成し、DDL権限を付与。
3. サイト名とHTTPS URLを指定。サブディレクトリ構成は現在サポートしません。
4. Discord Client ID / Secretを指定。未取得なら両方空欄にできます。
5. 初期管理者のDiscordユーザーIDを予約。
6. 設定を確認し、Migrationと設定ファイル生成を実行。
7. 完了。再インストールはロックされ、セットアップキーは削除されます。

認証情報はURLやブラウザのストレージに保存せず、セットアップ中はサーバーのセッションに保持します。
途中の設定は30分無操作で破棄されます。完了時にも破棄します。
二重送信・古いステップの送信は拒否します。

`config/config.php` は自動生成・Git対象外です。公開ルートは必ず `public/` にしてください。
Discordに登録するリダイレクトURIは `{サイトURL}/api/auth/discord/callback` です。
**OAuthそのものはPhase 4の実装対象で、現時点では認証できません。**
初期管理者は `installation_claims` に予約し、未認証の架空ユーザーや管理者セッションは作りません。

## 構成

| ディレクトリ | 責務 |
| --- | --- |
| `app/Controllers` | HTTP入力とレスポンス |
| `app/Services` | Installer・環境判定・ログ |
| `app/Repositories` | 業務データのSQL |
| `app/Database` | PDO接続・Migration実行 |
| `app/Router`, `app/Http`, `app/Middleware` | Router・APIレスポンス・CSRF |
| `app/Auth` | PHP Session・CSRFトークン |
| `app/Helpers`, `app/Views`, `lang` | View・Escape・ja/en |
| `database/migrations` | バージョン管理されたDB変更 |
| `public` | 唯一のWeb公開ディレクトリ |
| `storage` | 非公開のログ・アップロード等 |
| `bin` | CLIセットアップ・Migration・開発サーバー |
| `tests`, `docker` | 検証コードと隔離環境 |

## Phase 1 API

| Method | Path | 内容 |
| --- | --- | --- |
| GET | `/api/health` | DB接続を含む稼働状態。未導入/DB障害は503 |
| GET | `/api/csrf` | 現在のセッションのCSRFトークン |
| POST | `/locale` | CSRF付きja/en切り替え、303で画面へ戻る |
| GET / POST | `/installer` | 初回セットアップ |

APIは成功時 `{ "success": true, "data": {} }`、失敗時
`{ "success": false, "error": { "code": "...", "message": "..." } }` を返します。

## Migration

```sh
php bin/migrate.php
```

`migrations` テーブルに適用済みファイルとSHA-256を記録します。
適用済みMigrationは編集せず、新しいMigrationを追加します。
DB advisory lockで同時実行を防ぎます。
MySQL/MariaDBのDDLは暗黙コミットされるため、トランザクションで巻き戻せるとは扱いません。
Phase 1のテーブル作成は途中失敗後に再実行可能です。
`001_core.php` の `down()` は空の検証DBでの検証専用であり、本番データの復元には使用しません。
Updaterのバックアップ・復元はPhase 10で実装します。

## 公開環境の構成条件

現時点の開発版を完成済みサービスとして公開しないでください。
最終公開時はHTTPSを用意し、DocumentRootを `public/` に設定します。
Apache設定例は `docker/apache.conf` を参照してください。
`config/` と `storage/` のみPHP実行ユーザーが書き込めるようにします。
設定・ログ・セッションファイルは第三者から読めない権限にします。
TLS終端プロキシを使う場合は、Webサーバーで信頼するプロキシだけに基づいて
PHPの `HTTPS=on` を設定してください。アプリは任意の転送ヘッダーを信用しません。

任意の画像処理はImagickまたはGD、動画処理はFFmpegを使用する予定です。
Installerは未導入でも動作し、`SEARCH_FFMPEG_PATH` で指定された実行ファイルの有無を確認します。
圧縮・Updater・Chrome Extensionの機能は、各実装Phaseに到達するまで使用できません。

進捗・検証結果は [docs/phase-status.md](docs/phase-status.md) に記録します。
