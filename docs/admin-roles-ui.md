# 管理者権限の実画面検証

2026-10-04、専用MySQL8/MariaDB10.11で生成operator/targetだけを使い、IABの製品管理画面から付与・解除・最後の管理者の解除拒否を確認した。MySQLは日本語、MariaDBは英語。ユーザーから操作時点の明示承認を受けて実行し、以前の自動承認拒否を無断再試行したものではない。

`tests/admin-roles-ui-development.php` はCLI/testmode、固定専用DB host、private markerに限定する。setupはconfigなし・空schemaを要求し全17Migration fresh/repeatを確認。seedはusers/administratorsが空の場合だけ生成2ユーザーとoperator用deviceを作る。observeは生成2ユーザーの権限、operatorの監査件数・対象/前後/版/file_writtenの一致boolだけを出力する。Secret・Cookie・token hashを表示しない。

準備は `tests/run-admin-roles-ui.ps1`。既存の専用Docker image `search-phase9-roles-20261004-app-mysql` と隔離networkを前提とし、名前が既存なら拒否する。DBはtmpfs512MiB/no host port/生成password、appはloopback8109/8110/config/storage tmpfs。設定雛形と現ソースのみcopy。setup後のadmin-roles29/基盤40を両DBで各3回成功（23966 exit0）し、最後にUI用の2ユーザーを生成する。成功時は画面確認用に環境を残すので、検証後に専用app/DBだけを清掃する。

ブラウザ入口は `http://roles-mysql.localhost:8109/_test/roles-login.php` と `http://roles-maria.localhost:8110/_test/roles-login.php`。`.localhost` はループバック。Cookieにはport分離がないため、通常開発タブとも異なる専用host名を使う。最初に127.0.0.1/localhostを共有して開いた時はログインが失われ、MySQLの最初の付与・MariaDBの最初の解除が401で拒否された。成功扱いにせず、DBの未変更を観測してから専用hostへ移した。共有host上の既存タブのCookieが非変更であるとは主張しない。

実画面の結果:

- MySQL日本語: target一般→管理者→一般、operatorの解除は日本語の「管理者を最低1人残してください」で拒否。desktopと390px、page375/viewport390。
- MariaDB英語: target一般→管理者→一般、operatorの解除は英語の「Keep at least one administrator」で拒否。390px、page375/viewport390。
- DB最終: 両方operator1/target0、operatorの監査2件、版13→15。対象/前後状態/版・file配送の一致boolがtrue。最後の管理者拒否は版を進めず変更監査を増やさない。
- IAB Console warn/errorは両方0。失敗したHTTP401は別に記録し、成功と混同しない。画像は `.test-output/roles-ui-granted-ja-mobile.png`、`roles-ui-granted-en.png`、`roles-ui-last-admin-ja.png`、`roles-ui-last-admin-en.png`。

viewport reset、新規テストtabを全close。DBのtmpfs配置を確認後、専用app2/DB2を除去し最終prefix一覧空。生成admin/user/device/config/login入口/logを清掃。通常8099/8100のDB・ユーザー権限・workerを変更していない。

製品コード/schema/API/UIの変更はない。実Discord OAuth、Chrome/Edge/Firefox/Safari全ブラウザ、複数端末の同期往復の証拠にはしない。Phase9全体とVersion1.0は未完了。
