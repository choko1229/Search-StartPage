# 管理者権限の変更

最新の実画面検証は [admin-roles-ui.md](admin-roles-ui.md)。専用MySQL/MariaDBで、操作時点のユーザー承認後に付与・解除・最後の管理者拒否を実際に確認した。下の「実ブラウザ未実行・承認待ち」は当時の記録であり、今回の限定範囲は解消済み。実OAuthや全browserの証拠にはしない。

`/admin/users` の各ユーザーで管理者権限の付与・解除を行う。初期管理者はInstallerで登録したDiscord IDが最初に認証された際に作成する既存方式を維持する。通常ユーザーや未ログイン利用者は操作できない。

APIは `POST /api/admin/users/role`。CSRFトークンと以下のJSONを送る。

```json
{"user_id":123,"admin_flag":true,"expected_admin_flag":false,"version":1}
```

`version` は `GET /api/admin/users` の `role_version`、`expected_admin_flag` は一覧で確認した対象の現在の権限。どちらかが古ければ409 `ADMIN_SETTINGS_CONFLICT`となり、一覧を再取得して判断する。HTMLフォームは同じ処理を使い成功時に一覧へ303で戻る。

Migration016は `site_settings.admin_roles` に変更の通し版を作る。Repositoryはこの行を排他ロックし、対象ユーザーと有効な管理者を確認する。待機前にMiddlewareが認証した操作でも、ロック取得後に操作者の現在の管理者権限を再確認する。異なる管理者への同時変更も直列化し、最後の有効な管理者の解除は409 `LAST_ADMIN_REQUIRED`で拒否する。他に管理者がいれば自分の権限を解除できる。

変更・版更新・`ADMIN_ROLE_CHANGED`監査を同じDB transactionで保存し、その後既存のファイル監査outboxを処理する。監査には操作者、対象、変更前後、版を含める。変更がない要求は版を進めず監査を増やさない。解除や再付与で最初のCreated By/Created Atを変更しない。既存ログインの管理者アクセスも現在のDB権限を確認するため、解除後は拒否される。

## 検証

2026-10-04、専用MySQL 8/MariaDB 10.11環境で各29項目のHTTP/DB検証、各13項目の別プロセス競合検証、各6項目のMigration016検証に成功。競合検証は実際にロック待機中のプロセスを確認し、二人の同時自己解除と待機中の操作者失権を試す。Migration016はtransactional DMLのみで、往復・再実行後にrollbackし検証前の設定行を完全に復元する。

`tests/admin-roles.php`、`tests/admin-role-concurrency.php`、`tests/admin-role-migration.php` はCLIとSEARCH_TEST_MODE=1を要求し、開始時に有効な管理者がいれば拒否する。専用の空の開発環境でのみ実行する。競合検証の子プロセス入力はDocker Linuxの `/dev/null` を使用する。生成したユーザーだけをfinallyで削除し、監査は保持する。

日英フォームのHTML応答と実ブラウザ表示、390px幅/content375、Console0を検証済み。新規独立MySQL/MariaDB環境で全16Migrationのup/repeat/down/Web Installer再up各40項目を確認した。実ブラウザの付与・解除・最後の管理者の拒否は、自動承認レビューが明示承認不足としてクリックを拒否したため未実行。専用対象と範囲を明示してユーザーへ承認を質問中。実Discord OAuth成功の代替証明にはしない。
