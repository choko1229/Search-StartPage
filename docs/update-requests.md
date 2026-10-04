# 更新・復元の管理受付

管理画面の操作は[update-management-ui.md](update-management-ui.md)。確認/取消/確定、worker準備状態によるボタン制御、明示的な状態再確認を追加し、専用MySQL/Apache/IABで実更新と復元まで確認済み。execution.worker_readyはprivate lockが実際にheldで、書込み可能な固定配置/停止markerなしの場合だけtrue。両受付/準備28、隔離管理HTTP56、両配布物232files/PHP160成功。PHP8.3 FPM/Nginx経由の実HTTP/Engine/両DB更新・復元も各3回24項目確認済み（[update-fpm-http.md](update-fpm-http.md)）。以下の古い未確認は以前の記録で、独立複数FPM masterでの実DB更新・サービス運用・MariaDB実ブラウザ/全browserは残る。

最新の停止検証: 専用Apacheと両DBのHTTP試験で、更新中のworkerへstopを要求し、実Engineの完了後に常駐processが終了することを各23項目確認。変更PHP/復元/履歴/config保持も確認。停止CLIの最終25試験、候補13probe/20試験、最終配布物231files/3486208bytes/PHP160成功。以下の22以前の数値は以前の記録。systemdの自動起動は未確認。

最新のWeb検証: [update-web-cache.md](update-web-cache.md)のとおり、専用Apacheと両DBで実更新/変更PHP画面表示/復元/旧PHP画面表示を各22項目確認。候補のWeb Cache/hookを必須にして12probe/19試験成功、最終配布物230files/PHP160構文成功。以下の20項目やApache未確認は以前の検証記録。FPM・サービス起動・ブラウザ実行操作は残る。

2026-10-04追記: 定期workerの別PHP子を使う実HTTP受付→適用→更新後HTTP→手動復元→復元後HTTPを専用tmpfsの両DBで各20項目確認した。applyの取得callbackだけfixture、rollbackは更新された本来のrun-update.phpを実行する。候補互換性はworkerの必須化と二重起動拒否を追加して11probe/17試験、asset94成功。最新配布物は229files/3474944bytes/PHP159構文成功。以下の17項目/15項目/228filesは前回の検証記録。サービス自動起動・長時間常駐・Apache/FPM OPcache・ブラウザ操作は引き続き未確認。

管理画面/APIの受付はUpdateRequestsを通す。リリース、repository、要求者、復元世代をリクエスト本文から採用しない。現在の認証ユーザーとサーバーに保存した更新候補・Journalから決定する。

## HTTP契約

GET /api/admin/updateのdata.executionにはcommand_revision、engine_revision、request、rollback、busyを追加した。既存data.revisionは更新確認のrevision。requestはID、操作、版、状態、時刻、固定errorだけで、actor/repository/release_idやprivate snapshotを公開しない。rollbackは保存済み世代のfrom_version/to_versionだけを返す。

仕様のPOST /api/admin/updateとPOST /api/admin/rollbackは次のJSONで更新/復元を受け付ける。POST /api/admin/update/applyとPOST /api/admin/update/rollbackも同じ受付の別入口。サーバー側管理権限とX-CSRF-Tokenが必須。

```json
{"command_revision":0,"check_revision":1,"engine_revision":0}
```

成功は202でdata.executionとDB履歴を返す。これは受付の成功であり更新完了ではない。WebのPOST /admin/update/applyとPOST /admin/update/rollbackは同じ値をフォームで受け、_csrfを検証し303で管理画面へ戻る。現在の画面は実行状態の表示だけで、実行ボタンはまだ追加していない。

更新確認はPOST /api/admin/update/checkと既存のPOST /admin/update。互換性のためPOST /api/admin/updateへ従来のchannel/custom_tag/revisionを送った場合も更新確認を行う。command_revision/check_revision/engine_revisionのいずれかを送った場合は更新受付として厳密に3項目を検証し、確認payloadと混在させない。

更新確認lock→Journal lock→受付lockの順でrevisionと選択を固定し、監査を記録してからqueuedにする。古い画面、同時実行、未完了の内部job、既にキューにある要求、配置VERSIONの変更を拒否する。本文にactorやto_version等を追加した場合も422とする。

applyは利用可能な確認済み候補が必要。rollbackはbaselineを持つ直前の成功世代を使い、GitHub確認の失敗を理由にローカル復元を阻止しない。実行時はworker/Engineが現在の管理権限・source/世代・snapshotを改めて検証する。

受付HTTPはダウンロード・Engine起動・ファイル差替え・DB復元を行わない。通常のHTTP leaseは応答/終了処理が完了するまで保持する。実行はbin/run-update.phpまたはUpdateRunnerの別の処理で行い、Engineがleaseをdrainして排他を取得する。定期起動・ブラウザ操作・実Web OPcache確認は未接続/未確認。

## 検証の範囲

tests/update-requests.phpは一時privateファイルで候補選択、revision、管理権限、内部job、重複、VERSION変更、GitHub失敗時のローカル復元受付とprivate情報非公開を確認する。監査callbackは試験用で実DBの証明ではない。

tests/admin-updates.phpは通常開発環境で未認証/非管理者/CSRF/空revisionを実HTTPで拒否する。そこで実行可能な要求を作成したり通常workerを起動したりしない。

tests/update-requests-http.phpは専用tmpfs DB、生成管理者/device、使い捨てアプリ、コンテナ内loopbackのPHP HTTP serverだけを使う。API成功202とDB履歴、秘密非公開、再送409、日英HTML状態、応答後の実Runner/Engine更新、更新後HTTP、別IDの手動復元、復元後HTTP・両履歴・config保持を確認する。release情報と取得archiveは試験用に供給し、認証・CSRF・受付・監査・ファイル/DB処理・HTTPは実処理。実Discord認証、本物のGitHub release、Apache/FPM OPcache、実ブラウザのボタン操作を代替する検証ではない。

候補互換性検査ではUpdateRequestsとUpdateChecksを必須にし、次の版にもwithState/withSelection/withStatusと受付protocol1があることを別processで確認する。これは信頼するreleaseの互換性確認であり、未知コードのsandboxではない。

最終結果: 両環境で受付24、管理実HTTP56、専用HTTP/実DB更新17、更新確認39/Journal52/候補互換性15/asset94/rescue17/HTTP停止復帰30/基盤40成功。実配布物228files/3469312bytes/PHP158構文、独立tar一覧/hash/config不変/保護領域除外も確認。新Migrationなし。専用DBはtmpfs配置を確認後に清掃した。
