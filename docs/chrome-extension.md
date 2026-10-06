# Chrome拡張（Phase 11・実装中）
## Phase11 Offline簡易表示・外部font抑制・locale補修（2026-10-06）

前ターン78d4d71は実HTTP両DB各3回/生成候補・Web1巡のprogress。ロード完了通知は未受領。今回Chromeの現在inventoryを読取、対象タイトルを持つchrome-extension/newtabタブは見つからず（インストール未実行の証明ではない）。既存Web検証tabをmarkHandoff、Chrome settings URL制約を迂回しない。

共通offline-mode.jsでaria-live status/端末内データを使う簡易表示、offline時のanimation/transition停止、onlineで復帰。同期通信停止/再開は前ターンの共通処理を維持。appearance.jsはonline状態をfont signatureに含め、OfflineでGoogle/custom font新規要求を出さず標準fontへ一時fallback、保存済みfont設定は変更せずonline/visibilityで再反映。これは外部fontの永続offline cacheではなく可読性を保つfallback。remote URL背景のoffline保存・全font/背景実動作は未確認・残件。

locale-core.jsとmodule化extension-shellを追加。Browser ja→ja/他→en、保存済みchoice優先、明示locale query優先で、localStorageが無効/古いchoiceのままでも手動enja切替がredirectで戻されない。固定2pageだけへ遷移、不正locale/外部destinationは受け付けない。spec83はbrowser初期判定を指定し、locale cloud保存を明記しない。地域は既存settings同期で実Chrome確認を残す。

Tests: 初期34138 JS37×3成功、追加後23614 exit0 JS39独立suite各3回/全JS構文成功。localeはblocked storage/explicit tab choice/invalid destination、Offline表示はネットワーク/設定読書きせずstatus1件/online復帰を検証。fontの実FontFace/Chrome通信はこの単体で成功扱いにしない。86093 exit0 PHP8.2/8.3生成185/基盤40各3回・構文成功、専用package prefix清掃済み。

生存中の専用app marker/workspace candidate bounds/linkを確認し、app/public/lang/extensionだけを反映（config/DB/test loginは保持）。実CLI74filesから既存候補8対象filesをdependency→consumer→HTML順で更新、manifest SHA不変を確認。対象Folderは引き続き.test-output/extension-mysql-20261006 / extension-mariadb-20261006、同じ単一test host/unlimitedStorage/NewTab権限。候補を読み込み済みならChrome側Reloadが必要な可能性を残し、新しい権限を勝手に付けない。

全検証handle終了、実UIの同じ4app/DBは維持、再生成/清掃はまだしない。通常環境/Secret/spec保持、DB/Migration/API変更なし。Phase11/12/V1未完・goal active。

次: この補修/限定証拠をcommit/push→ロード完了を確認して既存Chrome候補の実Profile/Cookie/日英/NewTab/console/設定・履歴・fav・palette・clock/greeting・地域・背景・offline復帰を検証。回答待ちを経過時間で完了としない。remote URL背景/font offlineの不足を合理的に解消し、最後にcleanup-extension-http.ps1で専用env/生成ユーザー/入口を清掃。39suite/生成185を実Extension DoD成功に広げない。
## Phase11実HTTP両DB各3回成功・実Chromeロード待ち（2026-10-06）

前ターン856d561は共通輸送/保存/既定設定のprogress。今回 fresh dedicated network search-extension-ui-20261006、app/DB exact4名、MySQL8/MariaDB10.11各tmpfs512MiB/extension_ui schema、新規configと通常権限の生成2ユーザーを準備。17Migration fresh/repeatを両DB確認・administrator0。通常8099/8100/config/Secretを使わず、公開HTTPは127.0.0.1:8115/8116だけ、DBにhost portなし。Weather外部呼出しはfixtureのみ無効。Appsはwww-data/cap-dropALL/no-new-privileges、host mountsなし。

Authのremember cookie名は固定なので、通常localhostのCookieを壊さない専用extension-mysql.localhost / extension-mariadb.localhostへ分離。CLI/API origin guardをRFC6761の予約localhost下位名にも対応（外部HTTP、credentials/query/subpathは引き続き拒否）。Chromeで実名前解決・専用Webログイン画面・生成ユーザーのホーム/初回cloud選択/初回案内保留を確認。WebのJS初期化・プロフィール/検索/palette/favoritesをAXで確認、証拠 .test-output/extension-web-ready.png。Web1巡であり実Extension/実OAuth/全UI各3回ではない。

失敗履歴: 25282はHTTP接続ECONNREFUSED。直接detach起動+ready待ち後8217も接続不可、13138の診断はcontainer内401/host ECONNREFUSED。専用bridgeへ変更した67936はhost HTTP404、Node Fetchがvirtual Hostを置換していた。HTTP検証側を固定loopback socketのnode:httpへ変更し、server発行Cookie/virtual Hostを保持（Browserではない）。28512は14項目通過後test画像のtype指定漏れINVALID_UPLOAD。各exit1/finallyで当該env清掃、製品成功とは扱わない。

修正後17219 exit0、実HTTP19項目×MySQL/MariaDB各3round成功。guest/user/admin拒否、Web/extension役の独立deviceが同じ生成userへ結合、public presets、CSRF403/正当write/同revisionWebread/409conflict、別user owner403/別userread隔離、offline無送信/復帰writeとWebread、multipartPNG upload/byte一致download/Webbackgroundread/別owner拒否/unsafe URL422。extension役はruntime contextとCookie jarをNodeで模擬し、実サーバーへ通信した証拠。Chrome privilege/SameSite/Cookie自動転送の証拠ではない。

4426 exit0 JS37独立suite各3回、35595 exit0 PHP8.2/8.3の生成181/基盤40各3回、PHP/JS/PS構文とdiff成功。sync-http/extension-httpは引数と専用fixtureが必要なので独立suiteから除外。検証worker handleは終了、実UI用4containersはKeepReadyで稼働を確認、これは終了後清掃済みとは記録しない。

実CLIから両72files候補を.test-output/extension-mysql-20261006 / extension-mariadb-20261006へ保存。MySQL候補はsingle http://extension-mysql.localhost/* host permission/unlimitedStorage/NewTab override、CSP connect exact8115、remote scripts/frameなし。旧localhost候補は履歴、今回の実ロード対象にしない。cleanup-extension-http.ps1を準備し、exact network membership/app marker/DB tmpfs確認後のみ4containers/networkを削除する（まだ未実行）。temporary loginはtests内のguarded routerから生成通常userのみ、public/出荷物へ入らない。

ユーザーへChrome内部設定URLはBrowser policyで操作できないため、MySQL候補フォルダーのLoad unpacked→NewTabを手動で行い完了通知するようasync質問。新しいタブ変更/限定test host通信/端末内保存を説明済み。回答・実ロード完了は未確認、Chrome settingsをCLI/CDP/native等で迂回しない。Web検証tabをmarkHandoff。現段階Phase11/12/V1未完成、goal active。

次: この実HTTPfixture・localhost分離・限定証拠をcommit/push。環境を再生成せず生存している同じ4名/hostから続ける。ユーザーのロード完了後に実Chrome NewTab/console/日英/設定・fav・history・palette・clock/greeting・背景/cloud同期・offline復帰を検証し、次にMariaDB候補も確認。remote URL背景/fontのoffline可用性・locale/地域共有の残件を監査。完了後にtemporary accounts/entry/envとtest extensionを清掃し、cleanup実行を記録。Loaderが必要なまま完了と推測しない。本番適用なし/spec非変更。
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
