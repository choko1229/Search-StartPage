# 独立したブラウザ保存領域の同期検証準備

2026-10-04 再準備: 同じharnessが70392 exit0で完了し、両DB fresh17/repeat・sync17/基盤40各3回成功。専用app2/DB2は現在稼働中、両device2/cloud0/公開都市一致false。前回中断時のIAB空tab20だけ特定してcloseした。拒否navigationは再試行せず、生成ログイン→A→B→A公開テスト都市同期→停止復旧→清掃の具体的な再試行確認をユーザーへ提示し、回答待ち。下の「清掃済み」は前回の環境の記録で、今回の稼働環境とは区別する。read/close成功をnavigation review利用上限解消の証拠にはしない。

2026-10-04。Phase9進行中。実画面の往復同期は未実行。ブラウザの最初のnavigationが自動承認レビューの利用上限で拒否され、操作は実行されなかった。安全性の否認とは区別し、別ブラウザ・raw commands・直接通信で同じ画面操作を回避していない。

`tests/admin-sync-ui-development.php` と `tests/run-admin-sync-ui.ps1` は、固定専用DB host/CLI/testmode/marker/configなし/空schemaで準備する。MySQL8/MariaDB10.11専用DBはtmpfs512MiB/no host port、appはloopback8111/8112/config/storage tmpfs。全17Migration fresh/repeat、sync17/基盤40を両DB各3回成功（33177 exit0）。これはAPIの証拠であり、ブラウザ往復同期の成功ではない。

seedは生成アカウント1つに通常AuthRepositoryのdeviceを2つ作る。管理用の一時権限もこの生成アカウントだけ。テスト入口はHttpOnly/Lax remember cookieを設定する。既存タブとCookie・IndexedDBを混ぜないため、別の専用host名で開く。

- MySQL A: `http://sync-a-mysql.localhost:8111/_test/sync-a-login.php`
- MySQL B: `http://sync-b-mysql.localhost:8111/_test/sync-b-login.php`
- MariaDB A/B: 同様に `sync-a-mariadb.localhost:8112` / `sync-b-mariadb.localhost:8112`。

これは同一ブラウザ上の独立origin/保存領域で端末を模した検証。実物の2台の端末・別ブラウザ・実Discord OAuthの証拠にはしない。observeは生成device件数、同期可否、cloud版と公開テスト都市の地域一致boolだけを返し、Secret・Cookie・token hash・同期文書全体は出力しない。

計画: Aで公開都市の地域を設定→同期→Bの初回cloud選択/地域表示、Bで別の公開都市へ変更→Aで受信。生成環境だけでcloud_sync停止→端末変更保持→再開時送受信を確認する。helperのstop-sync/start-syncは生成adminを再確認して製品PolicyRepository/outboxを使うが、今回は未実行。過去の地域保存警告との因果は、この試験が成功しても自動的には証明されない。

中断時のobserveは両DBでdevice2/cloud_version0/地域一致false。ブラウザ操作やConsole・レスポンシブ・UIスクリーンショットは未確認。配置確認後に専用app2/DB2を除去し、最終prefix一覧空。生成user/admin/device/config/入口を清掃。通常8099/8100のDB/ユーザー/権限/worker非変更。再開にはレビューの利用上限が解消した状態で専用環境を新しく準備する。
