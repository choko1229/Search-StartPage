# 更新workerの常駐と停止

`bin/systemd/search-update-execution.service` は配布物にも含むLinux/systemd用の設定例。配置先 `/srv/search-startpage`、PHP CLI `/usr/bin/php`、Webと同じ実行ユーザー `www-data` を実環境に合わせて変更する。Webとworkerが同じliveコード/config/storageを参照し、同じユーザーで更新可能な配置で使用する。通常のreadonly開発環境では起動しない。

設定をサービス管理ディレクトリへコピーし、daemon-reload後にenable/startする。これはこの作業から本番へ自動配置しない。サービスは起動時に定期workerを開始し、異常終了のみ再起動する。stopは同じlive配置の `php bin/update-execution-worker.php --stop` を実行し、現在の子処理が終了してworkerが停止するまで待つ。TimeoutStopSec=infinityは更新中の強制終了を避けるため。停止中は完了までサービスを待ち、更新プロセスを手動でkillしない。

直接CLIでも `--stop` を使用できる。停止要求はprivate control fileと現在のsingleton lock内のinstance IDに結び付ける。実行中の子を打ち切らず、待機中はポーリング間隔を待たず停止する。停止要求を確認した後に次の子は開始しない。ID/stop markerは終了時に消す。既に停止中/停止済みへのstopも成功する。明示的な新しい起動は前instanceの有効なstop markerを清掃する。破損/symlink/公開権限は固定エラーで拒否し、対象外のファイルを変えない。

子の出力を公開しない既存ルールは保持する。予期しないプロセス消失やOS停止はgraceful stopの保証外であり、既存Runner/Engineのjournal/rescueで回復する。候補は `--stop` の互換性も検査し、停止を持たない版への更新を拒否する。

実プロセスの25試験で、実行中の子の完了待ち、待機中停止、次の子の抑止、restart、stopの冪等性、制御破損時の失敗を確認済み。これは短時間の常駐/CLI停止試験。Windows/Docker内にsystemdがないため、このunitの実enable/boot/restartは未確認。長時間運用とサービスマネージャー経由の停止は別途確認が必要。設定例の存在だけで自動起動成功扱いにしない。実Engine処理中のstopは専用Apache/両DBのHTTP試験で各23項目成功。更新中にstopを発行し、実更新完了/HTTPの新PHP表示、手動復元/旧PHP表示、履歴/config保持を確認した。取得archiveだけfixtureを使う。
