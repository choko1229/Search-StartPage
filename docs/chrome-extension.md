# Chrome拡張（Phase 11・実装中）
## Phase11共通API・Offline制御・サーバー別保存・既定設定共有（2026-10-06）

前ターン6b105b5は共有UI生成のprogress。今回共通api-transport.jsを追加、sync/background/metadata/suggest/weather/site-policy/statistics/palette account導線へ接続。Webは同じ相対API/same-origin、拡張はcompiled server originへcredentials include、cache no-store/redirect error固定。API経路は既知の検索/同期/背景/user/CSRF/logout/public presets等だけ、絶対URL/相対traversal/encoded path/制御文字/管理APIを拒否。任意URL proxy/message bridgeなし。ServerのCSRF/Auth/所有権検証は変更しない。ネットワーク輸送の単体証拠で実Chrome Cookie成功とは扱わない。

Offline時はAPI前にOFFLINE拒否、同期/背景同期/統計の通信を停止・local pending保持、onlineイベントで既存同期再開。外部suggestは止めてlocal候補維持。拡張のIndexedDB/BroadcastChannel/legacy keyはcanonical server originのSHA256で分離し、別サーバーの同じuser ID/checkpoint/private backgroundを再利用しない。Webの既存storage名と移行は維持。public provider-presets API（既存）を拡張でも使用、検証済みcacheで即時表示しfresh取得を同期前に待つ。custom providers/checkpointを上書きせず、取得失敗/不正応答でcache保持、復旧後共有。

生成CLI第3引数は公開server origin（既定HTTPS対象サイト）、HTTPはexact loopbackのみ。URL credentials/query/fragment/subpath/CSP注入を拒否、manifest version65535範囲。Manifestにsingle host permission/unlimitedStorageとconnect-src exact originを追加。Chrome host patternはportを絞れないのでCSPとAPI targetでexact portに限定。これは未インストール候補、アクセス承認済みとは扱わない。日英startup/login案内とlanguage header表示設定も接続。

Tests: 最初の全mjs一覧は引数/HTTPfixture必須のsync-http.test.mjsを誤って含めpath undefinedでexit1。製品不具合・HTTP成功として扱わない。独立suiteのみ35各3回26331、server storage分離追加後35各3回21195、その後public presets/cache/復旧試験追加の最終95741 exit0で37suite各3回/全JS構文成功。API testは生成mock応答でCSRF/owner/credential/target/offline/reconnect/401/503/background/weatherを検証、実ブラウザ/HTTPの代用ではない。

PHP生成56244は167/40各3回、39680は177/40各3回、現在の全資産94625 exit0で179/基盤40をPHP8.2/8.3各3回・PHP構文成功。PS parser/diff成功。network none/www-data/no host mount・ports/cap-dropALL、実config/DBなし、全検証handle終了・search-extension prefix空。実CLIから72files生成し.test-output/extension-sync-preview-20261006へ保存、6共通JSのホストhash一致・生成環境清掃。旧70files/transport71files候補は履歴、実ロードには使わない。最新candidateはcompiled localhost:8115だが、その専用HTTP/DB環境はまだ未作成。

DB/Migration/Server API route変更なし、公開bootstrap platform情報だけ追加。通常8099/8100/Secret/spec保持。Phase11進行中・Phase12/V1未完成、goal active。

次: この変更をcommit/push→localhost:8115のfresh専用DB/HTTP/Web+extension比較環境と生成通常ユーザー（管理者なし）を準備し、実HTTP成功/失敗・CSRF/所有権と清掃を検証→候補/権限/NewTab変更が具体的にreview可能になった後にChrome実ロードを扱う。内部Chrome settings URLのBrowser policyをCLI/CDP等で迂回しない。実Chrome Cookie/日英UI/Console/Offline操作・復帰/クラウド背景/地域・locale共有、remote font/URL背景のoffline可用性は未確認。37suiteや生成179を実Extension/Offline完了の証拠へ広げない。

現状は共有UIの静的パッケージ生成を検証した段階。Phase 11完了、実Chromeロード、認証同期、Offline機能の実操作成功とは扱わない。

`bin/prepare-extension.php` は `app/Views/home.php` / `favorites.php` とWeb版のJS/CSS/既定背景をそのまま使用し、日英のNew Tab画面を生成する。Manifest V3のNew Tab override、ローカル実行コードのみのCSP、iframeなし。現段階ではhost permission等の追加アクセスを要求しない。PHP/設定/ユーザーストレージ/認証情報は生成物へ含めない。

PHP 8.2以上で、ソース外の既存親ディレクトリ配下に新しい出力先を指定する。

```sh
php bin/prepare-extension.php /tmp/search-startpage-extension
```

出力にはmanifest.json、newtab.html、newtab-en.html、assetsがある。JS/CSSはWeb版と同じ内容で、PHPやNodeの常駐は拡張実行の依存にしない。既存出力/ソース内出力/親symlink/非静的assetを拒否し、生成失敗時に今回の新規出力だけを清掃する。

2026-10-06: tests/run-extension-package.ps1、修正後48487で160項目/基盤40、CLI実行検証追加後35485で163項目/基盤40をPHP8.2/8.3各3回成功、全PHP構文/PowerShell parser/diff成功。最初86730は既定providerの正規化漏れでWarningが出たため合格証拠にしない。修正後はWarningを例外にする検証。通信なし/www-data/cap-drop ALL/no-new-privileges/host ports・mountsなし、実config/DBなし。終了後専用prefix空。

実CLIで70filesの生成も確認し、Git除外 `.test-output/extension-preview-20261006` に保存、専用生成コンテナ清掃済み。このフォルダーは開発用生成結果で、実ロード済み拡張や完成版ではない。現在の相対API要求は拡張のサーバー接続へ未接続。API輸送、Webログイン/CSRF/同期、クラウド背景の取得、Offline時の外部要求抑制と復帰同期、初期設定/起動ページ案内、実Chromeロード/Console/日英操作が残る。New Tabの画面を複製して独立実装する方針にはしない。

参照: [New Tab override](https://developer.chrome.com/docs/extensions/develop/ui/override-chrome-pages)、[Manifest V3 CSP](https://developer.chrome.com/docs/extensions/reference/manifest/content-security-policy)、[cross-origin requests](https://developer.chrome.com/docs/extensions/develop/concepts/network-requests)、[storage and cookies](https://developer.chrome.com/docs/extensions/develop/concepts/storage-and-cookies)。API接続時にもCSRFや所有権確認を弱めず、任意URLの認証proxyを作らない。
