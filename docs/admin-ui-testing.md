# 管理画面の実ブラウザ検証

管理者操作用は `prepare roles` を指定する。既存の検証用Discord IDや有効な管理者がいる場合は準備を拒否し、新しく生成した「Disposable role target」を一般ユーザーとして追加する。cleanupはこの対象もID/Discord IDを照合して除去する。通常prepareの用途は従来どおり。

2026-10-04: localhost8097の通常認証による日英users画面、390px/content375、Console0を確認。権限付与と最後の管理者解除を試すクリックは自動承認レビューが対象・権限・範囲の明示承認不足として拒否し、どちらも未実行。承認を質問中。迂回して操作せず、fixture/preview/keyを清掃して通常ログイン要求への復帰を確認。承認後は新しくprepareして検証する。画像 `.test-output/phase9-roles-mobile-ja.png` / `phase9-roles-mobile-en.png` は画面表示の証拠であり権限操作成功の証拠ではない。

CLIはWeb実行ユーザーで起動する（このDocker環境では`exec --user www-data`）。0600のfixtureをroot所有で作るとWebから読めない。Migration015以降はプリセットも退避・復元するため、UI検証の中断後もcleanupで元に戻せる。既存fixtureにプリセット退避がない場合はpolicyだけを復元する。

専用のローカルDocker環境だけで使用する。`tests/admin-ui-fixture.php` はCLIかつSEARCH_TEST_MODE=1を要求する。`prepare` は固定の検証専用ユーザー・管理者membership・通常のdevice/login_tokenを作り、ランダムなキーとCookieを非公開storageへ0600で保存する。キーは標準出力へ返すため、チャットやログへ表示せずGit除外の一時ファイルへ受け取る。

`tests/admin-ui-preview.php` をその専用コンテナの `public/_test/admin-ui-preview.php` に手動配置する。本番routeへは登録しない。SEARCH_TEST_MODE=1とSEARCH_LOCAL_DEVELOPMENT=1の両方が必須、fixtureがなければ404、準備から15分後は410。画面にキーを入力すると専用fixtureのHttpOnly Cookieを設定して管理画面へ移動する。製品のAuth.restoreとAdminMiddlewareは通常どおりDBのtokenと現在の管理者権限を検証する。実Discord OAuthの証明にはならない。

複数の専用DB環境を同じブラウザで開く場合はホスト名を分ける。Cookieはポートごとに分離されないため、例えば既存ゲスト環境を127.0.0.1、管理検証をlocalhostにすると別環境がログインCookieを無効にする干渉を避けられる。製品の認証検証を緩めて対処しない。

終了時に必ず `php tests/admin-ui-fixture.php cleanup` を実行する。退避したpolicyとプリセットを復元し、生成ユーザーと関連token/deviceを削除し、非公開fixtureを除去する。手動配置したpreviewとローカルのキー一時ファイルも除去する。管理画面を再読込し、ログイン要求へ戻ることを確認する。追加した監査記録は保持する。新しいprepareは既存fixtureがあると拒否する。

2026-10-04: 両DBでprepare/cleanupとPHP構文成功。実MySQL管理policyのJA保存/再読込/復元、JA/EN表示・390px幅、監査前後値とファイル保存済み表示、Console0を確認。127.0.0.1でのEN保存は認証要求となり未成功、localhostに分けてJA復元を再成功。EN保存・全管理画面の網羅・実OAuthは未確認。
