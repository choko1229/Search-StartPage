# 一括更新・復元エンジン（内部CLIサービス）

UpdateEngineは非公開のCLIサービス。管理画面・更新workerからの適用入口、CSRF/管理者監査/update_historyはまだ接続していない。公開・本番適用の完了判定ではない。

## 呼び出し契約

- `new UpdateEngine(root)`：rootは固定のインストール先。config.phpに設定したDBだけを使う。外部の接続値や任意job pathを受け取らない。
- `apply(archive, manifest)`：信頼するGitHub releaseの取得/外側digest検証を済ませた配布物。成功はjob.phase complete、失敗から復元できた場合はrolled_backと固定error codeを返す。取得前の失敗は固定例外、復元失敗はUPDATE_ROLLBACK_FAILEDと停止状態を維持する。
- `recover()`：engine lockを取得して、停止・中断したjobを検査する。変更前ならキャンセル＋旧health、変更開始後なら両snapshotを戻す。complete後の清掃失敗なら新healthを確認して前進する。
- `rollback()`：直前1世代へ手動復元。最新の試行が失敗済みでも、履歴から成功世代のownerを再アクティブ化する。完了後は世代を消費する。
- `status()`：private journalの状態。UIでは停止markerと合わせて判定する必要がある。

第2引数のcheckpoint Closureは内部の故障試験用で、HTTP入力から渡さない。SQLはDatabase/Migrator/UpdateDatabaseに置き、Controller/Viewへ追加していない。

## 保存と適用

private engine lockがpreflightからcleanupまでapply/recover/rollbackを直列化する。Journalがjob IDを生成し、manifestの正規化JSONのSHA-256をjobに固定する。job内のcandidate.tar/manifest.jsonを新規0600 fileへ同期保存し、candidateを展開、構文・停止互換性を検査する。

Gateの排他取得で既存writerをdrainしてから旧healthとfile/DB snapshotを保存する。これはbackup中に失われる更新を発生させないための短い書込み停止でもあり、通常の手動maintenance設定は変えない。snapshotをfsync/hash検証してからbacked_up、変更前にreplacingを永続化。差し替え後は別PHPでmigrateとhealthを実行し、復元用manifestを再検証してcompleteへ進む。

file failure/Migration failure/health failureはrolling_backへ移り、fileとDBを独立して復元する。fileの復元が失敗してもDBは試みる。両方と旧healthが通った場合だけrolled_backにし、停止を解除する。失敗時はrollback_failedとmarkerを残し、修復後のrecoverを可能にする。

中断がreplacing/migrating/checkingなら保守的に旧世代へ戻す。途中成功を推定して更新を続行しない。保存したmanifestが改変されていればjobのhashと不一致になるため、復元対象リストとして使わない。

## 直前1世代の清掃

成功世代pointerはhealth後にだけ切り替える。新世代のfile/DB hashとmanifestを検証してから旧jobを削除する。保持するのは成功ownerのfiles.tar/database.jsonl/manifest.jsonだけ。failed/rolled_back jobの物理作業物は清掃し、journal metadataは最大20件保持する。次の更新失敗でも以前の成功世代を維持する。

cleanup failureは停止を維持する。complete後なら更新を再度戻さず、recoverで新healthとcleanupを再試行する。通常の手動maintenance=trueは復元・healthでも保持する。

## 検証・残る範囲

tests/update-engine.phpは通常配置を変更せず、実配布物のcloneと専用tmpfs DBだけを使う。全16本の初期Migration、cloneだけの17/18番fixtureでDDL/行変更、正常apply、manual rollback、世代置換と失敗後の旧世代保持、部分file failure、DDL後exception、view health failure、実process exit7後の別instance recovery、破損file snapshot/manifestで停止維持とDB回復・修復後retry、config/upload/manual maintenance保持を確認する。

Journalはmanifest hashを含むformat2。未公開のformat1は自動初期化・自動変換せず安全に拒否する。通常隔離アプリにjournalがないことを確認し、この変更で既存世代を消していない。秘密・SQL・例外本文をjournalへ保存しない。

書き込み不可の管理directoryはbacked_upでpreflightし、replacingより前にUPDATE_TARGET_NOT_WRITABLEで拒否する。旧healthとcleanupが成功すれば停止を解除し、file/DBは変更しない。通常の隔離開発アプリはwww-dataからroot/appへ書き込めないことを確認した。通常配置の権限は変更しておらず、実Web更新に対応した配置設計は残る。

最終tests/update-engine.phpはMySQL 8/MariaDB 10.11で各23項目成功。書き込み不可の配置から安全に復帰する試験も含む。ファイル単体44、journal43、package64、stage29、asset94、access34、task20、基盤40も両環境で成功。

web OPcache刷新、FPM/実HTTPの更新適用、任意の将来releaseで破損したアプリコードを使わず起動できる独立rescue入口、管理UI/適用worker/DB監査履歴は未接続・未確認。完全な停電時のdirectory fsync耐久性、Windows native/networkFSも未確認。現在の確認をVersion1.0 DoDの合格へ拡張しない。
