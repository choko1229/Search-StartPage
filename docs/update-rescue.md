# 更新中断時の独立復旧入口

UpdateEngineは更新候補の検証後、管理対象ファイルを変更する前に、復旧用コードをprivateな `storage/updates/rescue/<32桁ID>/` に保存する。`config.php`、ユーザーデータ、アップロード、liveのautoloadはコピーしない。通常packageのstorage除外により、更新やRollbackがこのコードを上書きしない。

復旧用のクラスは固定19ファイル。全PHPを別processで構文検査し、0600/0700、size/SHA-256付きruntime.json、launcherを同期保存する。pointerとlauncherは同directoryのtempからrenameする。既存のengine lock内で生成し、rescue専用lockの排他下でpointer更新と旧capsule清掃を行う。確認済みの新pointerができるまで旧capsuleは消さない。復旧依存は更新process内でも先にロードし、差し替え後のliveコードの遅延autoloadを避ける。

launcherは `storage/updates/rescue.php`。通常WebのDocumentRoot外に置き、CLI以外では404を返す。配布元の `bin/update-rescue.php` は直接実行しない。

```text
php /absolute/install/path/storage/updates/rescue.php status
php /absolute/install/path/storage/updates/rescue.php recover
php /absolute/install/path/storage/updates/rescue.php rollback
```

`recover` は更新中断・復元失敗の再試行。Journalに従い、変更開始後のjobはfile/DBの両snapshotを復元し、旧healthが通るまで停止を維持する。complete後のcleanup中断なら新healthとcleanupを再試行する。`rollback` は成功した更新の直前1世代への手動復元。両方ともEngineのロックとGateの排他を取得する。実行前に通常の更新processが終了していることを確認する。稼働中のwriterや内部childが保持するロックを解除・削除してはいけない。

launcherはlive app/autoload/Engine/Journal/UpdateDatabaseを読み込まず、rescue lockの共有下で固定allowlist、private permission、リンク不在、descriptor形式、全file size/hashを検査してクラスをロードする。共有lockは全クラスロード後に解放し、それからEngineのlockを取得する。この順序で通常prepareと逆順のロック待ちを起こさない。configはDB回復時だけ、保護された通常config.phpから読む。結果はphase/versionだけで、例外本文や認証値は出力しない。

hashは保存内容の破損を検出するもので独立署名ではない。同じOSユーザー権限でコードとmetadataを書き換えられる攻撃者への防御を保証しない。private storage自体の消失・改変、config破損、DB権限不足などがある場合は成功を推定せず停止を維持し、保存物や環境を修復してから再試行する。次回prepareで新しいcapsuleへ切り替え、旧/未完成capsuleを削除する。常時のユーザーデータbackup機能ではない。

## 検証範囲

tests/update-rescue.phpは両PHP環境で17項目成功。生成/tmpだけで独立起動、live PHP破損、hash/manifest/path/link/permission拒否、秘密非出力、不正prepare時の旧pointer保持、旧世代清掃、実processの公開lock待機と逆順lock待ち回避を確認する。tests/update-engine.phpは専用tmpfs MySQL/MariaDBと実source cloneで更新を中断し、liveのautoload/Engine/Journal/UpdateDatabase/update-taskを壊した後、private launcherだけで全managed hash・DB rows/schema・config/uploads/manual maintenanceを回復する。更新失敗後にも以前の成功ownerからprivate launcherで手動復元する試験を含む。

Web OPcache/FPMのキャッシュ刷新、実HTTPでの更新適用、管理UI/適用worker/DB監査履歴への接続、Windows native/networkFSと完全な停電時directory fsync耐久性は未確認。CLI復旧成功をこれらやVersion1.0 DoD全体の成功扱いにしない。

CLI適用worker/DB監査はdocs/update-runner.mdのとおり接続・検証済み。管理HTTP操作/実Web OPcacheはまだ未確認。live PHP破損時はprivate rescueで先にコード/DBを回復してからworkerでDB監査を再投影する。
