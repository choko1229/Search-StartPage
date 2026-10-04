# 更新確認の基盤

GitHubの[Releases REST API](https://docs.github.com/en/rest/releases/releases)からリリース一覧を取得し、候補を選ぶ処理を実装しています。入口は管理者画面 `/admin/update` と、アプリの実行ユーザーで実行する `php bin/check-updates.php` です。24時間cache・定期worker・管理者通知にも接続済みです。Download・検証・Backup・Replace・Migration・Rollbackは未実装で、この確認だけでUpdater完成にはなりません。

Git除外の `config/config.php` に任意の `updates` 設定を追加できます。`repository` は `owner/repo`、`channel` は `stable`（初期値）、`beta`、`nightly`、`custom` のいずれかです。`custom_tag` はcustom使用時の完全一致タグです。`token` は非公開リポジトリを確認する場合のサーバー専用認証情報で、チャット・進捗・Gitへ記録しないでください。対象のリリースを読める権限を与えたTokenを使用し、公開リポジトリでは空のままにできます。設定の例は `config/config.example.php` にあります。

Stableは公開済みの正式SemVer、BetaはNightly以外のSemVer（正式版への昇格を含む）から最新を選びます。`v`接頭辞とbuild metadataに対応し、数値の大小とprerelease順序で比較します。Nightlyは`nightly`または`dev`を区切り付きで含むタグの公開日時で選びます。Customは指定タグの完全一致だけを選びます。draftはどのチャンネルでも除外します。チャンネル命名を変更するときはリリース生成側とこの選択ルールを合わせる必要があります。

取得先は検証済みowner/repoの `api.github.com` に固定し、HTTPS証明書を検証します。TokenはAuthorizationヘッダーだけに入れ、URL・結果へ含めません。redirectは追従しません。100件ずつ最大20ページ、応答最大2MiBで、不完全な一覧や不正JSONはエラーとして扱います。上限に達した場合も、途中の候補を「最新」と表示しません。

成功結果は現在のVERSION、チャンネル、候補、更新候補の有無です。404は`UPDATE_SOURCE_NOT_FOUND`、確認できたrate limitは`UPDATE_RATE_LIMITED`、通信/その他HTTP失敗は`UPDATE_SOURCE_UNAVAILABLE`として終了コード1を返します。404は未公開・存在しないリポジトリなどを区別できず、「リリースがない」「最新版」とは扱いません。取得済みURLやrelease metadataだけを根拠に、更新ファイルを実行する処理はありません。

2026-10-04: 両隔離PHP環境で44項目と基盤40項目成功。実HTTPでは公式文書の公開サンプル`octocat/Hello-World`が0件で成功、現在の更新元`choko1229/Search-StartPage`は404。対象リポジトリのアクセス条件は確認待ちです。認証付き実API、実リリースのDownload/適用・Rollbackは未確認です。

## 管理画面と定期確認（2026-10-04）

管理者は `/admin/update` でチャンネルを保存して手動確認できます。APIは `GET /api/admin/update`（保存済みの状態）と `POST /api/admin/update`（CSRF必須の確認）です。POSTのJSONは `channel`、`custom_tag`、直前のGETが返した整数 `revision` を送ります。古いrevisionは409、無効な設定は422、取得失敗は502/503です。現在のPOSTは確認専用で、ファイルの更新を行いません。

チャンネルと取得結果は非公開の `storage/updates/checks/check.json` に0600で原子的に保存し、専用lockで複数の確認を直列化します。Tokenは保存しません。configの取得元・Token・初期チャンネルやVERSIONが変わると古い結果を無効にします。破損したmetadataは503を返し、更新なしとして扱いません。復旧時はworkerを停止し、このmetadataを非公開領域へ退避してから再確認してください。configやユーザーデータは削除しないでください。

管理画面と、サーバーが現在の管理者権限を確認したホームだけに新バージョンを通知します。通常ユーザーやguestに通知しません。管理者の手動確認は `UPDATE_CHECK_REQUESTED` として監査へ先に記録します。これは確認の試行を表し、更新の適用成功を意味しません。監査保存に失敗した場合は確認・設定変更を開始しません。

`php bin/check-updates.php` は保存済みチャンネルを今すぐ確認し、`--if-due` は成功後24時間のcacheを利用します。失敗後は1時間の再試行間隔があります。`php bin/update-check-worker.php` は常駐して期限に合わせて確認します。実行ユーザーはアプリと同じものを使用します。workerは二重起動を拒否し、設定を毎回読み直します。確認の成否・更新候補の有無・次回までの秒数だけを標準出力へ返します。エラーは既存の安全なupdate_errorログに記録します。

隔離Dockerのoverlayは `docker/update-checks.compose.yaml`、profileは `update-checks`、サービスは `updates-mysql` / `updates-mariadb` です。既存の専用composeに重ねて、`--no-deps` で対象workerだけを起動します。configはreadonly、storageは専用共有volume、公開portはありません。Docker停止中は確認できません。本番ホストの定期処理は未配置です。

両環境の保存/期限/競合/安全な失敗/監査失敗39項目、HTTP権限/CSRF/日英/管理者ホーム通知/権限解除/監査/実取得31項目が成功。実画面は通常Authの生成管理者で日英手動確認を行い、Stable→Betaの保持、再度Stableへ復元、390pxの横溢れなし、Console0を確認。一時ユーザーと入口は清掃済み。両workerが起動して実取得失敗・次回3600を返し、二重起動拒否を確認しました。24時間境界と失敗1時間境界は時計を置換した試験であり、実24時間の経過や本番運用の証明ではありません。Download・検証・Backup・Replace・Migration・Rollbackと、対象repositoryへの成功アクセスは未達のままです。
