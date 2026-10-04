# 更新workerの常駐と停止

`bin/systemd/search-update-execution.service` は配布物にも含むLinux/systemd用の設定例。配置先 `/srv/search-startpage`、PHP CLI `/usr/bin/php`、Webと同じ実行ユーザー `www-data` を実環境に合わせて変更する。Webとworkerが同じliveコード/config/storageを参照し、同じユーザーで更新可能な配置で使用する。通常のreadonly開発環境では起動しない。

設定をサービス管理ディレクトリへコピーし、daemon-reload後にenable/startする。これはこの作業から本番へ自動配置しない。サービスは起動時に定期workerを開始し、異常終了のみ再起動する。stopは同じlive配置の `php bin/update-execution-worker.php --stop` を実行し、現在の子処理が終了してworkerが停止するまで待つ。TimeoutStopSec=infinityは更新中の強制終了を避けるため。停止中は完了までサービスを待ち、更新プロセスを手動でkillしない。

配置するunitは通常ファイル0644とし、実行権限を付けない。実環境のパス・ユーザーへ変更した後、起動前に `systemd-analyze verify --man=no /etc/systemd/system/search-update-execution.service` で診断がないことを確認する。終了コード0でも不明な設定名の警告が出る場合があるため、標準エラーも確認する。

直接CLIでも `--stop` を使用できる。停止要求はprivate control fileと現在のsingleton lock内のinstance IDに結び付ける。実行中の子を打ち切らず、待機中はポーリング間隔を待たず停止する。停止要求を確認した後に次の子は開始しない。ID/stop markerは終了時に消す。既に停止中/停止済みへのstopも成功する。明示的な新しい起動は前instanceの有効なstop markerを清掃する。破損/symlink/公開権限は固定エラーで拒否し、対象外のファイルを変えない。

子の出力を公開しない既存ルールは保持する。予期しないプロセス消失やOS停止はgraceful stopの保証外であり、既存Runner/Engineのjournal/rescueで回復する。候補は `--stop` の互換性も検査し、停止を持たない版への更新を拒否する。

実プロセスの25試験で、実行中の子の完了待ち、待機中停止、次の子の抑止、restart、stopの冪等性、制御破損時の失敗を確認済み。これは短時間の常駐/CLI停止試験。実Engine処理中のstopは専用Apache/両DBのHTTP試験で各23項目成功。更新中にstopを発行し、実更新完了/HTTPの新PHP表示、手動復元/旧PHP表示、履歴/config保持を確認した。取得archiveだけfixtureを使う。

2026-10-04追加: `tests/run-update-systemd.ps1` はPHP8.3/Debian Bookwormの専用imageへsystemd検査ツールを入れ、出荷unitを `systemd-analyze verify` で静的検査する。9項目には存在しない停止実行ファイル、無効Type、不明な設定名の負例を含む。同じ隔離環境のwww-dataで既存25プロセス試験と合わせ各3回成功。Windowsからのコピーでunitへ実行権限が付く警告は0644へ直し、全3回を再実行した。

このharnessはnetwork none、公開port/DB/host mountなし、全capability除去、no-new-privilegesで動き、finallyで専用コンテナを除去する。コピーするのは公開unitとworker/試験だけ。PID1はsleepでありsystemd managerを起動せず、ホストへunitを登録しない。imageには検査ツールを残す。PHPの配布例パスを合わせるsymlinkはimage内だけに作る。

未確認: サービスの実enable/OS boot/異常終了後のmanager再起動、manager経由の停止（ExecStop失敗を含む）、長時間運用。静的なWantedBy/Restart/TimeoutStopSecの検査をこれらの動作証明にしない。Linuxの隔離VMなどで、通常停止・処理中停止・異常終了・再起動後のsingleton/履歴回復を確認してから運用ゲートを閉じる。
