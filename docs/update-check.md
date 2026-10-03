# 更新確認の基盤

GitHubの[Releases REST API](https://docs.github.com/en/rest/releases/releases)からリリース一覧を取得し、候補を選ぶ処理を実装しています。現段階の入口は、アプリの実行ユーザーで実行する `php bin/check-updates.php` です。管理画面、24時間cache/通知、Download・検証・Backup・Replace・Migration・Rollbackはまだ接続していません。この確認だけでUpdater完成にはなりません。

Git除外の `config/config.php` に任意の `updates` 設定を追加できます。`repository` は `owner/repo`、`channel` は `stable`（初期値）、`beta`、`nightly`、`custom` のいずれかです。`custom_tag` はcustom使用時の完全一致タグです。`token` は非公開リポジトリを確認する場合のサーバー専用認証情報で、チャット・進捗・Gitへ記録しないでください。対象のリリースを読める権限を与えたTokenを使用し、公開リポジトリでは空のままにできます。設定の例は `config/config.example.php` にあります。

Stableは公開済みの正式SemVer、BetaはNightly以外のSemVer（正式版への昇格を含む）から最新を選びます。`v`接頭辞とbuild metadataに対応し、数値の大小とprerelease順序で比較します。Nightlyは`nightly`または`dev`を区切り付きで含むタグの公開日時で選びます。Customは指定タグの完全一致だけを選びます。draftはどのチャンネルでも除外します。チャンネル命名を変更するときはリリース生成側とこの選択ルールを合わせる必要があります。

取得先は検証済みowner/repoの `api.github.com` に固定し、HTTPS証明書を検証します。TokenはAuthorizationヘッダーだけに入れ、URL・結果へ含めません。redirectは追従しません。100件ずつ最大20ページ、応答最大2MiBで、不完全な一覧や不正JSONはエラーとして扱います。上限に達した場合も、途中の候補を「最新」と表示しません。

成功結果は現在のVERSION、チャンネル、候補、更新候補の有無です。404は`UPDATE_SOURCE_NOT_FOUND`、確認できたrate limitは`UPDATE_RATE_LIMITED`、通信/その他HTTP失敗は`UPDATE_SOURCE_UNAVAILABLE`として終了コード1を返します。404は未公開・存在しないリポジトリなどを区別できず、「リリースがない」「最新版」とは扱いません。取得済みURLやrelease metadataだけを根拠に、更新ファイルを実行する処理はありません。

2026-10-04: 両隔離PHP環境で44項目と基盤40項目成功。実HTTPでは公式文書の公開サンプル`octocat/Hello-World`が0件で成功、現在の更新元`choko1229/Search-StartPage`は404。対象リポジトリのアクセス条件は確認待ちです。認証付き実API、実リリースのDownload/適用・Rollbackは未確認です。
