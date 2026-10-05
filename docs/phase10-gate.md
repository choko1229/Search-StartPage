# Phase 10 ゲート監査

最新サービス検証（2026-10-05）: live内guard配置の34831はfirst14/second5各3round・exit0と清掃成功。配置消失のリスクを解消するため、出荷unitのguard参照をOS管理の更新対象外pathへ変更し、root所有/非書込み・live側guard削除を追加した新VM63911でfirst16/second5各3roundを検証中。静的unit9/CLI25各3回8486 exit0、新配布4565とsoak11696/61720も実行中で全終了未確認。旧合格を変更後へ流用しない。実GitHub/Actions/実配布物通し検証は未達、Phase10進行中。

2026-10-04、Phase9の明記完了条件を照合後に正式開始。判定: **進行中**。先行実装を作り直さず、既存証拠と残件を照合する。Version1.0未完成。

| 添付完了条件 | 既存証拠 | Phase10で残る確認 |
|---|---|---|
| GitHub release check | REST取得、ページ上限、安全なエラー、24h cache/worker、実公開API応答。最新認証なしrepo/releases APIとも404、ユーザーが非公開repo/Token設定予定と回答 | 実候補取得成功・認証付き取得は未確認。404のみから原因を推測しない |
| channels | Stable初期値/Beta/Nightly/Customの選択・保持・競合検証、日英UI | 実配布元タグとの整合 |
| update download | 配布物生成・許可パス・実archive取得の専用fixture検証。canonical tar/tag一致/構文/互換性/sidecarの新準備CLIをPHP8.2/8.3で26項目各3回確認。手動artifact workflow作成/YAML解析済み | 対象GitHub asset取得と実Actions実行/配信は未確認 |
| verification | manifest/外側整合/PHP構文/health/私有stage/cache刷新、実FPM HTTP。独立2 master各2 child/同一live・実DBで更新と復元をHTTP37/基盤40各3回成功、親/子PID保持（53824 exit0） | 実公開配布物による通し検証 |
| backup | 更新時だけの旧世代保管、保護設定/uploads、journal/rescue | 運用配置での容量・所有者確認 |
| migration | 両DB fresh17/repeat、実Engine更新、transaction/復元と後発データ保持 | 実配布物によるmigration |
| rollback | 自動/手動、実CLI/HTTP/UI、旧PHP反映、後発fav/sync/log/policy保持。停止中の実Migration失敗→自動復元も専用両DB/FPMで29項目/基盤40各3回成功（1594 exit0）、停止version/signal/事前fav/sync/config/upload保持 | 実配布物による通し検証、故障原因全般の運用確認 |
| history | DB履歴/監査/失敗error同一transaction、再投影/配送 | 実配布物更新の履歴 |
| admin UI | 管理者check/通知/更新/復元、guest排除、CSRF/CAS、日英390px | MariaDB実UI更新/復元も日英/390px/worker停止で確認済み（docs/update-management-ui.md）。全browserはPhase12 |

主な証拠: docs/update-check.md、update-package.md、update-engine.md、update-database-merge.md、update-outcome-logsはdocs/admin-logs.md、update-fpm-http.md、update-management-ui.md、update-execution-service.md。各文書の初期「未実装」は当時の記録で、最新実装状態は本表とprogress.mdを参照する。

非公開repoの設定支援（2026-10-04）: 8099のconfigはDocker内、秘密値を出さないreadinessでToken未設定。bin/configure-updates.ps1 / phpを準備し、stdin非表示入力・他設定保持・private atomic保存を専用PHP8.2/8.3で15項目/基盤40各3回確認（5948 exit0）。実Token設定/実取得はユーザー設定後に確認し、今回の生成秘密試験を代用しない。docs/release-distribution.md参照。

次の具体的手順: 対象GitHub配布元のアクセス条件と実リリース配布経路を確認し、404や取得不能を成功・更新なしと扱わない。実asset検査・適用・復元の不足を監査する。秘密はGit/進捗へ記録せず、本番公開/pushは実行しない。systemd静的診断とCLIプロセス試験は実managerのboot/restartの証明ではない。今回の停止中失敗試験の詳細はdocs/update-fpm-http.mdに記録。

2026-10-05実manager追加証拠: network none/host mount・portなしの専用QEMU VM内で出荷unitを配置し、session82489の全3roundでfirst9/second5、両完了marker、exit0と清掃後prefix空を確認。子drain/正常停止/制御清掃/待機中異常終了後restart/正常停止後非restart/子出力非露出/二度目OS boot自動起動・停止を確認。出荷worker/unit非変更。生成子の短時間検証で、長時間運用・ExecStop故障・実GitHub配布物の通し検証は残る。Phase10進行中を維持する。

停止失敗対策の変更後（2026-10-05）: 出荷unitのExecStopを公開shell guardへ変更し、PHP stopが失敗してもMAINPID終了まで待つ。旧unitの82489通常成功は変更後の検証へ流用しない。静的unit9/既存CLI25各3回94340 exit0、新構文検査成功。旧直接stop負例と新guard正例の実VM比較は34831、実配布物へのLF guard含有はPHP8.2/8.3各3回19247で実行中。全終了は未確認。詳細はdocs/update-execution-service.mdとprogress.md。

最新追加: 19247 exit0、準備30/基盤40をPHP8.2/8.3各3回成功、専用prefix空。34831 round1・2は比較を含むfirst14/second5成功・清掃、round3実行中。継続workerの同一PID/RSS/FD/定期子/差替え/停止を確認する専用soakを追加し、16762 exit0で60秒7項目/12cycles両PHP各3回成功・清掃。www-dataの15分×3回本試験を独立並行開始（8.2=11696/8.3=61720）、全結果未確認。readinessはToken未設定・repo一致false、実GitHub/Actions/実配布物通し検証の残件は変わらない。
