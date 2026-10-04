# 管理ログと保持期間

管理者は `/admin/logs` でログ全体、`/admin/audit-logs` で管理操作の監査を閲覧できます。各画面・APIはサーバーで現在の管理者権限を確認します。

種類、開始日・終了日（UTC）、内部ユーザーID、エラーコード、キーワードで絞り込めます。終了日は当日の23:59:59までを含みます。キーワードの `%` や `_` は通常の文字として検索します。ユーザーIDはDiscord IDとは異なる、管理ユーザー一覧のAPIが返す内部IDです。

API:

- `GET /api/admin/logs`
- `GET /api/admin/audit-logs`

両APIの検索パラメーターは `type`、`from`、`to`、`user_id`、`error_code`、`q`、`page`、`page_size` です。`page_size` は1〜100、既定25です。監査専用APIは常に管理操作だけを返します。

ログは90日保持します。DBの期限切れ記録は削除し、日別ファイルの境界日については記録日時を確認して古い行だけを除去します。整理とファイル書き込みは同じロックを使用します。ログ以外のファイルやリンク先を削除しません。

ログ画面を開くと保持期間の整理と未配送監査の再試行が行われます。アクセスがない日にも整理するため、アプリと同じ実行ユーザーで `php bin/log-maintenance.php` を常駐させられます。起動時と24時間ごとに実行し、失敗時は1時間後に再試行します。出力は成功/失敗、日時、件数だけで、例外の詳細や認証情報は出しません。storageの専用ロックで同じアプリの二重起動を拒否します。Webからは実行できません。

Dockerの専用テスト構成は、設定済みの専用DB用環境変数を読み込んだ上で次のoverlayを追加します。workerにWebポートはなく、configは読み取り専用、storageはアプリと同じ専用volumeです。

```sh
docker compose -f compose.yaml -f docker/log-maintenance.compose.yaml --profile log-maintenance up -d --build logs-mysql logs-mariadb
```

稼働と成功/失敗は `docker compose -f compose.yaml -f docker/log-maintenance.compose.yaml logs logs-mysql logs-mariadb` で確認します。停止は同じ構成の `stop logs-mysql logs-mariadb` を使います。`unless-stopped`でDocker起動時にも再開しますが、Docker自体が停止している間は実行されません。2026-10-04の隔離MySQL/MariaDB環境では両workerの稼働と初回成功、短い間隔での実2回実行を確認しています。本番ホストへの配置やWindowsタスクの登録はしていません。

通常のサーバーで既存の定期実行機能を使う場合は、`php bin/cleanup-logs.php` を毎日実行する方法も維持しています。常駐workerでは `--interval=86400 --retry=3600` を指定でき、通常環境の最短間隔は60秒です。有限回数の `--cycles` と60秒未満の間隔は `SEARCH_TEST_MODE=1` に限定します。

`tests/log-maintenance-outage.php` は専用tmpfs DB・新規一時配置に限定した実process試験です。接続先を一時的に到達不能なloopback portへ切り替え、本物のPDO接続失敗後に設定を原子的に戻します。同じ常駐workerが2秒後に再試行し、期限切れDB/file整理・未配送記録の一度だけの取込み・最近の記録と非ゼロ統計の保持・秘密非出力を確認します。`--file-lock-outage` はログlockを一時symlinkにして実整理を失敗させ、リンク先を変更せず拒否し、修復後に同じworkerが完了することを確認します。DB整理済みでもfile整理が失敗する場合は、次回の冪等処理で残ったfileを整理します。接続試験はDBサーバー停止ではなく接続障害、file試験はunsafe lockでありdisk fullの証明ではありません。実際の1時間待機、本番配置、disk full、OS権限障害は未確認です。

監査ファイルの配送が失敗しても、設定とDB監査の保存は維持します。ファイル配送は再試行され、監査IDで同じ操作を識別できます。配送直後のプロセス中断などでファイルに重複行ができる場合がありますが、管理画面のDB記録は1件です。

PHPの警告・例外、APIエラー、OAuthエラー、同期エラー、権限・CSRFなどのセキュリティエラーを共通の収集処理へ接続しています。例外を投げずに返される同期の競合応答も記録します。ログには例外メッセージ・スタック・検索語・URL・クエリー文字列・リクエスト本文・トークンを含めません。

DBやログファイルに配送できない記録は非公開の `storage/log-pending` に保持します。管理ログの閲覧または整理コマンドで再配送し、固有のevent IDによりDBへ重複登録しません。再配送しても元の日時を維持し、90日を過ぎた記録は復活させません。形式が不正な記録は隔離し、通常のログへ取り込まず、隔離後も保持期限を適用します。ファイル配送直後の中断では同じevent IDを持つ行が重複する可能性があるため、ファイルを集計するときもevent IDを使います。

更新workerの固定エラーは、完了履歴・管理監査と同じtransactionで `update_error` に保存します。request ID・状態・固定エラーから決定的event IDを作り、結果の再投影によるDB重複を防ぎます。管理ログ閲覧・ログ整理時にDBの未配送記録をファイルへ再試行します。worker終了時の即時ファイル配送ではありません。ファイルにもevent IDを記録しますが、配送後の中断やDB復元では重複行の可能性があります。ApplicationLoggerの私有pending queueとは分離します。

開発用のPHP警告・DB接続不能試験は `tests/error-logs-outage.php` です。専用Docker環境でのみ実行し、`tests/php-errors-preview.php` をその環境の `public/_test/php-errors-preview.php` へ一時配置してから実行します。試験は専用の開発設定だけを一時変更し、元の設定を復元します。配置したプレビューは検証後に除去してください。本番ルートには登録されていません。
