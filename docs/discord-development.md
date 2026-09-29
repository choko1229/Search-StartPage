# Discord開発用ログインの設定

Phase 4の自動テストは模擬ユーザーと隔離DBを使用します。Discord自体へのログイン成功を代替しません。

1. [Discord Developer Portal](https://discord.com/developers/applications) で開発用アプリを作成または選択します。
2. OAuth2のRedirectsに、開発サイトのURLに `/auth/discord/callback` を付けたURLを登録します。例: `http://127.0.0.1:8082/auth/discord/callback`。
3. 開発環境のGit除外ファイル `config/config.php` で `site.url` を上と同じホスト・ポートに設定し、`discord.client_id` と `discord.client_secret` を入力します。Secretはチャットや進捗ファイルへ貼り付けないでください。
4. Dockerでは設定はコンテナの `/var/www/app/config/config.php` にあります。ホスト側のファイルと同一ではありません。設定ファイル全体を端末出力に表示しないでください。
5. `/account` から「Discordでログイン」を実行します。認可画面で表示されるアプリ名と要求権限を確認します。要求権限は `identify` のみです。
6. アカウント表示、この端末ラベル、端末名変更、ログアウト、再ログインを確認します。

HTTPSの本番CookieはSecure/HttpOnly/SameSite=Laxです。HTTPでの確認は明示したローカル開発環境に限定します。

OAuthのstateは10分有効・一回使用。Discordアクセストークンはユーザー情報取得にだけ使い、DBに保存しません。サイトの長期ログイントークンはハッシュのみ保存し、最終利用から90日で失効します。

参考: [Discord公式OAuth2仕様](https://github.com/discord/discord-api-docs/blob/main/developers/topics/oauth2.mdx)
