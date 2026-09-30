# Discord開発用ログインの設定

Phase 4の自動テストは模擬ユーザーと隔離DBを使用します。Discord自体へのログイン成功を代替しません。

## 現在のDocker UI環境へ安全に入力

Developer Portalで `http://127.0.0.1:8082/auth/discord/callback` と `http://127.0.0.1:8082/api/auth/discord/callback` を登録後、リポジトリで以下を実行できます。

```powershell
./bin/configure-discord.ps1
```

Client IDとSecretを対話入力します。Secretは画面表示・コマンド引数・ホスト上のファイルへ保存しません。Git除外のコンテナ設定ファイルに保存します。設定後は `http://127.0.0.1:8082/account` を開きます。

1. [Discord Developer Portal](https://discord.com/developers/applications) で開発用アプリを作成または選択します。
2. OAuth2のRedirectsに、開発サイトのURLに `/auth/discord/callback` と `/api/auth/discord/callback` を付けた2つのURLを登録します。前者はWeb画面、後者はJSON APIからの認証に使用します。
3. 開発環境のGit除外ファイル `config/config.php` で `site.url` を上と同じホスト・ポートに設定し、`discord.client_id` と `discord.client_secret` を入力します。Secretはチャットや進捗ファイルへ貼り付けないでください。
4. Dockerでは設定はコンテナの `/var/www/app/config/config.php` にあります。ホスト側のファイルと同一ではありません。設定ファイル全体を端末出力に表示しないでください。
5. `/account` から「Discordでログイン」を実行します。認可画面で表示されるアプリ名と要求権限を確認します。要求権限は `identify` のみです。
6. アカウント表示、この端末ラベル、端末名変更、ログアウト、再ログインを確認します。

APIでは `GET /api/auth/discord` が返す `data.authorization_url` へ移動します。同じブラウザのCookieを保持してください。API CallbackはJSONのユーザー情報を返します。Web画面のCallbackはアカウント画面へ戻ります。どちらも同じ固定サイトURL・identify権限を使用します。

ログイン開始とCallbackは接続元IPごとに合計20回/60秒までです。`login_rate_limit.attempts` と `login_rate_limit.window_seconds` で調整できます。`X-Forwarded-For`等は信用しないため、リバースプロキシ利用時はアプリから見える接続元単位の制限になります。

HTTPSの本番CookieはSecure/HttpOnly/SameSite=Laxです。HTTPでの確認は明示したローカル開発環境に限定します。

OAuthのstateは10分有効・一回使用。Discordアクセストークンはユーザー情報取得にだけ使い、DBに保存しません。サイトの長期ログイントークンはハッシュのみ保存し、最終利用から90日で失効します。

参考: [Discord公式OAuth2仕様](https://github.com/discord/discord-api-docs/blob/main/developers/topics/oauth2.mdx)
