# 機能停止・復旧の英語画面検証

2026-10-04。Phase9進行中。今回の実画面はMySQL8・専用Apache・IABの証拠であり、実Discord OAuth/MariaDBブラウザ/全browser成功へ拡張しません。

## 隔離配置

専用DB `search-policy-ui-mysql-20261004` は空の `policy_ui` schema、tmpfs `/var/lib/mysql:rw,size=512m`、host portなし。専用app `search-policy-ui-app-20261004` の公開は `127.0.0.1:8108:80` のみ。config/storageも専用tmpfs。通常8099/8100のDB・設定・ユーザー・権限・workerに変更はありません。

最新のapp/public/lang/database/tests/bin、VERSION、公開config.example.php/providers.phpを専用appへ配置。tmpfs設定へのdocker cpは実mountに反映されなかったため、公開設定を `/tmp` にcopyしてコンテナ内からmountへcopyし直しました。Secretを含む通常configはcopyしません。

`tests/policy-ui-development.php` はCLI/testmode/固定専用host/marker/既存configなし/空DBを必須にします。`TEST_POLICY_UI_PASSWORD` は試験中に生成した一時値のみで、Gitや進捗に保存しません。全17Migrationを新規適用し、生成ユーザー・管理者・AuthRepositoryの通常remember deviceを作ります。専用 `_test/policy-login.php` はtestmode/local-development/markerを確認し、HttpOnly/Lax cookieを発行して通常管理画面へ移動します。検証後は入口・ユーザー・device・configを専用環境ごと清掃します。

## 実画面とDB照合

1. 英語の管理画面でCloud background uploads/WeatherをDisabledとして保存・再読込。通常画面の初回案内を閉じ、背景設定を開く。
2. OS filechooser APIは長時間応答後もinput.filesが空でした。Console errorなし。この操作は未確認として保持。既存 `tests/background-file-preview.php/mjs` を専用 `_test` のみに置き、生成68byte PNGを選択。保存ボタン・IndexedDB・製品のBackgroundSyncSession/APIは実処理です。OS chooser成功の代替証拠ではありません。
3. Sync ONで保存・Sync now。英語で管理者停止理由と端末変更保持/後の再試行案内。`tests/policy-ui-observe.php` の集計はuploads=false、cloud rows/files/bytes=0。390pxでviewport390/page375、ページ全体の横はみ出しなし。
4. 英語管理画面でuploadsをEnabledへ戻し、同じ製品画面からSync now。Synced表示、DB rows1/files1/bytes68。停止中の端末画像が復帰時に届くことを確認。
5. Appearanceから公開都市の地域35.68/139.69を手動入力。Region saved、保存警告なし。生成背景へWeather条件を保存し、Switching=Conditions。英語のweather停止理由を表示。実API POST /api/weatherの403を観測。
6. 英語管理画面でweatherをEnabledへ戻して製品画面をreload。地域両値を保持、停止理由解除、実weather POST200。DBの実sync_states.settings.themeRegionは公開テスト値と一致（観測出力は真偽値のみ）。現在地取得や実OS位置許可は実行していません。

## 回帰・証拠・清掃

- 検証1: 上記実UI/実DB照合。両タブConsole warn/error0。画像 `.test-output/policy-upload-disabled-en.png`、`policy-weather-disabled-en.png`、`policy-region-restored-en.png`。最終地域画像を目視確認。
- 検証2: 専用appで新PHP2本構文、admin-policy29、sync17、基盤40成功。全17Migration repeatをpolicy試験で確認。新Migration/製品API変更なし。
- 検証3: Node site-policy/weather-context/region-settings/sync-data28/sync-session37/background-sync-sessionとupload-intent/recovery成功。モデルでの故障注入と実UIの証拠を区別。
- viewport reset、一時tab15/16 close、専用DB tmpfs確認後に今回のexact2コンテナを除去。最終prefix一覧は空。生成記録・テスト管理者・入口・画像・config・DBを清掃。通常環境非変更、Secret非出力、spec.md非変更。

残件: 旧roles実UI、他端末の認証済みUI往復、過去の保存警告と今回の無警告の因果確認、全browser/MariaDB実画面、実OAuth/OS位置取得。今回の保存成功だけで過去の警告原因を断定しません。
