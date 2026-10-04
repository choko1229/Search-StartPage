# Phase 10 ゲート監査

2026-10-04、Phase9の明記完了条件を照合後に正式開始。判定: **進行中**。先行実装を作り直さず、既存証拠と残件を照合する。Version1.0未完成。

| 添付完了条件 | 既存証拠 | Phase10で残る確認 |
|---|---|---|
| GitHub release check | REST取得、ページ上限、安全なエラー、24h cache/worker、実公開API応答。最新認証なしrepo/releases APIとも404、ユーザーが非公開repo/Token設定予定と回答 | 実候補取得成功・認証付き取得は未確認。404のみから原因を推測しない |
| channels | Stable初期値/Beta/Nightly/Customの選択・保持・競合検証、日英UI | 実配布元タグとの整合 |
| update download | 配布物生成・許可パス・実archive取得の専用fixture検証。canonical tar/tag一致/構文/互換性/sidecarの新準備CLIをPHP8.2/8.3で26項目各3回確認。手動artifact workflow作成/YAML解析済み | 対象GitHub asset取得と実Actions実行/配信は未確認 |
| verification | manifest/外側整合/PHP構文/health/私有stage/cache刷新、実FPM HTTP | 実公開配布物による通し検証 |
| backup | 更新時だけの旧世代保管、保護設定/uploads、journal/rescue | 運用配置での容量・所有者確認 |
| migration | 両DB fresh17/repeat、実Engine更新、transaction/復元と後発データ保持 | 実配布物によるmigration |
| rollback | 自動/手動、実CLI/HTTP/UI、旧PHP反映、後発fav/sync/log/policy保持。停止中の実Migration失敗→自動復元も専用両DB/FPMで29項目/基盤40各3回成功（1594 exit0）、停止version/signal/事前fav/sync/config/upload保持 | 実配布物による通し検証、故障原因全般の運用確認 |
| history | DB履歴/監査/失敗error同一transaction、再投影/配送 | 実配布物更新の履歴 |
| admin UI | 管理者check/通知/更新/復元、guest排除、CSRF/CAS、日英390px | 全browserはPhase12、実MariaDB UI更新は未確認 |

主な証拠: docs/update-check.md、update-package.md、update-engine.md、update-database-merge.md、update-outcome-logsはdocs/admin-logs.md、update-fpm-http.md、update-management-ui.md、update-execution-service.md。各文書の初期「未実装」は当時の記録で、最新実装状態は本表とprogress.mdを参照する。

次の具体的手順: 対象GitHub配布元のアクセス条件と実リリース配布経路を確認し、404や取得不能を成功・更新なしと扱わない。実asset検査・適用・復元の不足を監査する。秘密はGit/進捗へ記録せず、本番公開/pushは実行しない。systemd静的診断とCLIプロセス試験は実managerのboot/restartの証明ではない。今回の停止中失敗試験の詳細はdocs/update-fpm-http.mdに記録。
