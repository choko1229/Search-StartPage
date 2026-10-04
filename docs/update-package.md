# 更新配布物の作成と検証

GitHub Releasesへ添付するアプリ専用の更新配布物を作成・取得・検証する基盤です。Backup・更新適用・Migration・Rollbackへはまだ接続していません。作成済みでもUpdaterの完成や本番公開を意味しません。

リリース対象のコードを用意し、`VERSION` をそのリリースのバージョンにしたうえで、PHP実行ユーザーから `php bin/build-release.php` を実行します。Node/npm buildやZip拡張は不要です。非公開の `storage/updates/builds/` に一意な `.tar` を作り、ファイル名・バージョン・ファイル数・バイト数・SHA-256を返します。既存の配布物を上書きしません。このコマンドはGitHubへ公開・送信しません。

配布形式は、[GNU tarの標準ヘッダー仕様](https://www.gnu.org/software/tar/manual/html_node/Standard.html)を確認したUSTARの限定形式です。圧縮しない通常ファイルだけを含み、先頭に `release-manifest.json` を置きます。manifestはformat=1、version、php_min、各ファイルのbytes/sha256を持ちます。GitHubの自動生成source archiveや、任意のZIP/TARをこの検証器でそのまま適用するものではありません。公開側と取得側を同じ配布契約で接続する必要があります。

対象は `app/`、`public/`、`lang/`、`database/`、`bin/`、将来の `extension/` と、`VERSION`、`README.md`、`composer.json`、`config/config.example.php`、Git管理の標準検索先定義 `config/providers.php` です。`config/config.php`、セットアップキー、`storage/` 全体、Git管理情報、ユーザー提供の `spec.md`、進捗記録、tests、`public/_test/` は配布対象外です。管理画面の一時認証入口も出荷しません。資料・Docker設定・開発検証記録はこの更新配布物に含めません。

検証は既存のアプリ領域へ直接展開せず、新規の非公開stageへ行います。ファイル名・サイズ・SHA-256・全件の過不足・VERSIONとリリースタグ・PHP最低バージョンを確認します。正式SemVerの`v`接頭辞差だけを許容し、異なるbuild metadataのバージョンを同一扱いにしません。文字コードや大文字小文字による曖昧なファイル名、Windowsの予約名/代替stream、絶対パス、`..`、リンク、重複、PAX/GNU拡張、ディレクトリentry、非ゼロpadding、切れたarchiveを拒否します。パスは240文字以内の限定ASCII、最大10000ファイル/単体64MiB/内容合計256MiB、manifest2MiBです。VERSIONは512bytes以内です。

stageは0700、ファイルは0600で保持します。失敗したstageは除去し、除去できない場合も成功扱いにしません。保護領域を含む候補はmanifestでもarchiveでも拒否します。全ファイルの内部ハッシュは内容整合性の検証であり、配布者の署名ではありません。

GitHubUpdateAssetは選択済みrelease IDのasset一覧を100件ずつ最大20ページ読み、公開時の名前が完全一致する `search-startpage.tar` だけを1つ選びます。buildの一意なローカルファイル名は、公開する際にこの名前へ変更してください（この実装は公開しません）。state=uploaded、1536bytes以上で配布上限以内、GitHub metadataの `sha256:` digestが必須です。digestのない旧assetや複数候補は拒否し、自動生成source archiveを代用しません。任意のbrowser_download_urlは使用しません。

[GitHub公式のRelease Assets API](https://docs.github.com/en/rest/releases/assets?apiVersion=2022-11-28)に従い、固定APIへoctet-streamを要求して200直接配信と302転送を扱います。転送はHTTPSのrelease-assets.githubusercontent.comまたはobjects.githubusercontent.comへの1回だけ許容し、Tokenを転送先へ渡しません。その他のhost、認証情報付きURL、port、fragment、制御文字、連続転送は拒否します。cURLが必要で、TLS証明書確認、接続15秒/全体180秒/低速30秒制限を適用します。metadataは2MiB、headersは64KiBに制限します。

取得は新規private fileへのstreamで、metadataの正確なサイズと外側SHA-256を照合します。過大・途中切れ・改変は途中fileを除去し、既存fileは上書きしません。prepareは取得後に既存UpdatePackageのmanifest検証とstage展開へ接続し、検証失敗時にarchiveも除去します。成功したarchive/stageは後続Updaterが管理する非公開作業物です。取得元のGitHub metadataを信頼する整合性検査で、独立署名による配布者認証ではありません。

取得検証: 両隔離PHPで85項目（200/302、Token非転送、不正redirect、size/hash、pagination、失敗清掃、生成配布物の取得→stage接続）。実cURL/TLSは公式公開サンプルrepoの存在しないrelease IDで安全な404を確認した範囲です。対象repoの実asset取得成功・非公開Token認証・実CDNからのbinary配信は未確認です。管理画面からの取得/適用操作、backup/rollbackは未実装です。

検証: 両隔離PHP環境で危険な配布物・設定保持等63項目、実アプリ207ファイルのbuild→stage検証、独立したGNU tar一覧との一致、stage内PHP137ファイルの構文を確認しました。元の設定は変更されず、config実値/user storage/tests/一時入口はstageに含まれません。これらはローカルの生成物の証拠で、対象GitHubの実Download・更新適用・Rollbackの証拠ではありません。
