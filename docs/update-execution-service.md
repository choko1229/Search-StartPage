# 更新workerの常駐と停止

最新soak再試験（2026-10-05）: 旧11696/61720はround2のFD peak条件で不合格exit1/finally清掃。初回の瞬間値との差4以内という条件は、子を作るproc_openの一時pipe数をリークと区別できない。95326の高頻度60秒診断はwindow底値6一定/peak12（file6 pipe6）/初回baseline8を観測。元失敗の数値は未保存なので、その原因を同じ瞬間差だと断定しない。

新判定は10秒window（生成子の完了・待機を複数回含む）のFD底値が全windowで一定であることを要求する。peak値と型別数値も保存し、path/引数/秘密は出さない。上限を単に増やす変更ではない。コピーしたworkerへ毎cycle閉じないfileを注入する負例を34160/96749で両PHP各3回検証し、底値[9,10,12,14]の増加を検出して各exit0/清掃。未closeを製品へ入れていない。

通常900秒×3は20197/42770で再試験開始、www-data・生成子のみ、全結果未確認。旧合格/負例合格を新通常合格の証拠へ流用しない。RSS/定期子/途中source差替え/正常stop・制御清掃/固定状態・子出力抑止は維持する。

更新対象外guardの最終VM結果（2026-10-05）: 63911 exit0。全3roundでfirst16/second5・両marker成功、finally清掃後に専用prefix空を確認。root所有/非書込み・live側guard削除後の正常停止、旧直接stopの生成child中断負例と新guardのchild完了/失敗表示/明示修復復帰、異常終了後restart、正常停止後非restart、子出力非露出、二度目OS boot自動起動/停止を確認。各round前のcommand4項目各3回も成功。出荷unit/guardのこの配置を実managerで確認した証拠であり、任意のOS故障や実GitHub配布物更新の証拠へ拡張しない。以下の実行中という記録は当時の履歴。

2026-10-05新配置の途中結果: 63911 round1でfirst16/second5・両marker/清掃成功、round2実行中。root所有のguardが更新対象外に残り、live内guard削除後も通常停止・故障比較/明示修復復帰・二度目OS bootが成功。全3round/最終exit/全清掃は未確認。新参照の実配布4565は準備30/基盤40を両PHP各3回成功・exit0/専用prefix空。

soak本試験11696/61720は両PHP round1の900秒7項目/176cycles/3555samplesを成功。最大RSS KiB 8.2=23780/8.3=24012、最大FD各8、warm基準の上限内。source差替え・正常stop/制御清掃・失敗とprivate出力なしを確認。両round2実行中で全3回/終了/全清掃は未確認。生成子の独立15分試験であり、同じworkerを45分連続稼働した証拠や実DB/通信・日単位の耐久ではない。

最新の配置修正（2026-10-05・検証中）: 配布物内の `bin/systemd/stop-update-execution.sh` は更新や手動復元で削除され得るため、インストール済みunitはそこを参照しない。OS管理者が公開guardを `/usr/local/libexec/search-startpage/stop-update-execution.sh` へコピーし、親directory root:root/0755・script root:root/0644で配置する。出荷unitのExecStopもこの更新対象外のpathを参照する。Web/worker実行ユーザーには親directory・scriptへの書込みを与えない。サイトの更新処理はOS管理のunit/guardを自動変更しない。配置先PHP/site pathを変更する場合はguardのPHP呼出しを合わせる。Unix改行を維持し、unitの配置・enable前にguardが存在し読めることを確認する。本作業でホスト/通常アプリへこの配置を行わない。

前のlive内guard方式で34831はfirst14/second5各3round・両marker・exit0/清掃成功。旧直接stopの子中断、新guardでの完了待ち/失敗表示/明示修復復帰を実証した。これは上記の更新対象外pathの動作証拠ではない。新unitの静的9/既存CLI25各3回8486 exit0。新配置のVM試験ではroot所有/非公開書込み・live内guard削除の2項目を追加し、first16/second5各3roundを別に確認する。実配布準備も新参照で再検証中（4565）。

継続稼働の専用試験: `tests/run-update-worker-soak.ps1` はPHP8.2/8.3のnetwork none/no host mount・port/cap-drop ALL/no-new-privilegesコンテナへ、公開workerと生成試験だけをコピーする。既定はwww-dataで15分×3回、`-Php 8.2` / `-Php 8.3` で別コンテナの独立実行を選べる。実DB/config/Tokenなし。各回で同じPIDの生存、warm後RSS増加8MiB以内/FD増加4以内、定期子処理の継続、途中の公開コード差替えが次子へ反映、停止/制御清掃/固定状態・秘密出力抑止を確認する。生成子は100msで完了し、時間はfixtureの総稼働時間。実Engine/DB/通信を15分稼働させる証拠や、日単位の耐久・無期限無故障の保証ではない。

2026-10-05予備確認: 16762 exit0、旧harness(root)の60秒7項目/12cyclesを両PHP各3回成功、専用prefix空。www-dataへの変更とPHP選択を追加し、15分×3回の本試験を独立並行開始（8.2=11696、8.3=61720）。両構文成功、round1実行中で全結果/終了/清掃は未確認。予備の成功を15分実行の証拠へ流用しない。

2026-10-05停止失敗対策（検証中）: 出荷例のExecStopを `/bin/sh /srv/search-startpage/bin/systemd/stop-update-execution.sh` へ変更。既存PHPのstopを呼んだ後、systemdが渡すMAINPIDの終了を待ち、stopの終了コードを保持する。stopが失敗しても更新中の子を見守るsupervisorが終了する前にmanagerへ戻らない。通常のKillModeやRestart/TimeoutStopSecは保持。unitと一緒に公開scriptを配置し、配置先/PHPパスを変更する場合はscript側も合わせる。scriptは実行権限不要、Unix改行を必須としGit属性でLFを指定する。

MAINPIDの終了を待つため、stopの書込みが失敗したままsupervisorが稼働し続ける場合は停止要求が待機し続ける。運用者が保存領域/所有者/制御の不具合を修復し、同じworkerへ正常なstopを再発行する。更新中のプロセスをkillして解消しない。shell自体の強制終了やOS/電源喪失を保護する保証ではない。

静的unit9/既存CLI25各3回は94340 exit0。最初の故障試験88608はexit1/finally清掃で、末尾ログだけでは失敗項目を特定できず原因を断定しない。失敗マーカーを先に保存する表示を追加し、生成drop-inで旧直接stopと新guardを同じVMで比較する試験へ拡張。34831で全3round実行中、first14/second5の全成功・終了・清掃はまだ未確認。旧unitで成功した82489の証拠を変更後の証拠へ流用しない。

最新のVM記録（2026-10-05）: 検証用proof unitをjournal+console出力へ変更し、独立したVM限定のprocess/unit観測を追加した。session82489は全3roundでfirst boot9項目/second boot5項目と両完了marker成功、終了exit0。各専用コンテナをfinallyで清掃し、専用prefix一覧空を確認。各round前のcommand4項目を各3回成功。実managerの通常起動・子drain・停止・待機中異常終了後restart・二度目OS bootの自動起動/停止を確認した。過去の部分成功・途中停止を全合格として扱わず、旧停止原因も未確定。

観測はPID/PPID/PGID/SID/STAT/wchan/commとunit状態に限定し、引数・環境変数・configは読まない。出荷unit/workerは変更しない。診断fixtureはVM markerがある専用guest内のみ動く。長時間運用/ExecStop故障/実配布物更新をこの短いmanager試験で証明しない。下記の「未確認」や実行handleは当時の履歴であり、最新状態はこの冒頭とprogress.mdを参照する。

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
