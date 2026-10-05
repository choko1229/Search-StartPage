# GitHub Releasesの配布準備

2026-10-05配置変更後: unitのguard参照をOS管理の更新対象外/usr/local/libexec/search-startpageへ変更後、4565 exit0。PHP8.2/8.3で準備30/基盤40各3回成功、専用prefix空を確認。配布guardの同一LF内容・新unit参照、全4channelsの既存契約を実stageで確認。OS側への実設置は専用VM試験で別に確認する。実GitHub/Actions/配信の成功ではない。

2026-10-05追加検証: 停止失敗対策の公開shell guardを追加後、19247 exit0。PHP8.2/8.3で準備30項目/基盤40を各3回成功し、専用prefix一覧空を確認。4channelsの実tarを別stageへ展開し、同一LF guardとunitのExecStop参照を確認。既存のtag/hash/private保護/拒否/清掃も成功。GitHub Actions/実asset取得・公開の証拠にはしない。

Updaterの取得契約は、選択したリリースのassetにある **search-startpage.tar** 一つ。GitHubの自動source ZIP/TARやActions artifactのZIPを代わりに添付しない。manifestとVERSIONはリリースタグに一致させる。正式SemVerのv接頭辞差だけ許可する。Stableは正式SemVer、BetaはNightly以外のSemVer、Nightlyはnightly/devの区切り付きタグ、Customは設定タグ完全一致。チャンネル判定と配布物のバージョン検証を区別する。

## ローカル準備

リリース対象の固定ソースでVERSIONをタグに合わせ、PHP8.2以上から実行する。出力先はソース外の既存親ディレクトリ配下で、新しいディレクトリ名を指定する。

```sh
php bin/prepare-release.php v1.0.0 /tmp/startpage-release-1.0.0
```

この例のバージョンは手順説明であり、現在のVersion1.0完成・リリースを意味しない。コマンドはタグ一致を確認後、canonical tarを生成し、manifest/内部ハッシュ/全PHP構文/更新停止・cache・worker/journal互換性をstageで検証する。成功時はstageを削除し、search-startpage.tar、SHA-256 sidecar、release.jsonの3ファイルだけ残す。新規ディレクトリ0700/ファイル0600、既存出力は拒否し、検証失敗時には今回の出力を清掃する。出力先のsymlinkやソース内への出力は拒否する。

config/config.php、セットアップキー、storage/ユーザーデータ、spec.md、進捗、tests/一時入口は従来builderの対象外。CLIはDB/OAuth/GitHub Tokenを読み込まず、GitHubへ接続・公開しない。稼働環境ではなく固定ソースから作成する。

## GitHub Actionsでの準備

`.github/workflows/release-package.yml` はworkflow_dispatchのみ。既存のrelease_tagを指定するとそのtagのソースをcheckoutし、PHP8.2/8.3で同じCLIを実行してActions artifactへ保存する。contents:read、checkoutのpersist-credentials:false。Releaseの作成・公開・assetアップロード・push・tag作成は行わない。

Actionsからダウンロードしたartifact ZIPを展開し、検証したtarをGitHub Releaseに正確な名前search-startpage.tarで添付する。実際の公開は別の明示操作。公開後にGitHub Release Assets APIが返すsize/state/digestとローカルSHA-256を照合し、Updaterで取得→適用→復元を専用環境で確認する。sidecarは利用者の照合資料で、Updaterが信頼するGitHub metadataのdigestを代用しない。

参照: [GitHub workflow構文](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax)、[checkout](https://github.com/actions/checkout)、[upload-artifact](https://github.com/actions/upload-artifact)、[Release Assets API](https://docs.github.com/en/rest/releases/assets?apiVersion=2022-11-28)。workflowのYAML/ローカルCLI検証と、実GitHub Actions実行・公開・配信検証は別の証拠として扱う。

## 非公開配布元

2026-10-05補足: App\Config::getのアプリと同じ既定値で確認し、Token未設定、実効repositoryは対象に一致、repository明示キーは省略されている。raw配列でのrepo一致falseは既定値を評価しない観測だったため、配布元不一致として扱わない。設定helperはアプリが読むupdates.repository/tokenを正しく保存する。値を出さないreadinessは認証取得の成功を証明しない。

現在の8099開発サイトはDocker内の `/var/www/app/config/config.php` を使い、ホスト側config/config.phpとは別。2026-10-04の値を出さない読取り確認ではupdates.token未設定。リポジトリのPowerShellで次を実行すると、非表示の対話入力でそのコンテナだけへ保存できる。

```powershell
./bin/configure-updates.ps1
```

別の開発コンテナには `-Container`、別repoには `-Repository owner/repo` を指定する。既定は現在の8099コンテナ。値はコマンド引数やホストのファイルに書かず、標準入力でPHPへ渡す。コピーするのは公開の補助PHPだけ。設定のrepository/tokenだけを変更し、Discord/DB/site/session/channel/custom_tag等は保持する。configを0600で原子的に保存し、完了メッセージにはTokenを含めない。空入力は公開repo用のToken解除。

実Tokenを入力するPowerShell対話とDockerコピーはユーザーが実行する。この作業では実設定を変更していない。CLIだけの設定変更は別FPM/ApacheのOPcacheを直接消せないため、timestamp確認が無効な環境では運用手順に従ってWeb側の設定反映を確認する。アプリ/workerを自動再起動しない。

2026-10-04の認証なし実APIでは対象repoとrelease一覧の両方404。ユーザーが非公開repoと回答し、Tokenを設定する予定。404単独から非公開/不存在を判定したものではない。Git除外config/config.phpのupdates.tokenへ対象repoを読めるTokenを設定する。TokenはFrontend/チャット/進捗/Gitへ記録しない。設定済みの実configを配布準備用コンテナへコピーしない。

認証付きrelease候補取得、実assetのsize/digest/CDN取得、実GitHub Actions実行は未確認。Token設定後にもassetが未公開・digestなし等なら、その条件を解消してから検証する。未確認のままPhase10やVersion1.0を完了と扱わない。

## 検証記録

2026-10-04: tests/run-release-preparation.ps1 / tests/release-preparation.php、90204 exit0。ネットワークなし・DBなし・host mount/公開portなしの専用PHP8.2/8.3で準備26項目/基盤40を各3回成功。4種類のtag/VERSION契約、実CLI生成/size/SHA-256/sidecar/private mode/stage除去、秘密/ユーザーデータ除外、tag不一致/既存出力/ソース内出力/親symlink/不正PHP候補拒否と失敗清掃を確認。元の生成config/data非変更。

設定補助の検証: `./tests/run-release-preparation.ps1 -Configuration`、5948 exit0。専用PHP8.2/8.3で設定15項目/基盤40各3回成功。生成秘密のstdin入力/出力非露出、他設定保持、private mode/temporary除去、空Token、不正JSON/型/キー/長さ/repo/Token/引数拒否、未installed/公開権限/symlink拒否、configの予期しない出力の抑止を確認。PowerShellは構文解析済みで、実ユーザー対話・実Token保存・認証取得の成功証拠ではない。専用コンテナ清掃済み。

PHP構文/PowerShell parser/git diff --check成功。workflowはYAML parserで構文・手動trigger・contents read・PHP matrixを確認し、実GitHub Actions実行とは扱わない。bundled Python/Nodeにparserがなく、pnpm取得は証明書検証で失敗し停止した。Git除外の検証領域へWindowsの証明書検証有効HTTPSで公式registry packageを取得しSHA-512照合してparserを使用。TLS検証を無効にしていない。製品にNode/parser依存を追加していない。専用コンテナ清掃後prefix一覧空。
