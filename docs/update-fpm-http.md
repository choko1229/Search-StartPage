# FPM経由の実HTTP更新・復元試験

`tests/update-requests-http.php` の `TEST_UPDATE_FPM=1` は、専用NginxからPHP FPMを呼ぶ。通常のWeb入口、認証・CSRF、管理API、専用worker、実Engine、DB保存・復元、変更PHP画面表示まで接続する。GitHub候補とarchive取得だけはfixture。実Discord OAuthやGitHubアクセス成功の証拠ではない。

PHP8.3 FPMのOPcache timestampsを0にし、static childを2つ起動する。CLIとWebは同じwww-data。HTTP受付自身では更新せず、応答後に新しいCLI子が実行する。常駐workerへstopを送り、実Engine完了を待って終了する。更新後は本来の製品runner入口から復元する。config hash・両履歴・新旧PHPテンプレート表示を確認する。

再現用ファイルは `tests/fixtures/update-fpm/Dockerfile` と `nginx.conf`、PowerShell harnessは `tests/run-update-fpm.ps1`。Docker Desktopの既定パス、既存の隔離network `search-phase9-roles-20261004_default` を前提とする。固定専用container名が既存なら停止せず拒否する。

```powershell
docker build -t search-update-fpm-engine:20261004 tests/fixtures/update-fpm
./tests/run-update-fpm.ps1
```

専用DBはtmpfs512MiB・host portなし・生成password、アプリもhost portなし。公開設定雛形と現ソースだけをコピーし、実configをコピーしない。試験入口は空schemaを確認してから所有し、finallyでそのschemaだけ清掃する。harnessはtmpfs配置を確認してから専用DBを除去する。通常8099/8100のユーザー・権限・設定・workerへ操作しない。

`tests/update-fpm-runtime.php` は固定 `/tmp/search-update-fpm-source`、markerあり・configなし・FPM SAPIに限定した検証入口。Nginxの専用loopback routeだけへ接続し、release/publicには配置しない。SAPI/PHP版/OPcache設定で実FPM経由を確認する。WebのWarning/Stack Trace非公開設定を維持し、CLI試験の診断と区別する。

この試験は1 master/2 static childの専用Nginx・PHP8.3構成。独立2 masterのcache刷新は別の `tests/update-web-cache-fpm.php`、Apache実更新・ブラウザ操作も別の検証。systemd boot/restart・長時間常駐・実外部連携・全browserは引き続き未確認。

2026-10-04: 両DBで最終FPM HTTP24項目・基盤40を各3回成功（31199 exit0）。最初の試験はHTTPステータスの理由句省略を扱えない検証側の正規表現で停止した。理由句がない行も受け付ける形へ修正し、Warningを伴う失敗を成功扱いにせず全試験を再実行した。Web製品コード/schema/API/UIへの変更はない。

保存するharnessの `-Rounds 1` でも両FPM24/基盤40を再確認し、既存PHP HTTP server mode23を両DBで回帰確認（35497 exit0）。専用appと配置確認済みtmpfs DBを除去し、最終prefix一覧は空。新PHP2構文・PowerShell parserも成功。

## メンテナンス設定との連携

`./tests/run-update-fpm.ps1 -Maintenance` は `TEST_UPDATE_MAINTENANCE=1` を追加し、専用生成管理者の実HTTP/CSRF/CASで全面停止を有効にしてから更新する。日英の匿名homeは503、管理者homeは200。実Engineの更新・内部health完了後も、DB設定と公開停止signalが維持され、匿名homeは503のままであることを確認する。

続いて更新後の管理APIで停止を解除し、匿名homeが200となってから実runner経由で旧版へ復元する。復元後も解除した最新version/DB false/private signal false/匿名home200、両方のMAINTENANCE_CHANGED監査を保持する。更新前の停止状態へ戻して後の管理者変更を失わないことの検証。匿名HTTPのCookie応答を管理者Cookie jarへ混ぜない。

2026-10-04: 専用MySQL8/MariaDB10.11のFPM33項目と基盤40を各3回成功（72186 exit0）。最後に各DBのPHP HTTP server mode32でも成功。新しい9項目と既存の更新/復元/変更PHP表示/config保持/履歴/stop drainを合わせた結果。PHP構文/PowerShell parser/git diff --check成功、専用FPM app/配置確認済みtmpfs DBを清掃しprefix一覧空。製品コード/schema/API/UIは変更せず、通常8099/8100の設定・DB・ユーザー権限・workerへ操作していない。

これは実HTTPでの停止→更新→解除→手動復元という経路。実ブラウザ表示、更新失敗の自動復元を停止中に起こす経路、独立複数FPM master、systemd運用、実GitHub/OAuthを今回の成功範囲へ拡張しない。
