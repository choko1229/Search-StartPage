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

## 隔離VMによる実manager検証

`tests/run-update-systemd-vm.ps1` はQEMUのソフトウェアエミュレーションを使う。外側のDockerはnetwork none、capability全除去、no-new-privileges、host mount/公開portなし。内側のVMもネットワークカードなし、共有フォルダなし。ホストのsystemdやWindowsサービスへ登録せず、VM内だけで出荷unitを配置する。Windows再起動は行わない。

Ubuntu24.04の公式cloud imageをHTTPS取得し、同じ公式配布元のSHA256SUMSと照合する。検証用PHP8.3の実行ファイルと依存libraryは専用imageからVMへコピーし、製品依存へ追加しない。NoCloud seedには公開worker/unitと生成試験だけを入れる。実config/DB/Tokenは使用しない。

実PID1がsystemdであることを必須にし、enable/start、実ExecStopによる子の完了待ち、停止後のsingleton清掃、待機中workerの強制異常終了後のRestart=on-failure、正常停止後の非再起動、journalへの子出力非露出を確認する。その後VM自体を再起動し、enabled unitの自動起動と停止を確認してpoweroffする。試験の子処理は生成fixtureであり、実Engine更新中の停止の証拠は別の両DB HTTP試験を参照する。長時間運用や任意障害の証明へ広げない。

各roundは新しいqcow2差分diskとseed ISOを使い、終了時にコンテナとともに除去する。完了markerをfirst bootとsecond bootの両方で確認し、QEMUの終了コードだけを合格としない。ベースimageは検証専用Docker imageに残る。

再現性のため、guestのreboot要求でQEMUを終了させ、同じ永続diskを別のQEMUプロセスで冷起動する。ネットワークなしVMのwait-onlineだけをkernel起動引数でmaskし、製品unitは変更しない。初回63363は通信待ちの設定修正で途中停止。88735ではfirst boot9項目が成功したが、再起動の観測が遅く途中停止した。停止直前に次kernel起動も出たため再起動失敗とは断定しない。現在55011で冷起動方式の全3roundを実行中、合格・清掃はまだ未確認。

参考: [Ubuntu公式cloud images](https://cloud-images.ubuntu.com/noble/current/)、[cloud-init NoCloud](https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html)。この段落は検証手順の説明で、実行成功記録は別途追記する。

2026-10-05追記: 55011のfirst boot9項目とsecond boot PHP/自動起動/設定制約3項目は成功。second bootの検証用oneshot内でsystemctl stopを同期実行し、起動完了と停止の依存順序で待ち合わせた。生成VMを意図的停止（exit1/finally清掃）し、bootstrapの検証用unitのみType=simpleへ修正。出荷unitは変更せず、50725で全3round再試行中。現在round1 first marker9成功/QEMU生存確認、second/全3round/清掃未確認。これを全サービス運用合格としない。

2026-10-05再追記: 50725もfirst boot9項目/second boot3項目成功後、同期stopが完了しなかった。Type=simple変更のみで原因・解消を断定しない。生成VMを意図的停止（exit1/finally清掃）、second bootをstop --no-block後のinactive最大60秒観測へ変更し、unit状態の診断を追加した。Result success/制御清掃/identity空の合格条件は維持、first bootの同期drain試験も維持。66830で全3round再試行中、成功は未確認。これまでの部分成功・修正意図を全合格へ拡張しない。

2026-10-05出力回収追記: 66830でもfirst9/second3成功後、検証用unit Type=simple/SubState=running/Job空の状態で停止確認が完了せず、起動依存のみを原因と断定しない。意図的停止exit1/finally清掃後、commandの出力を非同期で回収し、対象process終了後は子孫が継承したpipeのEOFを待たない方式へ変更。出力上限/60秒限度と固定エラーを追加。seed/env/marker限定の短いプロセス試験4項目を各3回成功（66698のround1準備）。同じ66698で全3roundのVMを継続中、全サービス合格・停止原因確定ではない。出荷unit/worker非変更。
