# 更新・復元の管理受付

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
