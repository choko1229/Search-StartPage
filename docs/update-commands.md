# 更新受付と履歴

現在は内部受付サービスと管理画面の履歴表示まで実装している。UpdateCommandsは更新を実行しない。管理画面からの適用/手動Rollback POST、実行worker、Engine journalと受付IDの対応付けは未接続。更新確認POSTは従来どおりリリース情報だけを確認する。

## 保存と権限

UpdateCommandsはprivate directory内のcommands.jsonへformat 1の台帳を保存する。排他lock、revision一致、0600/0700、link拒否、1MiB上限、固定schema、fsyncとrenameを使う。履歴は20件、DB履歴は削除しない。Secret・認証token・任意URL・例外本文は保存しない。破損台帳は自動初期化せず拒否する。

受付時はpreparedを保存し、DB履歴と監査ログを同一transactionで記録してからqueuedにする。DB監査失敗は実行可能な状態にしない。claimは受付時と独立して現在の管理者権限とDBの不変metadataを再確認する。同じrevisionの二つのprocessは一つだけclaimできる。preparedで中断した場合も、DB記録が確認できる場合だけclaimする。

完了結果はprivate台帳へ先に保存し、DBへ投影する。DB記録失敗時のreconcileは記録だけを再試行し、更新を再実行しない。完了結果を別の結果へ変更したり、過去のqueuedへ戻したりするDB投影は拒否する。request/event/statusから作る監査event IDで再試行の重複を防ぐ。

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

request IDとEngineのjob IDを永続的に対応付け、process中断後に別の更新結果を受付へ結び付けないこと。HTTPの通常leaseを解放した後、専用workerが排他を取得して更新を実行すること。DB restoreが監査履歴を巻き戻した場合はprivate台帳から正しい結果を再投影すること。Web OPcache刷新・書込み可能な隔離配置・実HTTPの停止/更新/復帰も検証してから管理画面の実行操作を有効にする。
