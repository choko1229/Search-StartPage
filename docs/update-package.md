# 更新配布物の作成と検証

GitHub Releasesへ添付するアプリ専用の更新配布物を作る基盤です。Download・Backup・更新適用・Migration・Rollbackへはまだ接続していません。作成済みでもUpdaterの完成や本番公開を意味しません。

リリース対象のコードを用意し、`VERSION` をそのリリースのバージョンにしたうえで、PHP実行ユーザーから `php bin/build-release.php` を実行します。Node/npm buildやZip拡張は不要です。非公開の `storage/updates/builds/` に一意な `.tar` を作り、ファイル名・バージョン・ファイル数・バイト数・SHA-256を返します。既存の配布物を上書きしません。このコマンドはGitHubへ公開・送信しません。

配布形式は、[GNU tarの標準ヘッダー仕様](https://www.gnu.org/software/tar/manual/html_node/Standard.html)を確認したUSTARの限定形式です。圧縮しない通常ファイルだけを含み、先頭に `release-manifest.json` を置きます。manifestはformat=1、version、php_min、各ファイルのbytes/sha256を持ちます。GitHubの自動生成source archiveや、任意のZIP/TARをこの検証器でそのまま適用するものではありません。公開側と取得側を同じ配布契約で接続する必要があります。

対象は `app/`、`public/`、`lang/`、`database/`、`bin/`、将来の `extension/` と、`VERSION`、`README.md`、`composer.json`、`config/config.example.php` です。`config/config.php`、セットアップキー、`storage/` 全体、Git管理情報、ユーザー提供の `spec.md`、進捗記録、tests、`public/_test/` は配布対象外です。管理画面の一時認証入口も出荷しません。資料・Docker設定・開発検証記録はこの更新配布物に含めません。

検証は既存のアプリ領域へ直接展開せず、新規の非公開stageへ行います。ファイル名・サイズ・SHA-256・全件の過不足・VERSIONとリリースタグ・PHP最低バージョンを確認します。正式SemVerの`v`接頭辞差だけを許容し、異なるbuild metadataのバージョンを同一扱いにしません。文字コードや大文字小文字による曖昧なファイル名、Windowsの予約名/代替stream、絶対パス、`..`、リンク、重複、PAX/GNU拡張、ディレクトリentry、非ゼロpadding、切れたarchiveを拒否します。パスは240文字以内の限定ASCII、最大10000ファイル/単体64MiB/内容合計256MiB、manifest2MiBです。VERSIONは512bytes以内です。

stageは0700、ファイルは0600で保持します。失敗したstageは除去し、除去できない場合も成功扱いにしません。保護領域を含む候補はmanifestでもarchiveでも拒否します。全ファイルの内部ハッシュは内容整合性の検証であり、配布者の署名ではありません。今後のDownloadでは信頼するGitHub取得元と外側の配布物検証に接続します。

検証: 両隔離PHP環境で危険な配布物・設定保持等63項目、実アプリ207ファイルのbuild→stage検証、独立したGNU tar一覧との一致、stage内PHP137ファイルの構文を確認しました。元の設定は変更されず、config実値/user storage/tests/一時入口はstageに含まれません。これらはローカルの生成物の証拠で、対象GitHubの実Download・更新適用・Rollbackの証拠ではありません。
