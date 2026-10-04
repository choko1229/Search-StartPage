# 更新受付と履歴

UpdateCommandsは受付と履歴を担当し、更新自体は専用workerが実行する。管理POST・実行worker・Engine journalへの受付ID対応・管理画面の適用/復元操作は接続済み。実操作の検証範囲は docs/update-management-ui.md、キャッシュの検証範囲は docs/update-web-cache.md を参照。実GitHub配布元・サービス運用等の最終確認は残る。

## 保存と権限

UpdateCommandsはprivate directory内のcommands.jsonへformat 1の台帳を保存する。排他lock、revision一致、0600/0700、link拒否、1MiB上限、固定schema、fsyncとrenameを使う。履歴は20件、DB履歴は削除しない。Secret・認証token・任意URL・例外本文は保存しない。破損台帳は自動初期化せず拒否する。

受付時はpreparedを保存し、DB履歴と監査ログを同一transactionで記録してからqueuedにする。DB監査失敗は実行可能な状態にしない。claimは受付時と独立して現在の管理者権限とDBの不変metadataを再確認する。同じrevisionの二つのprocessは一つだけclaimできる。preparedで中断した場合も、DB記録が確認できる場合だけclaimする。

完了結果はprivate台帳へ先に保存し、DBへ投影する。DB記録失敗時のreconcileは記録だけを再試行し、更新を再実行しない。完了結果を別の結果へ変更したり、過去のqueuedへ戻したりするDB投影は拒否する。request/event/statusから作る監査event IDで再試行の重複を防ぐ。

固定エラーを持つ完了結果は同じtransactionで `update_error` にも保存する。決定的event IDでDB重複を防ぎ、DB復元で消えた保持対象の結果は再投影する。ファイル配送は管理ログ閲覧・整理時のoutbox再試行で行い、ApplicationLogger独自のpendingを消費しない。ファイルへの配送直後に中断すると重複し得るためevent IDを保持する。tests/update-outcome-logs.phpで実DB挿入失敗時のtransaction rollback・再投影・file lock故障/復旧・queue分離を検証する。

Migration 017_update_historyはspecのid/from_version/to_version/channel/status/created_at/completed_atに、受付ID・操作・リリース識別・要求者・固定error codeを追加する。requested_byにユーザー削除cascadeを付けず、アカウント削除後も更新履歴を保持する。SQLはUpdateHistoryRepositoryに限定しPDO prepared statementsを使う。

## 管理画面

GET /admin/updateとGET /api/admin/updateは既存の認証・サーバー側管理権限検証を通す。APIは最新20件の履歴を返すが、repository/release_id/requested_byを履歴項目へ公開しない。画面は日時UTC、操作、バージョン、channel、結果とエラー説明を日英で表示し、保存値をescapeする。

狭い画面では履歴表だけが横スクロールする。領域に名称とtabindexを付け、キーボードでも移動できる。成功した更新がまだない場合は空履歴を表示する。検証用の失敗履歴は試験後に削除し、実際の成功履歴として残さない。

## 検証

- 専用tmpfs DBのMySQL 8/MariaDB 10.11で受付・履歴43項目。権限失効、同時claim、監査DB障害時のtransaction rollback、prepared中断、結果投影の再試行、破損保持、履歴件数、アカウント削除、Migration往復を確認。
- 同じ両DBでDB snapshot51、runtime23、engine26。新規Web Installer40、全17Migrationの初回/再実行/往復を確認。engineのclone限定fault fixtureは018/019へ移動した。
- 両開発アプリの実HTTP管理更新36項目。guest401/非管理者403/CSRF403/入力拒否/権限失効/DB履歴/日英/保存値escapeを確認。
- ブラウザ外観確認は実HTTPが返したHTMLからhidden値を除いた一時snapshotで実施。実Discordログインや認証済みブラウザ操作の成功を示す試験ではない。Chromeで日英、390px、ページ横はみ出しなし、領域のキーボード横移動、console warn/error 0を確認。初回の文字単位の折り返しを表内スクロールに修正。一時公開snapshotを清掃、viewportを戻した。
- 最終の両配布物は224files / 3421696bytes / PHP154構文検査成功。独立tar一覧/hash/config不変、private storage・tests・public/_test非包含を確認。基盤40/HTTP停止復帰30/journal43/rescue17も両環境で成功。

## 次の接続条件

初期の接続条件であったrequest/job IDの永続対応、HTTP応答後の排他実行、DB復元後の結果再投影、隔離Apacheでの実HTTP停止/更新/復帰は後続実装・検証で確認済み。FPMのcache単体検証をFPM経由のEngine/DB更新成功へ拡張せず、サービス運用・実外部連携とともに最終監査へ追跡する。

CLI workerとrequest/job IDの対応付け・DB巻戻し後の再投影は実装・検証済み。管理POST/Apache実Web更新/管理画面の実操作も後続検証済み（docs/update-runner.md、docs/update-requests.md、docs/update-management-ui.md）。PHP8.3 FPM/Nginx経由Engine/両DB更新も各3回24項目確認済み（docs/update-fpm-http.md）。上の初期検証記録を現在の未接続条件として扱わない。独立複数FPM masterでの実DB更新・サービス運用・実配布元は引き続き未確認。
