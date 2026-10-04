# 更新排他中のMigrationと動作確認

`UpdateRuntime(root).run('migrate'|'health', version, access)` は `UpdateAccess.exclusive` のcallback内で呼ぶ。`UpdateProcess.guardedScript` が親の排他access.lockとoperation.lockのstreamを子のdescriptor 3/4へ継承する。markerだけ、通常shared lease、異なる配置のlock、未継承のCLIでは許可しない。

`bin/update-task.php` はCLI限定で、autoload/config/DBより先に `UpdateAccess.authorizeInherited` を実行する。継承したFDとrootのlockのdevice/inodeを照合し、独立streamの共有access取得が失敗することで排他状態を検査する。childはlockをunlockせず、継承streamの参照をshutdown loggingまで保持する。親が終了してもchild終了までは排他とoperation所有権が維持される。既存の通常migrate CLIはこの入口へ変更していない。

protocol 1、exact VERSION、installed、必須環境を検査。migrateは新しいクラスからMigratorを実行する。healthは全Migrationの集合・checksumを検査し、通常bootstrapと同じroutesを構築して/api/healthのDB成功応答を確認する。日本語・Englishのhome viewも描画する。bootstrapの内部CLI probeはHTTPから有効にできず、手動maintenanceのDB値・signalを変更しない。healthの内部route検査は通常HTTPでの全routeやJS成功の代替ではない。

成功応答はformat/protocol/task/version/pidと件数のみ。親UpdateRuntimeがexact schema、protocol、version、異なるPID、整数上限、ja/en双方の描画bytesを検査する。HTML、例外本文、設定値を返さない。script失敗のstderrは固定 `UPDATE_TASK_FAILED`。成功応答が不正なら `UPDATE_TASK_INVALID_RESPONSE` を返す。

## 配布物の補修

実コピー＋空DBのMigrationで、標準検索先の `config/providers.php` が配布物に含まれていない不具合を検出した。ProviderPresets、統計分類、015 Migrationが使うGit管理の静的定義なので管理対象allowlist・builder・必須manifestへ追加した。config.php/setup key/storage/user settingsは対象外を維持。管理者によるpreset変更はDBに保持される。静的定義を欠く以前の開発用archiveは現在の検証では拒否する。

## 検証と制約

- 両環境 `tests/update-task.php` 20項目。未許可のconfig非読込、別配置/shared拒否、実child、親のSIGKILL後もchildがaccess/operationを保持し、終了前のrecovery拒否・終了後復旧を確認。
- 専用tmpfs空DBのMySQL8/MariaDB10.11で `tests/update-runtime.php` 23項目。実配布物のcloneだけを変更し、全17 Migration初回/再実行、routes/DB health/日英view、manual maintenance保持、checksum故障、実DDL後例外の部分状態と停止、正常healthによる回復、JSON/protocol/PID/version/schema拒否を確認。専用DBとcloneは清掃済み。
- 両通常隔離アプリのaccess HTTP30、access34、stage29、package64、asset91、file38、journal40、基盤40。実配布物217files/PHP147構文、独立tar一覧、config不変、保護領域非包含。

継承FD/lockの検証はLinux Dockerで実施。WindowsネイティブPHPやnetwork filesystemは未確認で、未対応なら安全に拒否する。FD capabilityは同じOSユーザーによるconfigの任意変更を防ぐsandboxではない。候補のHTTP/worker停止protocol、実web OPcache、更新engineとのfile+DB一括復元、job中断回復、直前1世代清掃、管理UI操作・監査履歴はまだ未接続。新MigrationのDDL失敗に自動復元できたという試験ではない。
