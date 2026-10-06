# Chrome拡張（Phase 11・実装中）

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
