# 実装と検証の記録

更新日: 2026-10-03

## Phase一覧

| Phase | 内容 | 状態 |
| --- | --- | --- |
| 1 | Core Foundation | 完了（2026-09-27、PHP 8.2 / 両DB / ブラウザ検証） |
| 2 | Search Core | 完了（両DB Migration、API、JS単体、ブラウザ主要操作） |
| 3 | Favorites | 完了（2026-09-29） |
| 4 | Discord Auth | 実装・機能検証済み（実Discord OAuthはユーザー指定により留保） |
| 5 | Cloud Sync | 機能ゲート検証済み（実OAuth・認証済み実UIは留保） |
| 6 | Appearance | 機能ゲート検証済み（環境依存の実確認は留保） |
| 7 | Background System | 機能ゲート検証済み（外部サービス・実ブラウザの未確認は留保） |
| 8 | Command Palette | 機能ゲート検証済み（実OAuth・環境依存の最終確認は留保） |
| 9 | Admin | 進行中（管理基盤・一覧・メンテナンス・ログ収集/保持・機能制御/動的制限を実装、残る管理機能とUI検証を継続） |
| 10 | Updater | 未着手 |
| 11 | Chrome Extension | 未着手 |
| 12 | Final Polish | 未着手 |

Version 1.0のDefinition of Doneは未達成です。未検証の項目を成功扱いにしません。

## Phase 1 作業記録

### 開始時

- 元のリポジトリには `.gitattributes` とユーザー提供の未追跡 `spec.md` のみ存在。
- 既存アプリ、DB Migration、テスト、AGENTS.mdはなし。
- 既存の `spec.md` は変更していない。
- PHP/MySQL/MariaDB/Composer/Dockerは当初PATHになし。
- ユーザーがDocker Desktopを導入。CLI 29.8.0は動作。
- Dockerログに `Virtual Machine Platform not enabled` / `No virtualization available`。
  Linuxエンジンが停止しており、Windows仮想化機能の有効化・再起動が必要。

### 実装内容・追加ファイル

- `app/autoload.php`, `app/Config.php`, `app/bootstrap.php`: Composer不要の起動・設定。
- `app/Router/Router.php`: パラメーター付きルート・Middleware・404/405・HEAD。
- `app/Http/*`: JSON入力・統一API形式・安全なローカルリダイレクト・セキュリティヘッダー。
- `app/Auth/Session.php`, `app/Middleware/Csrf.php`: strict session・再生成・CSRF。
- `app/Database/*`: native prepared statements・utf8mb4・バージョン判定・Migration。
- `app/Repositories/InstallationRepository.php`: 初期管理者IDの予約。
- `app/Services/*`: 環境確認、排他Installer、設定保存、秘密情報を含めないファイルログ。
- `app/Exceptions/ErrorHandler.php`: Warning/Notice/例外と致命的エラーの処理。
- `app/Controllers/*`: 基本ページ・health・言語切り替え・Installer。
- `app/Views/*`, `lang/*`, `public/assets/css/core.css`: ja/en画面・レスポンシブなInstaller。
- `public/index.php`, `public/.htaccess`, `.htaccess`: 公開入口と非公開ルート保護。
- `bin/*`: セットアップキー、Migration、開発サーバー。
- `config/config.example.php`, `.gitignore`, `composer.json`, `VERSION`: 設定雛形等。
- `docker/*`, `compose.yaml`, `.dockerignore`: PHP 8.2 / MySQL 8 / MariaDB 10.11。
- `tests/*`: 構文・単体・実DB/HTTP統合テスト・PowerShell実行手順。
- `README.md`, `docs/phase-status.md`: 導入手順・検証状況。

### DB変更

- `001_core.php`: `users`, `administrators`, `installation_claims`。
- Migration管理用 `migrations` テーブル。
- Discord ID / 管理者user_idのUniqueと管理者からusersへのFK。
- 残りの仕様テーブルは該当PhaseでMigrationとして追加する。
- MySQL DDLの暗黙コミットを考慮し、テーブル作成を再実行可能にした。

### API / UI

- `GET /api/health`, `GET /api/csrf`, `POST /locale`。
- `GET/POST /installer` に7段階のインストール。
- セットアップキー、30分の設定有効期限、古い画面/二重送信拒否。
- 設定済みconfigまたはinstalled.lockがあれば再導入不可。
- 基本ページ・共通エラーページ・日本語/英語切り替え。
- Phase 1にJavaScript実装はない。後続機能のダミーUIは置いていない。

### Tests

用意したテスト：

- `tests/lint.php`: PHP全ファイルの構文検査。
- `tests/run.php`: Router、Middleware、CSRF、翻訳、Escape、URL検証、ログ秘匿。
- `tests/integration.php`: DB Migration/再実行/空DBのdown/FK、Installer完走、
  セッション再生成、言語保持、JSONエラー、非公開パス拒否、設定生成、再導入拒否。
- `tests/docker.ps1`: 両DBの隔離環境で同じ検証を実行。

実行結果：

- Docker CLIのバージョン取得: 成功。
- `tests/docker.ps1` のPowerShell構文検査: 成功。
- config.php / install.key / storageログのGit除外確認: 成功。
- Docker Linuxエンジン: Windows Virtual Machine Platform無効のため起動失敗。
- PHP構文/単体テスト: 未実行。
- MySQL / MariaDB MigrationとHTTP統合テスト: 未実行。
- 実ブラウザの操作確認: 未実行。

### Security Notes

- 設定・アップロード・ログはDocumentRoot外。秘密情報はGit対象外。
- 全POSTルートにCSRF。セッション固定化対策とHttpOnly/SameSite Cookie。
- HTTP許可はlocalhost開発のみ。転送ヘッダーを無条件に信用しない。
- prepared statements・HTMLエスケープ・CSP・View/localeパスの許可リスト。
- セットアップキーはサーバーで生成。未認証の管理者アカウントは作成しない。
- OAuth実装はPhase 4。初期管理者IDの予約は認証を意味しない。
- エラーログにリクエスト、SQLパラメーター、例外メッセージ、Secretを保存しない。

### Issues / Next Phase Impact

- 実行検証で見つかった不具合を修正してからPhase 1完了を判定する。
- Phase 1未完了のため、仕様に従ってPhase 2へ進んでいない。
- 再起動後は `tests/docker.ps1` を実行し、両DBテストとブラウザ検証を継続する。
- ユーザーの依頼により `progress.md` に再開地点を保存し、`AGENTS.md` に開始時の必読・再開手順を追加。
- 最終DoDの全項目は未達成。Version表記は `0.1.0-dev`。

### 再開後の検証結果（2026-09-27 22:38 JST）

- Docker再起動後、Linuxエンジン正常動作を確認。以前の仮想化ブロックは解消。
- Composeプロジェクト `search-test-20260927223223`。
- MySQL 8 / MariaDB 10.11それぞれでPHP 8.2構文検査35ファイル、単体39項目、統合35項目すべて成功。
- Dockerのループバック公開ポート向けに明示的な開発用HTTP許可を追加。外部Hostは許可しない。
- 変更後、37 PHPファイル（生成configを含む）の構文検査成功。
- 独立UIコンテナ `search-phase1-ui` (`localhost:8082`) でInstallerをブラウザから7段階完走。
- ja→en切替、フォームのEnter送信、保存したHTML文字列のEscape、モバイル幅で横はみ出しなしを確認。
- ブラウザwarn/errorログ0件。通常リクエストのPHP Warning/Notice/Fatalなし。
- `tests/error-probe.php` による意図的Warningは安全なINTERNAL_ERROR JSONに変換され、内部メッセージは非表示。
- ファイルログの秘密メッセージ除外は単体テストで確認。
- Phase 1完了条件を確認し、Phase 2へ進む。OAuthそのものの検証はPhase 4で行う。

## Phase 2 作業記録（2026-09-28）

- 前提: Phase 1の全完了条件を確認。既存のInstaller等を回帰テスト。
- 追加: `config/providers.php`、`SearchController`、`SuggestService`、`002_search.php`。
- 追加JS: search / search-core / suggest / history / providers / search-settings / store / i18n。
- 変更: home View、bootstrap、Translator、ja/en、検索CSSとアイコン。
- Migration: search_history / search_engines / ai_providers。ユーザーFK、履歴日付Index、Prefix Unique。
- API: GET /api/search/suggest。空クエリ、過大クエリ、配列入力を検証。
- UI: Web/AI切替、Web10種・AI4種、カスタム検索先の追加/編集/削除/有効化/順序、起動モード、既定検索先。
- UI: Enter単独抑止、Shift+Enter、Alt+Enter、矢印/Enterによる候補実行、Prefix、URL候補、長文AI推奨。
- UI: ローカル履歴（300件・90日）、個別削除・全削除・保存ON/OFF、外部サジェストON/OFF。
- AI: Claude/Geminiはコピー案内を表示してからサービスへ移動。ChatGPTクエリ受け渡しを実画面で確認。
- Tests: プロジェクト `search-test-20260927225055` で両DBの構文41ファイル、単体39項目、統合35項目が成功。
- Tests: JS検索/URL/Prefix/履歴23項目成功。検索APIの4ケースが両環境で成功。
- Browser: カスタムWeb/AIの保存、Prefix、Shift/Alt+Enterの同一タブ遷移、履歴保存、AI推奨、URL候補、AIコピー案内を確認。
- 修正: 非同期候補の更新時に選択を維持。Esc後の遅延候補再表示を防止。
- Security: URLスキーム制限、DOM textContent、JSON_HEXエスケープ、外部リクエストの固定送信先/サイズ/時間制限。
- Apacheログはクエリ文字列を記録しない形式に変更。
- 未ログイン履歴/検索先は端末内保存。認証とクラウドAPIの接続はPhase 4〜5の対象。
- Next: Phase 3でfavorites/folders/tagsと関連Migrationを追加し、既存サジェストへ接続する。

## Phase 3 作業記録（2026-09-28、検証中）

- Files: favorites-core / favorites-store / favorites / favorite-editor、favorites View/CSS、MetadataService/Controller、翻訳、テスト。
- DB: 003_favoritesでfavorites / favorite_folders / tags / favorite_tags。所有者を含む外部キーで他ユーザーのフォルダ・タグへの参照を防ぐ。
- API: GET /api/favorites/metadata。公開IP検証、DNS接続先固定、リダイレクト再検証、サイズ/時間制限。
- UI: お気に入りCRUD、フォルダ、タグ、あいまい検索、表示形式、並び順、コンテキストメニュー、ショートカット、利用統計。
- Settings: 右クリックメニューON/OFF、利用統計ON/OFF、メイン/専用検索の切り替え。
- Tests: search-test-20260928072011で両DBのPHP構文47ファイル、単体39項目、統合35項目、検索API4ケース、SSRF拒否8ケースが成功。
- Tests: 追加のショートカット順序/非表示/明示キー優先と統計OFFを含むFavorites JSテスト成功。検索JS23項目も成功。
- Browser: 追加保存、フォルダ作成/移動、カード表示、タグ絞り込み、同一タブ遷移、使用回数更新を確認。ブラウザerror/warnなし。
- Fix: JS/CSSのキャッシュ再検証。ショートカットはピン留め/非表示を反映した共通割当を使用。明示キーが並べ替え後も優先される。
- Pending: 残りのPhase 3ブラウザ操作、モバイル、原仕様の完了条件照合。Phase 3はまだ完了扱いにしない。
- Next: Phase 3合格後にDiscord認証を実装。クラウド保存/同期接続はPhase 4〜5。

### Phase 3 検証継続の状態

- Browser: 複製、専用検索、非表示、管理用の非表示表示を確認。
- 標準confirmの削除操作で操作ツールがタイムアウト。旧タブを閉じる操作もタイムアウトし、新規タブは読取可能だが入力が反映されない。
- お気に入り/フォルダの削除確認をHTML dialogへ変更。キャンセルを初期フォーカスにした。変更後PHP構文48ファイル合格。
- 変更後削除UI、ショートカット、並べ替え、モバイルの実ブラウザ検証は未確認。Phase 3完了判定は保留。

### Phase 3 完了確認（2026-09-29）

- ブラウザ操作が復旧。HTML削除確認のキャンセル初期フォーカスと削除結果を確認。
- Alt+1で同一タブ起動、使用回数/最終アクセス更新を確認。
- メニューの上へ移動、実際のドラッグ＆ドロップの両方で順序変更を確認。
- 390px幅でdocument幅375px、viewport390px。横はみ出しなし。
- CRUD / Folder / Tags / Drag&Drop / Search / Ranking / Context Menu / Shortcut / Usage Count / Last Accessの完了条件を、既存の両DB・JSテストとブラウザ操作結果で確認。
- Phase 3完了。Phase 4 Discord Authへ進む。外部Discord認証の実確認は未実行であり、合格扱いにしない。


## Phase 4 作業記録（2026-09-29、実装・検証中）

- Files: Auth / OAuthState / DeviceAgent、DiscordOAuth、AuthRepository、AccountController、account/logged-out Views、ja/en、authテスト、設定手順。
- DB: 004_authでdevices / login_tokens。userとdeviceの外部キー、token_hashのみ保存。
- Routes: GET /account、POST /auth/discord、GET /auth/discord/callback、POST /auth/logout、POST /account/device。
- Security: CSRF、10分の使い捨てstate、固定Discord接続先、identifyのみ、応答サイズ/時間制限。Discordアクセストークンは永続化しない。
- Security: 端末トークンを毎リクエスト検証、90日rolling、HttpOnly/SameSite/Secure（localhost例外）、セッション再生成、所有者条件付き端末操作。
- 初期管理者はDiscord識別後に予約IDと照合しトランザクションで一度だけ付与。
- Tests: search-test-20260929221803で両DBの構文/単体39/Installer統合35/検索API4/SSRF8/認証20が合格。
- Tests: 認証テスト拡張後24項目が両DB合格（管理者付与、状態再利用拒否、rolling失効、多端末、所有権、Cookie復元、CSRF、HTTPログアウト）。
- Browser: Discord未設定時のアカウント表示を確認。
- Pending: 実Discordログイン、ログイン後ブラウザ操作、容量/同期状態表示、同期済みローカルデータ削除/local-only保持。Phase 4は未完了。
- 設定手順: docs/discord-development.md。ユーザーへ開発用アプリ有無を質問済み。秘密値はチャットに求めない。

### 追加指示後の検証記録（2026-09-29）

- 追加要件: 各Phase最低3回の検証・Phaseごとのコミット。Phase 1〜3は追加指示以前に完了済みで、当時の変更は未追跡だったため基準点6254f38に保存。Phase 4の完了コミットとは区別。
- Phase 4検証1: account-dataの純粋関数11項目、favorites、search23項目合格。
- Phase 4検証2: MySQL/MariaDBで変更後PHP構文、単体39、認証24項目合格。
- Phase 4検証3: MySQL/MariaDBでHTTPセキュリティ7項目合格。修正後account-data12項目合格（検索先キーも実装と一致）。
- 追加実装: アカウントの端末内データ量/背景容量/最終同期表示、ログアウト時の同期済みデータ削除設定。
- 同期所有権マニフェストを持つ項目だけ削除し、別ユーザーのデータとlocal-only背景は保持。Phase 5で同期成功時のマニフェスト記録を接続する。
- 残る確認: 実Discord往復、ログイン済みアカウント画面のブラウザ操作。実Discordアプリの準備についてユーザーへ質問。Secretはチャットに求めない。
- 3回のテスト合格だけでPhase完了とは扱わない。Phase 4は未完了で目標は継続。

## 目標更新による監査訂正

spec.md最優先で全体を再確認し、Phase 2/3の細部の不足が判明。docs/spec-audit.md参照。過去の完了表記は当時の検証結果であり、最新版の仕様適合判定を意味しない。Phase 2から不足を補修して順番に再検証する。Version 1.0は未達。

## Phase 2 補修検証（2026-09-30）

- Files: search-preferences.js、history/providers/search-settings/search/suggest、home View、ja/en、search-preferences.test.mjs。DB変更なし。
- UI: AI候補の頻度/最近順、検索・履歴キー変更、URL方針、フォーカス候補、履歴件数/期間/エリアを追加。
- 検証1: 新規JS18項目、既存検索JS23項目合格。
- 検証2: MySQL8/MariaDB10.11それぞれ構文59ファイル、単体39、検索API4、認証24合格。既存の隔離DBで実施。
- 検証3: ブラウザでカスタム検索先へのControl+Enter同一タブ遷移、履歴エリア、空欄クリックの履歴候補、Control+Shift+H、件数25/期間7の遷移後保持を確認。390px幅ではみ出しなし、console warn/errorなし。
- Fix: 数値とキーの妥当な入力は即時保存。キー重複修飾子、予約キー、設定間重複を拒否。URLはhttp/https限定。
- Pending: §33ヘッダー導線を補修してPhase 2の照合を完了する。Command PaletteはPhase 8、履歴同期はPhase 5で接続。Phase 3以降へまだ進まない。
- Docker停止を検出し、既存UIと最新両DB検証環境だけ再起動。ボリューム作り直しなし。

### Phase 2 ヘッダー導線検証

- layoutに翻訳された履歴アイコンを追加。トップではダイアログを開き、他画面では/#historyへ遷移後に開く。
- 検証1: JS構文確認。検証2: UI/両DB PHP構文59ファイル、両DB単体39合格。検証3: ヘッダーからEnterで履歴を開く、アカウント画面から遷移後に履歴が表示されることを確認。390px幅ではみ出しなし、console warn/errorなし。
- §21/25/26/29/30/32/33のPhase 2補修を確認。Phase 5の履歴同期、Phase 8のPalette接続は未実装として継続追跡する。Phase 3補修へ進む。

## Phase 3 仕様補修（2026-09-30）

- Files: favorites-layout.js、favorites.js、favorites View/CSS、ja/en、favorites-layout.test.mjs。DB変更なし。
- §36: Autoが画面幅と件数からCard/Icon+Name/Iconを選択。§37: 上/下/履歴下、距離、幅、高さ、件数、列数を調整可能。狭い画面は列数を安全に縮小。
- §38: 自動件数は画面幅に応じた2行分、最大10件。件数指定と「もっと見る」、展開保持ON/OFFに対応。展開時は固定高さを解除し下方向に伸びる。
- 検証1: レイアウト単体16項目、既存favorites全項目、JS構文成功。
- 検証2: 両DBでPHP構文59、単体39、SSRF8、検索API4成功。DB構造変更なし。
- 検証3: ブラウザで位置3種、幅75%、高さ180px、距離48px、1件/2列、展開後再読込、記憶OFF後の折畳みを確認。Autoは850pxでCard、390pxでIcon+Name。document375px、console warn/errorなし。
- 画像: .test-output/phase3-layout.png（Git除外）。Phase 3既存CRUD等の検証記録と合わせて仕様補修を確認。Phase 4補修へ進む。
- Phase 5同期・Phase 6設定全体との接続はそれぞれのPhaseで追加検証する。

## Phase 4 API・ログイン制限・障害対応補修（2026-09-30）

- API: GET /api/auth/discord、GET /api/auth/discord/callback、POST /api/auth/logout、GET /api/user、GET /api/user/devices、DELETE /api/user/devices/{id}。JSON形式、認証・CSRF・所有者条件を適用。開始APIはauthorization_urlを返す。既存HTMLルートも保持。
- Rate Limit: ログイン開始/CallbackだけにIP単位20回/60秒の共通制限。configで変更可能。転送ヘッダーを信用せず接続元を使用、IPはハッシュで短期保存、排他ロックで更新。通常検索を制限しない。
- DB接続を認証画面/APIへ遅延。ゲスト検索/お気に入りはDB停止時も利用可能。認証接続失敗は503、内部例外や認証情報を返さない。
- DB Migration追加なし。
- 検証1: 両DBでPHP構文61ファイル、単体39、認証30、HTTP認証12、Rate Limit単体7、検索API4成功。
- 検証2: 両方の隔離DBを一時停止し、トップ/CSRF成功・user/healthの安全な503の4項目がそれぞれ成功。finallyで再起動、両DBhealthy確認。
- 検証3: 両DBで実HTTPログイン上限429と検索API継続200を確認。account-data12/検索23/既存favorites JS回帰成功。
- 注意: login-rate-http.phpはループバック元のログイン枠を消費するため最後に実行。再実行の認証検証は60秒の窓が過ぎてから。
- Pending: 実Discord認証、ログイン済みUI、成功したAPIログアウト/現在端末解除の専用ケース、API Callback成功、同時要求時Rate Limitの追加検証。Phase 4未完了。設定状況をユーザーへ質問済み。Secretは求めない。

### Phase 4 成功系と同時実行検証（2026-09-30）

- APIログアウト・現在端末解除を実HTTPで検証。JSON成功、Cookie失効、DBトークン失効、古いCookieの401をそれぞれ確認。
- 不正なDiscord token_typeが配列の場合にTypeErrorになり得る箇所を修正。応答検証11項目（型、ヘッダー注入、長さ、identity形式）成功。
- API開始は/api/auth/discord/callbackへ戻るよう修正。Web用/auth/discord/callbackとの区別は固定bool分岐、任意URLを受け付けない。Discord登録URL2種を手順書に追記。
- 検証1: 両DBでPHP構文65、OAuth応答検証11、認証38成功（Callback選択追加前）。
- 検証2: 両DBで24プロセス同時要求を実行し、許可5/拒否19を確認。排他ロックによる上限維持を検証。
- 検証3: Callback選択修正後、両DBで認証39、HTTP認証12、基盤単体39、OAuth応答11成功。
- 開発UI /account のHTTP200とDiscord未設定表示を現時点で確認。実Discord往復・API Callback成功・ログイン後の実ブラウザ操作は未確認。自動テストは実Discord成功の代替としない。
- 今回DB変更なし。Phase 4は未完了、Phase 5へ進まない。

### Phase 4 通常アクセスの期限更新Regression修正（2026-10-01）

- DB接続遅延化で、通常検索画面だけを使うログインユーザーのトークンが延長されない問題を修正。
- OptionalAuthenticationは有効な形式のCookieがある場合だけDB認証を試行。トップ/CSRF/検索候補/metadataで長期期限を更新する。DB障害時はsessionの認証IDを外し、Cookieを保持して端末内機能を継続。権限必須APIは従来通り503で停止。
- 検証1: 両DBでPHP構文66、認証42（通常ルート3つで期限延長をDB確認）、HTTP12、検索API4合格。
- 検証2: 両DBを一時停止、Cookieあり/なしのトップ200・CSRF200・認証503・health503を各8項目確認。DB復旧healthy。内部例外露出なし。
- 検証3: account-data12/検索23/既存favorites JS回帰合格。
- Docker停止を確認して最新検証環境のみ起動、UIも復旧・反映。ボリューム削除なし。
- 実Discord認証とログイン後の実ブラウザ操作は未確認。Phase 4のOAuth/Account UIゲート未達、Phase 5へ進まない。

### Phase 4 外部設定待ちの判定（2026-10-01）

- /accountを再確認: HTTP200、Discord未設定。9/30成功系終了・10/1期限更新修正終了・今回の3回で同じ阻害条件を確認。
- 独立したAPI/Rate Limit/期限延長/DB障害対応は確認済み。残るOAuth/Account UIゲートは実Discord設定と認証が必要。
- bin/configure-discord.ps1にAPI用Redirectの案内も追加。PowerShell構文成功。実値入力は未実行。
- Goalはblocked。解除に必要な作業はdocs/discord-development.mdのRedirect登録と設定スクリプトでの秘密値非表示入力。設定後に実OAuth・ログイン済みUI・端末管理・再ログインを検証しPhase 4を判定する。Phase 5〜12未着手、Version 1.0未達。

## Phase 5 着手（2026-10-01、ユーザー指示による進行）

- 最新指示: 実ログインができる前提で先へ進む。Phase 4実OAuth未確認を留保し、Phase 5実装を開始。認証・権限の実装はそのまま使用する。
- Files: sync-core.js、sync-core.test.mjs。
- 同期マージ: 異なる項目は自動、同一項目はPrevious/Local/Cloudの競合へ。削除対編集、配列フィールドの競合、項目選択、ルール適用を実装。危険なキー/重複ID拒否。元データを変更しない。
- 検証: マージ23項目成功、JS構文成功、既存account-data12回帰成功。DB/API/UIは今回まだ未実装・未検証。
- Next: 同期の版管理と両DBMigration、認証付きAPI、既存CRUD接続、初回選択・競合UI・適応間隔・所有権記録。
- Phase 5未完了、Version 1.0未完成。Phase 4実OAuthの未確認は最終DoD監査へ残す。

### Phase 5 同期版管理とAPI（2026-10-02）

- Files: 005_sync.php、SyncRepository、SyncDocument、SyncController、RequestのJSONオブジェクト保持、bootstrapルート、tests/sync.php、Docker検証一覧。
- DB: sync_states（user_id PK/FK、version、document、updated_at）。両DBへ005適用、再実行0件を確認。
- API: GET /api/sync、PUT /api/sync。既存のCookie認証・サーバー側所有者ID・CSRFを使用。保存はトランザクション内で版比較し、古い版なら409と現行文書を返す。
- Validation: 同期対象のルート限定、マップ/ID整合、URL http/https、危険なキー、文書512KiB、深さ/ノード/文字列上限。空マップを維持するためRequestは元のJSONオブジェクトも保持。
- 検証1: 両DBで構文71ファイル合格。
- 検証2: 両DBで同期15項目（版比較、上書き防止、所有者分離、Validation、HTTP読書き、CSRF、409、匿名拒否）、基盤39合格。
- 検証3: JS同期マージ23、検索23、account-data12合格。
- 初回テストではGETにJSON Content-Typeを付けたためINVALID_JSONになった。テストヘッダーを修正して両DBで再検証合格。失敗を成功として扱っていない。
- Pending: 既存エンティティCRUD API・DBとの整合、初回Local/Cloud/Later、競合画面/ルール保存、同期スケジューラ、所有権記録、UI実動作、新規Installer/全Migration回帰。Phase 5未完了。

### Phase 5 初回選択・競合UI・適応同期接続（2026-10-02）

- Files: sync-session/data/api/dialogs/sync.js、store、account-data/account、search、account View、ja/en、検索CSS、JS検証5種と独立dialog-preview。
- UI: 検索設定に同期ON/OFF・履歴同期・手動同期・状態表示。初回3択、項目ごとのPrevious/Local/Cloud、選択必須・ルール保存。Laterは画面内で保留して手動再開。
- Sync: 250ms変更反映、10秒/1分/5分チェック、3回CAS再試行、変更なしPUT省略、通信中ローカル編集保持、ACK後の原子的ローカル保存/所有権記録。複数タブで状態更新しメタ変更の循環を抑制。プロバイダー順序を保持。
- Privacy: 履歴は既定OFF、OFFの間は端末の履歴を送信/置換せずクラウド履歴を維持。端末設定と背景ファイルは同期文書から除外。背景同期はPhase 7。
- API/Security: CSRFのcsrf_token契約、Cookie認証、保存前後のアカウント確認。PUT user_idは不一致拒否用ヒントで、サーバーの認証所有者を変更できない。アカウント切替の拒否後に両ユーザーの状態保持をテスト。
- DB: 新規Migrationなし、UIへ既存005適用。既存DBテーブルとの投影/CRUDは次の作業。
- 検証1: JS構文、merge23/session32/data18/通信12/store12、account-data12、検索23/設定18、お気に入り/レイアウト16成功。
- 検証2: UI/両DB構文71/基盤39、両DB同期17成功。誤った単体検証ファイル名の失敗は修正して再実行成功。
- 検証3: 未ログインUI、独立fixtureで3択/Later/競合値/未選択保存拒否/Cloud+ルールをブラウザ確認。390px document390/dialog358/content356、warn/error0。設定初期化が同期欄を消す不具合を修正して再確認。画像.test-output/phase5-conflict.png。
- Remaining: §117 CRUD API、既存エンティティDBとの整合、詳細Validation、履歴ON/OFFの完全統合、認証済み実ブラウザの端末間往復、新規Installer/全Migration。OAuth未確認はユーザーの進行指定に基づき留保。Phase 5を完了扱いにしない、Phase 6未着手。

### Phase 5 エンティティDB整合と詳細Validation（2026-10-02）

- Files: SyncProjectionRepository、SyncRepository、SyncDocument、006_sync_entities、007_folder_owner_cascade、sync-projection.php、Docker検証一覧。
- DB: client_id/payload（既存5テーブル）、BIGINT sort_order、user_settings、sync_versions。既存client IDを保持し内部IDを所有者ごとに分離。タグ/フォルダの所有者FKを保持。ユーザー削除を妨げていたフォルダFKのRESTRICTを007でCASCADEへ修正。
- Sync: 初回は既存DBから文書を再構成。保存は正本のCASと全投影/設定/項目版を同じトランザクションで更新。削除後の版を保持。個別CRUDもこの経路へ接続する（未実装）。
- Validation: 型/名称/長さ/URL資格情報拒否/時刻/色/タグ/フォルダ参照/重複prefix/名称/ショートカット/危険キー/端末専用設定を検証。全エンティティ検証をRepository保存時にも実行。
- 検証1: 両DB構文75、基盤39、同期17、投影31成功。投影の完全性/所有者/既存読込/削除/時刻/AI copy/保存途中の失敗時ロールバックを含む。
- 検証2: 両DBMigration再実行0、認証42、HTTP12、検索4、SSRF8成功。UIへ006/007を反映し再実行0・トップ/CSRF200。
- 検証3: JS同期merge23/session32/data18/通信12/store12、account-data12、検索23/設定18、お気に入り/レイアウト16成功。
- Issues resolved: トリガーのSUPER権限不足は一時CHECK制約を使った専用DB試験へ変更（権限拡大なし）。失敗時のユーザー削除の複合FK問題を007で修正し再実行成功。論理的なフォルダ削除ではfavorite.folderIdを先に解除するAPI実装が必要。
- Remaining: §117 CRUDとPOST同期/競合解決API、新規Installer/全Migration往復、履歴同期のAPI/UI完全統合、実認証済み端末間ブラウザ。Phase 5未完了、Version 1.0未達。

### Phase 5 CRUD・POST同期・競合解決API（2026-10-02）

- Files: CloudDataController、CloudMutation、SyncInput、SyncMerge、SyncController/bootstrap、sync-api.js、ja/en、cloud-api/merge検証、Docker一覧、Installer試験の完了待機時間、docs/cloud-api.md。
- API: §117 Settings/Favorites/open/Folders/History/Search Engines/AI Providers。POST /api/syncとPOST /api/sync/resolve-conflictを追加、PUT同期も保持。WebはPOSTへ変更。JSON versionとCSRF、認証所有者、404/422/409の共通契約。
- Behavior: 項目の追加/部分更新/削除、UUIDと初期値、フォルダ削除時のfavorite保持、利用統計OFF、同じ正本/更新版/DB投影経路。競合解決は異なる項目を自動マージし、同一項目・削除対編集を選択。未解決では保存しない。rulesをクライアントへ返す。
- DB: 新規Migrationなし。006/007を含む全7本の往復を新規両DBで検証。
- 検証1: 既存両DB構文81/基盤39、PHP merge8、cloud API50、sync17、projection31成功。
- 検証2: search-test-20261002020651で両DB新規Installer35と全PHP試験合格。構文80（初回config生成前）、検索4/SSRF8、OAuth11/認証42/HTTP12、同期17/投影31/merge8/API50、制限7/同時24（5許可19拒否）/実HTTP制限と検索継続。
- 検証3: JS構文、同期23/32/18/通信12/store12/account-data12、検索23/設定18、favorites/layout16回帰成功。UI構文81・トップ200。
- Issues: 最初の新規Installer試験は20秒のHTTPタイムアウト（サーバー側installed=yes/7Migration適用は確認）。Installer完了試験だけ120秒にし、別の新規隔離環境で全検証成功。初回の失敗は未合格としてログ保持。既存・失敗環境のボリュームを削除しない。
- Remaining: 実HTTP2端末のフロントエンジン整合、履歴同期のON切替/物理期限削除、オフライン復帰、認証済み実ブラウザ/実OAuth留保、背景設定のPhase 7接続。Phase 5未完了、Phase 6未着手。

### Phase 5 実HTTP端末整合・履歴・ルール共有（2026-10-02）

- Files: SyncRetention、SyncRepository/Document/Controllers、sync-session/data/sync.js、sync-httpのPHP/Node/PowerShell、retention/data/projection/API検証とDocker一覧、cloud API仕様。
- Sync: 通常の端末Cookieを使う実HTTPで2端末整合、設定/背景設定メタ/フォルダ/タグ/お気に入り/統計、並列409の再試行、同一項目選択、ルール共有・エンジン再構成、オフラインJSON再構成からの復帰、別ユーザー・Laterを確認。実OAuthと認証済みブラウザとは区別。
- History: OFFは送受信しない。ON切替の最初だけ同一ユーザーの端末+Cloud履歴を保持、その後の削除は通常同期。初回選択や他ユーザーへ自動マージを適用しない。OFF後も同期済みIDの所有権を保持。件数・期限は文書/DB/削除版で物理削除、未変更は版維持。入力上限12,000文字へ整合。
- Rules: settings.syncRulesへ保存・共有。パス/選択Validation、以前のローカルルール移行。同じルールをエンジン再構成後も使用。
- Issues resolved: 並列保存のロック取得を初めから排他へ変更。履歴ON切替のCloud履歴削除を補修。ブラウザ保存イベントによるチェック反転を一括保存で補修。失敗後に再検証。
- 検証1: 両DB構文84/基盤39/sync17/projection34/cloud API52/retention9/PHP merge8/認証42/HTTP12/検索4成功。
- 検証2: 実HTTP2端末試験を両DBで3回＋追加後再実行成功、CAS再試行1回を確認。一時認証ファイルと専用ユーザーを除去。
- 検証3: JS merge23/session32/data23/API12/store12/account-data12/検索23/設定18/favorites/layout16成功。ゲストブラウザのON再読込保持・OFF・同期ON/OFF表示、390px document/dialog375/content358、warn/error0。画像.test-output/phase5-history-sync.png。
- Remaining: 既定300件×日本語12,000文字（10,823,525 bytes）が512KiB文書制限で422になることを確認。Body1MiB、二重JSON decode、本体/checkpointのLocalStorage永続容量も補修が必要。仕様の保存件数/入力長を縮小しない。Phase 5未完了、Phase 6未着手。実OAuth未確認を最終監査へ留保。

### Phase 5 大容量文書の実HTTP検証（2026-10-02）

- Files: Request、SyncDocument/Repository/Controller、docker/php.ini、sync-http.test.mjs、cloud-api.md。
- Capacity: DBと同じUTF-8 JSONで文書16MiB未満、同期/競合解決本文32MiB、他API1MiB。単一JSON decodeでroot bodyとdocumentを共有。Docker post_max_size=32M。本番設定手順を記録。
- Memory: 300件×12,000文字の4バイトUnicode（14,428,059 bytes）。競合解決で128MiB不足を再現し、旧JSON行・PDO buffer・前回文書をACK decode前に解放して同じ128MiBで成功。メモリ上限を増やさず補修。
- 検証1: 両DBの実HTTP通常2端末/409と長文保存/読込/競合解決/409/所有者分離を2回成功。最後はroot配列400、一般API上限413、文書上限422・版維持も確認。一時認証ファイル・専用ユーザーを除去。
- 検証2: 両DB構文84/基盤39/sync17/projection34/merge8/retention9/cloud API52/auth42/HTTP12/search4成功。DB変更なし。
- 検証3: JS merge23/session32/data23/API12/store12/account-data12成功。UI appとPHP設定を反映、今回の新規ブラウザ検証は未実施。
- Failures: テスト403はJSON/CSRF漏れを修正、続く500は実メモリ不足を上記補修。成功と区別して記録。
- Remaining: LocalStorage本体/checkpointの容量不足。旧データを保持してIndexedDBへ移行、ACK保存失敗/再読込/オフライン復帰/複数タブを実ブラウザ検証。Phase 5未完了、Phase 6未着手。

## Phase 5 IndexedDB・同期機能ゲート（2026-10-02）

store-database.jsでtop-levelキーごとのIndexedDB保存。モジュール初期化時にLocalStorageの全データを同じwrite transaction内のmarker照合で移行しreadback後に旧コピーだけ除去（ログアウト削除後の第二コピー残存を防止）。初期化失敗時のクラウド同期/削除は停止、ローカル検索は維持。LocalStorage-only fallbackも維持。

通常setは画面即時反映、キー別永続キューと失敗した変更のflush再試行。setManyはtransaction成功後にACK/所有権/文書を公開。保存中編集のrevision保護、生成関数で変更を再マージして保存し直し、queued setは最新のキー値を保存する。SyncSessionはasync accept完了を待つ。Webでは保存待ち中の編集も項目マージし次回即時同期を予定。BroadcastChannelで別タブをDBから再読込。検索/お気に入りの遷移はflushを待つ。accountも同じstoreで使用量/設定/ログアウト削除。

検証1: JS全構文、store12/IndexedDB21/session37/merge23/data23/API12/account12/search23/preferences18/favorites/layout16成功。IndexedDBモデルはquota transaction abortによるACK/文書保持、失敗したローカル編集のflush再試行、再構成、保存中の異なる設定項目のCloud+Local両方保持を確認（モデル試験であり実quota枯渇ではない）。
検証2: 実HTTPの2端末/CAS/オフラインJSON再構成/履歴/ルール共有/大文書を両DB再成功。PHP・DB・Migrationは今回変更なし、直前の両DB全PHP/新規全7本往復証拠を継続使用。
検証3: IAB fixture実IndexedDBで日本語12,000文字×300履歴とcheckpoint約21.6MB保存/再読込、invalid clone失敗でACK/データ不変、保存中編集/再読込、別タブ変更反映、旧favorite/local背景の移行、所有権対象のみ削除/再読込とローカルfavorite/背景保持を確認。通常UI8082でfavorite使用回数2→3を遷移後に確認、検索indexeddb-history-testをローカル先へ実行し遷移後履歴保持。warn/error0。画像.test-output/phase5-indexeddb.png。認証をバイパスしない独立storage fixture。

添付Phase 5ゲート照合: settings/favorites/history同期（実HTTP+投影）、conflict detection/resolver（JS/PHP/実HTTP+独立UI）、offline queue foundation（ローカル変更+ACK baseの永続化/復帰）、device consistency（実HTTP2端末/複数タブ）を確認。同期機能ゲート検証済みとしてPhase 6へ進む。実Discord/OAuth・実認証済みブラウザはユーザー指定の前提に従い最終監査へ未確認を留保。Background Settingsメタは同期し、実ファイル/ライブラリUIはPhase 7で接続。Version 1.0未完成。

## Phase 6 設定基盤（2026-10-02）

§71–76を実装開始。カテゴリ9種のsidebar modal、検索/AI/Privacy/Shortcutsに既存設定を分類、favorite設定とsyncを同じmodalへ移動、ヘッダー導線/#settings、desktop resize/mobile full screenのCSS、再表示時width/height解除。重要な未確定provider設定を閉じる際の確認、カテゴリ別fixed reset lists（ユーザーデータを含めない）。Appearance/Background/Generalの新機能はまだ未実装、空カテゴリを完了扱いにしない。

settings-history.jsは日時/設定/Previous/New・存在フラグを20件保持、Undo/Redo、Undo後の新変更でRedoを破棄、カテゴリresetのUndo。settingsHistoryはtop-level端末専用でsyncDocumentに含めない。通常setSetting、同期toggle、account設定を接続し、最後に使った検索先等の操作記録は除外。reset/UndoはIDB原子的setMany。検索画面/favorite toolbarにも設定変更を反映。

検証1: settings-history19、store12/IndexedDB21/session37/data24/API12/account12/search23/preferences18/favorites/layout16と全JS構文成功。sync-dataで履歴メタが送信されないことを追加確認。
検証2: 両DBPHP構文84/基盤39/sync17成功。Viewと翻訳の最終構文84も再成功。DB/Migration/APIルート変更なし。app/public/langはUIと両DBへ反映。
検証3: IABでカテゴリ表示、チェック変更→Undo→Redo、再読込して日時/Previous/Newと20件履歴のcursor保持を確認。カテゴリresetの標準confirmで操作が停止（Input.dispatchMouseEvent/Emulation focusタイムアウト）、getJsDialog/closeも同じ状態。新タブ8は描画できるがイベント無反応。reset成功とはしない。確認をアプリ内dialogへ変更、構文/単体は成功、変更後のブラウザ検証は未確認。Mobile/resize/Close重要設定も未確認。今回は検証画像未作成。

残件: 上記未確認UIとtheme/sunrise/glass/custom fonts/animation/greeting/clock/date/header/onboarding。挨拶はspec§69で既定ON（以前の進捗の既定OFF記述は誤り、時計/日付だけOFF）。Phase 6未完了、Phase 7へ進まない。

### Phase 6 テーマ・フォント・基本表示・検索欄（2026-10-02）

- Files: appearance-core.js/appearance.js、settings schema/modal、search.js/CSS、翻訳、Response CSP、appearance-core.test.mjs、docs/appearance.md。
- Theme: Light/Dark/OS/森/ローズ/Custom複数保存、地域の太陽時近似（NOAA）・白夜/極夜/日付変更線。地域未設定はOS、許可を自動要求しない。0.75秒フェード。地域座標の同期対象をUIに表示。
- Fonts/Animation: System/Serif/Mono/Google/Custom HTTPS、size/weight/line height/spacing。外部接続の説明、失敗時fallback、4段階とreduced motion優先。CSPはGoogle外部CSSとHTTPSフォントを追加、スクリプト制限は維持。
- UI: 挨拶既定ON、時計/日付OFF、カスタム文/時間帯/既存APIのユーザー名、12/24h/秒/日付形式/曜日。検索欄は位置3種・responsive/fixed320〜900・高さ48/56/72・Glass色/透過/blur/枠線/角丸/影/文字/placeholder。モバイルは固定幅でも画面幅へ縮小。
- DB/API: Migrationと認証経路の変更なし。既存設定同期へ接続、Custom定義はカテゴリresetでも保持。履歴の表示を設定名とテーマ名へ改善。
- 検証1: 全JS構文、appearance37/history19、store12/IndexedDB21/session37/merge23/data24/API12/account12/search23/preferences18/favorites/layout16合格。全*.test.mjs一括実行は実HTTP専用ファイルへの引数不足で停止し、単体一覧から除外して全回帰成功。実HTTP試験の今回の再実行は未実施（直前Phase 5の証拠を維持）。
- 検証2: UI/両DB構文84、両DB基盤39/sync17成功。最新app/public/lang/testsを反映、最後のJS/CSS修正も反映済み。
- 検証3: 新タブ9で旧confirm停止が解消。Searchカテゴリreset→Undoで25件/7日/候補方針を復元、suggestOnFocus ONに復元、未確定provider Close取消/破棄を確認。Dark、Custom A/B保存と再読込、公式配信元のCustom font load完了、時計/日付とカスタム挨拶の再読込保持を確認。390pxモーダル全画面、PC960×612→851×549リサイズ→再表示960×612。検索欄420px/72px/opacity .4/blur20px/radius28px/影なしの保存・再読込、390pxで内容343px/document375px、console warn/error0。初期化後標準56px。未保存テーマClose確認と破棄も確認。
- Issues fixed: 他項目を続けて操作すると未保存の数値/文字が戻る不具合を発見。編集中draft保持とblur保存で再検証成功。標準検索欄が共通button余白で61.6pxへ膨らむ問題を56pxへ補修。
- Images（Git除外）: .test-output/phase6-display.png、phase6-mobile.png、phase6-glass-mobile.png、phase6-appearance.png。検証用のGeneral/Appearance選択は初期値へ戻したが、再利用するCustomテーマ定義A/Bと既存entityは保持。
- Remaining: 時計/日付の位置・サイズ・フォント・色・不透明度、header全設定、§82初回案内。Google Fontsモード/solar地域入力と時刻切替/reduced motionの実UIは未確認。実Discord後の名前表示も未確認を留保。Phase 6未完了、Phase 7へ進まない。

### Phase 6 時計・日付・ヘッダーstyle（2026-10-02）

- Files: layout.phpのdata-header-item、appearance-core/appearance.js、search.css、settings schema/modal、ja/en、display-layout.test.mjs、appearance.md。
- UI: 時計/日付は上/下/4隅、size10〜120、font4種、色/opacity。隅が同じ場合は縦にずらす。Headerは上/下、左右/中央、size10〜32、opacity、背景色/blur、5項目（Settings/History/Account/Brand/Language）の順序/表示。全項目OFFでも検索設定ボタンから復元可。
- Auth/Security: 既存現在ユーザーAPIでLogin/Profileラベル。認証・権限を変更しない。数値の範囲、色hex、フォント/配置enum、重複・未知header keyを除外。DB/API/Migration変更なし。
- 検証1: 新規display21、appearance37/history19、全構文と既存JS回帰すべて成功。
- 検証2: UI/両DBPHP構文84、両DB基盤39/認証HTTP12/sync17成功。
- 検証3: 時計64px/Mono/下とheader下/左/blur8/brand非表示/並べ替え、再読込保持。390pxでdocument/header375px、header bottom844px（viewport844px）。同じ右上でclock bottomとdate top一致・非重複。日英の全追加設定、Google Fonts実読込完了表示、架空地域の昼夜Light/Dark切替確認、warn/error0。画像phase6-header-display.png/header-mobile.png（Git除外）。
- Issues: 数値blurでリストを再描画するとcheckbox clickが消える問題を再現。設定内容が変わる場合だけ再描画しフォーカス復元、同じ操作を成功。地域入力の未確定draft/Close確認、保存/Undo/resetの入力値反映も補修。ブラウザのdocument.fonts列挙はread-only wrapper非対応で失敗、アプリ側FontFaceSet.load完了表示を使い実読込確認。
- Cleanup: General/Appearance初期値、地域入力空、日本語へ復帰。既存entity・Custom保存定義を保持。
- Remaining: §82可変初回ウィザード、Animation/reduced motionの実UI、Phase 6最終照合。実Discord後の名前/Profile未確認を留保。Phase 6未完了。

### Phase 6 初回ウィザード・機能ゲート（2026-10-02）

- Files: onboarding-core.js/onboarding.js、search接続、単色background設定、settings modal/schema、appearance/core/CSS、ja/en、onboarding-core.test.mjs、sync-data/appearance検証、phase6-gate.md。
- Wizard: 8steps（認証済み7）、Welcome/Appearance/Background/Search/AI/Favorites・Shortcuts/Discord/Complete。各Skip/Back/Later、再開/完了/再実行。設定と進捗をIDB同一transactionへ保存。進捗は端末専用、設定は既存同期対象。重要draftがある設定画面から再実行する場合は既存confirmを通す。
- Background: themeまたは実Solid colorを選択・反映。Phase 7で画像/動画/Gradient/ライブラリと圧縮等を追加する。
- Security/Validation: provider現行ID、theme/animation enum、font size範囲、色hex、予約/重複/履歴キー衝突を拒否。Secret入力/認証バイパスなし。DB/API/Migration変更なし。
- 検証1: onboarding18/appearance42/display21/history19、全JS構文・既存回帰合格。sync-data25は進捗同期除外を確認。
- 検証2: UI/両DB構文84、両DB基盤39/auth HTTP12/sync17合格。
- 検証3: 初回・外観保存/途中再開・単色・SearchSkip不変・重複キー拒否・DiscordSkip・完了/非表示、再実行/Later/Welcome再開・全Skip・Backを日英UIで確認。390px document375/dialog358/content356、warn/error0。AnimationNone 0s、Settings Escape/外側Close。画像phase6-onboarding.png/onboarding-mobile.png（Git除外）。
- Issue: 日の出境界のfractional msがDateで切り捨てられるためLightにならないことを単体で再現。solarTimesを整数msへ丸め境界2つを再成功。実時刻の精度を過大に主張しない。
- Gate: docs/phase6-gate.mdで添付の11完了条件とspec§53〜56/68〜82を照合、Phase 6機能ゲート検証済み。実OAuth/名前/Profileと実OS reduced motion切替は最終監査へ留保。認証済み7stepsは単体のみ確認。Phase 7へ進む、Version 1.0未完成。

### Phase 7 背景ライブラリ基盤（2026-10-02・実装中）

- Files: background-core.js/background.js、search/appearance接続、CSS、settings-schema、ja/en、Response CSP、sync-data、background-core/sync-data検証。現在未コミット。
- UI: Solid/Gradient/Image/Video、HTTPS/サイト内URL、プリセットと端末内ライブラリ、編集・保管/復元、描画調整、動画速度/ミュート/ループ/停止、モバイル代替画像、手動/ランダム/時間条件切替。
- Rules: 11条件、AND/OR、詳細条件優先・同順位ランダムの判定コア。編集UIは時間条件のみ、天気・気温の実データは未接続。
- DB/API: 変更なし。アップロード/所有者別ファイル保存/メタデータ/圧縮/容量/背景ごとのCloud Syncは未実装。現在は端末専用、ローカル選択を同期ACKで上書きしない暫定保護。
- Security: URL/色/数値の検証、文字列のtextContent表示。画像blobとHTTPS動画のCSP追加、スクリプト制限維持。認証バイパスなし。ファイルアップロードの安全性は未実装なので検証済みとしない。
- 検証1: background-core41、sync-data28と追加JS構文確認成功。
- 検証2: UI/両DB構文84成功、Phase初期に両DB基盤39/sync17回帰成功。Migration追加なし。
- 検証3: Gradient保存/再読込、画像読込、動画1.5倍/ミュート/ループ/停止、390px代替画像/横はみ出しなし、時間条件、編集/保管/復元、reset時ライブラリ保持、console warn/error0。Git除外画像phase7-gradient/mobile-fallback/library-mobile。MDN公式flower.webm URLで検証。
- Remaining: 回帰検証と途中コミット、安全なアップロード・圧縮/容量・Cloud Sync、全条件編集/天気/地域、手動動画再生導線。Phase 7未完了、Phase 8〜12未着手。状態確認時点で新たな検証は実行していない。

### Phase 7 基盤再検証

全JS構文/全単体（実HTTP専用除外）成功。UI/両DB構文84、両DB基盤39/認証HTTP12/同期17成功。切替間隔の編集中値保持・ライブラリ型防御を補修。新規DB/Migrationなし。前記ブラウザ証拠を維持、今回の追加補修のブラウザ確認は未実施。途中コミット、Phase 7未完了。

### Phase 7 アップロード検査・私有保存サービス

- Files: BackgroundUpload.php、background-upload.php/background-upload-http.php、tests/docker.ps1、docs/background.md、進捗/監査。前のライブラリ基盤はee76732へ保存済み。
- Security: 実HTTP由来/拡張子/実MIME/画像寸法/25MiB・500MiB、危険な名前拒否、乱数保存名、非公開所有者ディレクトリ0600/0700、symlink拒否。所有者認証は今後ControllerでAuthへ接続する。
- 検証1: 両DB環境で検査22成功。検証2: 両環境www-dataで独立HTTP14成功（非公開/404/同名保護/symlink拒否を含む）。検証3: PHP構文87成功、直前のJS全単体/両DB基盤39/auth HTTP12/sync17維持。一時領域/プロセスは終了時除去。
- DB/API/UI: 今回変更なし、公開upload endpointなし。HTTP試験はサービス用fixtureで、実アプリ認証・CSRFの成功と扱わない。
- Remaining: 圧縮・環境不足警告、メタDB、認証/CSRF API、容量の原子的適用と清掃、500MiB受付設定（現在32M）、アップロード画面/Cloud Sync、全条件編集/天気/地域。Phase 7未完了。

### Phase 7 実圧縮・環境判定

- Files: BackgroundCompression.php、EnvironmentCheck.php、background-compression.php、docker/compression.Dockerfile、Docker一括検証、background.md/進捗。
- Behavior: Imagick→GD/FFmpeg、小さい検証済み出力のみ採用、元データ維持/実最終サイズ、機能不足/失敗警告。PNG色/透過、JPEG向き、Imagick GIFアニメーション保護。GD非対応animation/APNGは保持。
- Security: Imagickリソース制限/復元、GDメモリ見込み確認、FFmpeg引数配列/固定demuxer/ネットワークprotocol拒否/期限・出力上限、非公開候補出力/失敗時除去。処理を認証APIへまだ接続していない。
- 検証1: 両DB機能不足8/基盤39/auth HTTP12/sync17/構文89成功。検証2: 専用www-data Imagick/GD/FFmpeg17、GDのみ16、proc_open禁止12成功。検証3: 補修後各経路再成功、写真向き/GIF/動画/失敗保持/一時出力除去を確認。
- Failure: 最初はImagickの誤メソッド呼出でfallback、優先順試験が失敗。autoOrientと互換回転へ補修し再成功。
- DB/API/UI: 新規変更なし。圧縮後容量のDB適用/Upload API・画面/Cloud Syncは未実装。巨大動画/期限発動/全形式/ICC実写真未確認。Admin警告表示はPhase 9へ。Phase 7未完了。

### Phase 7 背景DB・認証付きAPI

- Files: 008_backgrounds、BackgroundInput/Repository/Controller/FileResponse、Request/Response/bootstrap、BackgroundUpload、config example、PHP上限、ja/en、API/quota試験、Docker検証一覧。
- DB/API: 背景/条件の所有者複合キー/FK、項目version、owner row lock+圧縮後bytes容量。GET backgrounds、POST upload/url、PUT/DELETE ID、GET/HEAD file。変更はCSRF、読込もAuth/所有者。64KiB stream/単一Range、DB拒否時upload候補清掃。
- 検証1: 旧両DB API25、実並列quota6を3回ずつ成功。検証2: 新規両DB Installer35/全8Migration往復、基盤39/sync17/projection34/Cloud52/auth HTTP12/検査22/独立HTTP14/圧縮不足8/構文96成功。検証3: security追加後API27/構文96、UI008反映/トップ200 diagnosticsなし。
- Failures: partial update ruleのarray化で422→object保持で再成功。UI HTTP試験の予約変数誤用→名称修正後成功。
- Security: 別所有者file404/guest401/CSRF403、不正URL/日付/flag422、版409、重複upload候補除去、quota拒否でmetadata不変。通常長期Cookieの実HTTP、実Discord往復は未確認留保。
- Remaining: Local Upload UI/IDB blob、背景ごとの同期・競合/offline、条件全種編集/天気・地域/手動再生。DELETEは保管/容量保持で永久清掃未実装。500MiB実HTTP/実圧縮+DB容量結合/Admin警告未確認・未実装。Phase 7未完了。

## Phase 7 端末内ファイル保存（2026-10-03）

IndexedDB schema 2へ非破壊upgradeし、background-files object storeにBlobを保存。背景メタ/設定とファイルの追加・置換・除去を同一transactionへ接続。保存失敗時はメタもBlobも変更しない。ファイル本体をJSON/同期文書へ入れない。選択した画像/動画の拡張子・実ヘッダー・25MiB/500MiBを検査、描画は一時blob URL、切替時にrevoke、古い非同期読込による上書きを拒否。編集時ファイル未選択なら保持、保管/復元でも保持。ローカル圧縮/Cloud Syncはまだ未接続。

検証1: 全JS単体17ファイル成功（IndexedDB30/ローカルファイル14/背景41等）、全JS構文成功。
検証2: 最新隔離MySQL/MariaDBで基盤39とPHP構文確認成功。SQL Migration変更なし、008の直前両DB往復証拠を維持。
検証3: 隔離8083のテスト専用画面で生成PNGを製品保存処理へ入力、通常トップへの再読込でblob画像naturalWidth1を確認。ファイル未選択の名前編集、保管/復元後も表示保持。390pxでdocument幅/scroll375、form幅/scroll341、console warn/error0。証拠画像.test-output/phase7-local-file.png（Git除外）。ブラウザ通常表示に戻した。IABタブ11を保持、8083には検証用ローカル背景1件を残す。

OSファイルchooser自動操作はinput.filesへ反映されず未確認。最初のiframe検証画面はX-Frame-Options DENYで拒否、削除済み。8082はSEARCH_TEST_MODEなしで専用PHP画面404、拒否維持。テスト専用PHP/mjsは隔離8083のpublic/_testにだけ配置、通常View参照なし/認証バイパスなし。新しい同一ページfixtureで保存・描画を確認したが、OS chooser成功とは扱わない。最初の検証ボタンが暗黙submitで二重操作になったためtype=buttonへ修正。実500MiB/ローカル圧縮/動画ファイル実操作は未確認。

次は背景ごとのCloud Sync ON/OFF、ファイル/メタと競合/オフライン、全条件編集/天気・地域/手動動画再生。Phase 7未完了、8〜12未着手。実OAuth留保は継続、Version 1.0未完成。

## Phase 7 背景同期の送受信層（2026-10-03）

background-api.jsに一覧、URL/色背景作成、multipartファイル作成、version付き更新、私有ファイル取得を追加。変更はCSRFとuser_id hint、same-origin認証、redirect拒否、JSON envelope検証。multipart Content-Typeを手動指定しない。アップロード/ダウンロードは最大660秒、通常JSON15秒。ファイル取得先はmetadata.urlでなく検証済みIDから固定APIへ生成。型/MIME/上限/宣言・実bytesを検査、過大・不正レスポンスはstream取消。ファイル本体をJSONへ含めない。

検証1: 専用fetchモデルでmultipart/owner hint/CSRF失敗/JSON不正/409・私有URL・MIME/Content-Length/実bytesの上下限・stream取消を確認。検証2: 全JS単体18ファイルと新規構文成功。検証3: 最新隔離MySQL/MariaDBの実HTTP背景API各27項目再成功（認証/CSRF/所有者/CAS/実multipart/Range/保管復元）。JS送受信層自体の実HTTP接続はまだ未確認、PHP実HTTPとfetchモデルを区別する。SQL/DB/API仕様変更なし。今回新規UI操作なし。

この送受信層はまだ製品UIから呼び出していない。Cloud Sync ON/OFF・checkpoint/所有権・3-way競合・ファイル置換・初回選択・offline復帰への接続が次の作業。既存同期文書から背景ライブラリは引き続き除外。アカウント容量表示のfileSize接続・同期データ削除時のBlob清掃も残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 同期ファイルの安全な置換（2026-10-03）

POST /api/backgrounds/{id}/uploadを追加。Auth/所有者/CSRF、multipartの厳密なversion、ID一致、実ファイル検査/圧縮を確認し、DB transactionで版と圧縮後容量を再検査して置換。成功後に旧ファイルを除去、失敗時は元データを保持して新候補を清掃。PUTでupload→URL/色へ切替も接続、画像/動画へのURL切替は明示URLが必要。実ファイルなしでURL→upload宣言は拒否。DB schema変更なし。

APIメタにfileRevision（非公開乱数保存名のSHA256、保存名/パスそのものは露出しない）を追加。私有file APIはETagとIf-Match/412に対応。メタ取得とファイル取得の間に置換が起きた場合、同容量でも異なる版のファイルを保存しない。JS transportはmultipart版付き置換とETag一致検査へ対応。まだ製品UIから同期transportを呼ばない。

検証1: 両DB実HTTP背景API40項目（CSRF/他所有者/古い版/不正multipart版/旧ファイル保持/置換成功後清掃/容量/ETag/URL切替）成功。検証2: 両DB並列quota6/基盤39/PHP lint成功（MySQL98:専用public/_test PHP込み、MariaDB97）。検証3: 全JS単体18ファイル成功、置換multipart/If-Match/ETag不一致をfetchモデルで確認。初回API試験の置換成功確認は失敗、別HTTPプロセス削除後のPHP stat cacheをclearstatcacheして再成功。アプリ失敗と混同しない。新規ブラウザ操作なし、変更UIなし。

最新両DBへapp/tests反映、8082は今回backend未反映。旧ファイルunlink失敗やプロセス強制終了時の耐久的孤立清掃は未実装。fileRevisionは内容hashではなく保存ファイルの版識別。巨大動画実通信/実圧縮+DB容量/置換同時競合の専用実HTTP試験は未確認（Repository CASと既存並列quotaを検証）。

次はCloud Sync ON/OFFの編集UIとbackground checkpoint/所有権・3-way競合・初回選択・offline復帰を接続。受信Blobとメタ/checkpointはIDB同一transaction、保存中のローカル編集を保持し、ローカル専用背景は送らない。既存同期文書へ背景ライブラリを不用意に追加しない。条件全種/天気/地域/手動再生、容量表示と所有権削除時Blob清掃も残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 背景同期の差分・競合判定（2026-10-03）

background-sync-core.jsへ背景メタの同期投影と3-way merge/選択解決を追加。描画設定と名前など異なる項目は自動マージ。メディア種類・URL・ファイル版を単一選択として扱い、別メディアを混在させない。保管と同時編集は背景全体の競合にする。実ファイルの端末内版とACK済みのfileRevisionを区別、fileId/fileVersion/サイズはAPI書込みメタへ流さない。条件解除はrule:nullを送る（省略では旧条件が残る）。

背景単位の明示cloudSync=falseを旧localOnlyより優先。同期OFFで未所有の背景と他アカウント由来の背景は送らず、同じIDのクラウド受信でも上書きしない。前回ACKのあるOFF変更だけはOFFを送るために投影へ含める。checkpointは同一userIdだけ採用し、ID重複・危険キーを拒否。

検証1: 専用単体で設定別マージ、同項目競合/未解決拒否/選択、保管対編集、未送信ファイル版とACK済み版、アカウント/同期OFF保護・重複拒否成功。検証2: 全JS単体19ファイルと追加構文成功。検証3: 種類とURLの一体選択、5設定の独立変更マージ、既存sync-core23/IndexedDB30を再成功。PHP/DB/UI変更なし、直前の両DB背景40/quota6/基盤39/lintの証拠維持。今回実HTTP/新規UI操作は実施していない。

判定コアはまだ製品から呼び出していない。次はbackground sync session（部分成功のACK保存、版409再読込、受信ファイルとメタ/checkpoint同一transaction、保存中編集保持）、Cloud Sync編集UI・初回選択・既存競合dialog/ルール・offline復帰へ接続。UI保存時は既存cloudOwner/fileRevision/syncedFileVersionを保持し、ファイル置換時だけlocal fileVersionを進める必要がある。所有権削除時Blob清掃/容量表示/条件全種/天気・地域/手動再生も残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 同期ACKとファイルの原子的保存（2026-10-03）

background-sync-ack.jsに最新stateからACK保存計画を作る処理を追加。受信Blob・背景メタ・backgroundCheckpoint・backgroundOwnershipを一体で保存。通信中の名前/設定/保管/OFF変更を保持し、通信中に置換された新しいローカルBlobを旧ACKで上書きしない。URLへの切替時に旧Blobを同じtransactionで除去。別所有者/端末専用ID衝突/実bytes不一致/必要なBlob欠落を拒否。保管中の送信元Blobは受信不能でも保持し、圧縮後サイズと異なるキャッシュは復元後に再取得する必要がある。

store.setManyのfiles引数は最新stateから操作配列を生成できるよう拡張。メタとfilesへ同じsnapshotを渡す。保存中の入力state変更があれば両方再計算。全stateのrevisionを監視し、保存対象以外の設定から計算する場合も再計算。通知は全state更新後に出す（backgrounds通知時にcheckpointも更新済み）。

検証1: ACK単体で受信/既存cache/編集中名前/新fileVersion/保管/OFF/削除/所有者拒否/URL切替成功。検証2: 全JS単体20ファイル成功、追加JS構文成功。検証3: IndexedDBモデル41項目でfactory再計算・通知時の整合・ファイルとcheckpointのquota rollback・再試行成功を確認、既存store12/session37/merge23も成功。初回factory試験はfontSize22のままで失敗、書込みキーだけのrevision監視を全入力stateへ拡張して再成功。実ブラウザquota枯渇ではなくモデル試験。PHP/DB/UI変更なし、直前両DB証拠維持。

コードはまだ同期スケジューラ/UIから呼んでいない。次はbackground sync sessionの部分成功/初回選択/CAS409再読込/同一owner再確認を接続、ACK plannerをsetManyのvalues/files factoryから利用する。Cloud Sync編集UI・競合dialog/ルール・offline復帰・実HTTP2端末/実IDB・ログアウト所有権Blob清掃/容量表示が残る。UI編集では既存cloudOwner/fileRevision/syncedFileVersionを保持する。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 背景同期sessionと設定画面接続（2026-10-03）

BackgroundSyncSessionとWeb adapterを背景画面へ接続。初回Local/Cloud/LaterはpendingInitialを保存し部分成功から再開、項目ごとにACKを保存。409は一覧を再取得し最大3連続で停止。同期前/ACK前の同一所有者確認、成功したuploadの原本を保持してACK版を保存、圧縮後ファイル取得が途切れても再uploadせず受信再開。背景ON/OFF・今すぐ同期・失敗/未ログイン状態を追加。背景編集で既存cloudOwner/fileRevision/syncedFileVersionを保持、URL切替は旧ファイル参照を除去。受信ACKはstoreのmeta/files factoryへ接続。online/表示復帰と適応間隔で再実行。

OFFはファイルやローカル編集を送らず、旧ACK所有項目へのOFFフラグだけ送る。OFF後もローカルの置換済みBlobを維持。初回Cloudで置き換わるローカルON項目は永久削除せず保管/同期OFFへ退避。背景と一般同期のdialogをキュー化し、背景用titleを明示して同時modal衝突を避ける。競合保存ルールは現状background checkpoint内だけ（他端末へのルール共有は未接続）。

検証1: sessionモデルで2端末/異なる設定のマージ/圧縮結果/同期OFFの新ファイル非送信/409/Later/ログアウト・所有者変化拒否/受信中断→再送なし復帰を成功。検証2: 全JS単体21ファイル/構文38、両DBとUIの基盤39/翻訳一致、両DB背景実HTTP40成功。モデルは実HTTP2端末として扱わない。PHP/DB schema変更なし、直前Migration証拠維持。検証3: 実UI8083で既存uploadのON保存/再読み込みでcheckbox保持・blob画像naturalWidth1、OFFへ戻す/未ログイン表示、console warn/error0。画像.test-output/phase7-background-sync-settings.png（Git除外）、タブ11保持。認証済みブラウザではない。

public/langはUI8082と最新両DBへ反映、8082backendも最新へ反映。検証背景は同期OFFへ復帰し保持。実OAuthは成功前提のユーザー指示を維持、検証成功とはしない。

次はJS session/transportの実HTTP2端末と実IndexedDB確認、競合dialog・offline通信/ACK失敗後再開、ログアウト所有権Blob清掃と容量表示、ルール共有を完成させる。server成功後ACK永続化に失敗した場合の再送抑止/再起動後の扱いは専用試験と補修が必要。API読込の所有者hint追加/アカウント切替途中の応答も監査する。条件全種編集/天気・地域/手動動画再生、巨大動画実通信・圧縮+DB容量・耐久的孤立清掃/Admin警告も残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 実HTTP2端末とログアウトBlob清掃（2026-10-03）

background-sync-http.mjsで製品BackgroundSyncSession/background-api/ACK plannerを実APIへ接続。専用fixtureの通常remember Cookie（AuthRepositoryの端末発行、OAuth成功の証拠ではない）を使い、MySQL8083/MariaDB8084で2端末の画像本体一致・URL/設定マージ・実HTTP同時更新409と競合選択・通信停止→復帰・OFF時の新ファイル非送信・別所有者file拒否を成功。client state/Blob保存はモデルでありブラウザ実IndexedDBではない。生成PNG68bytes、外部ユーザーファイルなし。fixture認証情報はGit除外へ一時保存しfinally除去、専用ユーザー/既知uploadも終了時除去。fixturePHPはCLI+SEARCH_TEST_MODE+create/cleanup限定、秘密値を進捗/Git/チャットに出さない。

account-dataのbackgroundOwnershipを一般syncOwnershipから独立して処理。同じアカウントのACK所有背景だけ削除し、OFF/端末専用/他所有者/未所有背景を保持。削除するBlobとメタ/manifest/checkpointはstore同一transaction、失敗時は全て保持。ローカル容量はfileIdが参照するfileSizeを加算し、JSONメタとBlobの合計を表示。旧size記録にも互換対応。IndexedDB不在でBlob操作がない削除は従来LocalStorage fallbackを維持。

検証1: 両DB実HTTPの製品JS2端末成功（上記3グループ）。検証2: 全JS単体21ファイル、account21/IndexedDB47/store14の追加再検証成功。IDBモデルで清掃quota失敗時Blob/メタ保持と再試行時ownedだけ除去を確認。検証3: 最新3環境PHP構文成功（MySQL99/MariaDB98/UI97、専用public/_test有無による差）、追加JS構文成功。Migration変更なし、既存両DB全8Migration往復証拠維持。今回新規ブラウザ操作・認証済みUI検証はなし。

account-data/accountは3環境に反映。store fallback補修は次回反映が必要。新規fixtureはtestsのみ、通常View/ルートから参照しない。

次は実IndexedDBで同期/清掃の保存・再読込、認証済みUIは実OAuth留保を区別、ACK永続化失敗後の再送抑止/再起動・読込所有者hint/途中アカウント切替を補修検証。競合ルール共有、条件全種編集/天気・地域/手動再生、巨大動画/実圧縮+DB容量、耐久的孤立清掃/Admin警告が残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 アカウント切替防御と実IndexedDB検証（2026-10-03）

背景一覧/ファイル読込へX-Background-Ownerを追加。JS sessionは認証開始時のownerを読込・409再取得・ファイル取得へ渡し、Controllerは現在の認証ownerと一致しない読込を403で拒否。owner hintは所有者を選択する権限にはしない。body user_id=nullも黙って無視せず拒否。同期中にCookieが別アカウントへ変わる実HTTP試験で、checkpoint/背景保存より前に拒否した。

store-databaseに独立namespaceを指定できる引数を追加（既定search-startpageは維持、BroadcastChannelも分離）。背景用検証画面はbackground-storage-verificationという専用DBを使い、通常ユーザーstate/認証を変更しない。不正putが同期DataCloneErrorになる際、Promiseの拒否だけで前のputをcommitしてしまわないよう明示transaction.abortを追加。モデルで補修対象を再現し、実ブラウザで状態不変を確認。

検証1: 両DB背景API43（owner read/file hint追加）、製品JS実HTTP2端末4グループ成功。4番目はread中アカウント切替の拒否。隔離fixture認証情報/ユーザー/既知uploadはfinally除去。検証2: 全JS単体21ファイル、IndexedDB49/追加構文成功。3環境PHP構文MySQL101/MariaDB99/UI99成功（public/_testによる差）。検証3: 実ブラウザの専用DBで背景2件/owned68bytes/local68bytes/ACKを保存→reload保持、不正put時全state/Blob不変、清掃→reload後owned0/local68/背景1/ACKなし、console warn/error0を確認。画像.test-output/phase7-background-storage-cleanup.png（Git除外）、タブ12保持。独立保存画面であり認証済み製品UI/実OAuthの証拠にはしない。

public/tests/Controllerは3環境へ反映（前回store fallbackも反映済み）。専用画面PHP/mjsはSEARCH_TEST_MODEあり8083のpublic/_testのみへ配置。専用DBと端末専用検証画像1件を残す。通常8083ライブラリの既存背景は保持。

次はserver成功後ACK永続化失敗・再起動での再送抑止を補修、背景競合ルール共有、全条件編集/天気・地域/動画手動再生。認証済みUIはユーザー指定の実OAuth留保を区別。巨大動画実HTTP/圧縮+DB容量、耐久的孤立清掃/Admin警告も残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 アップロード再送のサーバー側重複防止（2026-10-03）

009_background_upload_receiptsを追加。任意のX-Background-Request（64桁の小文字hex）と認証ownerごとに、経路/期待version/送信itemの原文/検査済み原本ファイルSHA256を照合。背景保存と応答記録を同じtransactionに保存し、owner row lock取得後にも再確認する。同じ送信の再試行は以前のACKを返し、新しいファイル・version・容量を作らない。同じ番号で異なるitem/bytesを送ると409。古いACKの再取得はその後の背景編集を変更しない。再送時の一時候補は除去、元の圧縮警告も保持。応答にreplayed:true、私有パス/保存名は引き続き非公開。SQLはRepositoryに集約。

検証1: 隔離MySQL8/MariaDB10.11で背景実HTTP各50項目成功（新規/置換再送、異なるメタ/bytes、番号形式、後続URL変更保護、候補清掃を追加）。検証2: 両DBで009 up/up/down/down/upとMigrator再実行0件、既存基盤39成功。rollback試験はSEARCH_TEST_MODE付き専用DBの空receiptテーブルのみ、通常UIでは未実行。検証3: JS単体21ファイル成功、送信番号header/不正番号の通信前拒否のtransport単体成功、3環境PHP構文成功、git diff --check成功。今回UI変更/ブラウザ操作なし。全9Migrationの新規Installer・全down/up一括検証は未実行、前回全8Migration証拠と区別する。

app/009/JS transportは3環境へ反映、通常開発UI8082も009を適用済み。専用API試験ユーザー/既知uploadは終了時除去。receiptはユーザー削除時cascade、現状自動期限削除なし。Phase7途中の保存で完了コミットではない。

次に実行すること: BackgroundSyncSession/Web adapterへ送信前の耐久的intent（同じ番号・元のpayload/version・原本Blob）を原子的保存し、ACK/intent除去も一体にする。ACK保存失敗/通信応答喪失/再読み込み後は同じ送信を回復し、編集中の新しいBlobを旧intentで上書きしない。今回の番号引数は製品sessionからまだ渡していないため、端末の再起動後の再送抑止全体は未完成。競合409のintent扱い、account切替/ログアウト清掃、同時再送専用試験も追加する。その後ルール共有・全条件編集/天気/地域/手動再生、巨大動画/実圧縮+DB容量・耐久的孤立清掃へ続く。Phase7未完了、8〜12未着手、Version1.0未完成、実OAuth留保は継続。

## Phase 7 端末送信記録と再送なしの中断回復（2026-10-03）

background-upload-intent.jsを追加し、製品BackgroundSyncSession/Web adapterへ接続。送信番号・owner・元payload/version・before/target・選択ルールと原本Blobの別コピーをIDB同一transactionで保存してから通信する。intentはowner別のbackgroundUploadIntents、Blobはランダムな別キーで通常背景と分離。ACKの背景メタ/ファイル/checkpoint/所有権保存とintent/送信用コピー除去も同一transaction。保存失敗/応答喪失/再読み込みで同じintentから再開。GET /api/backgrounds/receipts/{requestId}は認証ownerとread hintを照合し、保存済みならファイル本体を再送せず元ACKだけ回復。未送信のままOFF/保管/別ファイル/URLへ変更されたintentは送らず除去。owner変化時はそのownerの記録を保持し、他ownerのintentを利用しない。ログアウト同期データ削除は該当ownerの送信用コピーも原子的に除去、他ownerと未ACKの通常ローカル背景は保持。容量表示に送信用コピーも加算。

store-database.write/setManyに期待state条件を追加。IDB readwrite transaction内でintent集合を比較し、不一致なら全書込みをabort。別タブの記録を上書きしない。保存中編集の再計算では一度commitした条件を引き継ぎ、条件付き失敗後はDB状態を再取得して次回回復に利用。Broadcast読込の通知はstate全項目更新後に行う。

検証1: JS単体21ファイル/全JS構文成功。sessionでACK容量失敗→新session回復、応答喪失、後からの原本置換保持、送信前保存失敗で通信なし、未送信OFF取消、owner変化保持、後続クラウド編集を確認。IndexedDBモデルでprepare/ACK quota rollback、reload、条件不一致時Blob保持、保存中設定編集再計算を確認。accountのowner別intent清掃/容量も成功。
検証2: MySQL8/MariaDB10.11で背景API55（receipt本人200/不存在404/他owner404/guest401/hint403）、製品JS実HTTP2端末6グループ成功。通常remember Cookie、応答喪失/ACK失敗後の再開で実upload回数を増やさず、後続クラウド名前/version2を保持、同じ番号の並列upload2要求はversion1/同じfileRevisionを返す。client state/Blob永続化はモデルであり、実認証ブラウザとは区別。専用fixture秘密値/ユーザー/既知uploadはfinally除去。3環境PHP構文MySQL105/MariaDB102/UI102、両DB基盤39/Migration再実行0件成功。SQL schemaは009のまま、全9新規Installer/全Migration往復はまだ未実行。
検証3: 専用8083画面とbackground-upload-verificationという独立DBで原本68bytes+送信用68bytes/intent保存→reload保持、不正ACK putと古い条件の双方で全state/Blob不変、ACK確定→reload後原本68bytes/intentなし/送信用0/確定記録ありを確認。console warn/error0、画像.test-output/phase7-upload-intent-ack.png（Git除外）、IABタブ13保持。実quota枯渇ではなくDataCloneError/条件競合でabortを確認。実OAuth/認証済み製品UIの証拠ではない。fixturePHPはSEARCH_TEST_MODE付き8083のpublic/_testのみ、通常UIには配置しない。

Failures: session試験の全体write件数は初回Localの残項目処理も数えたため失敗、対象upload versionの検証へ訂正。一方、ACK済み背景もpendingInitial=Localのまま再適用すると後続Cloud名前を上書きしてversion3になる実Regressionを検出し、ACK済みIDだけ通常3-way mergeへ切替えてversion2/Cloud編集保持で再成功。

app/public/testsは3環境へ反映済み（公開fixtureは8083のみ）。送信記録は原本の追加コピーを保持するため一時的に端末容量が増えるが、保存できないときは通信しない。巨大500MiB実環境での容量/通信検証、ブラウザ実quota枯渇、認証済み実UIは未確認。Phase7未完了、8〜12未着手、Version1.0未完成。

次に実行すること: 全9Migrationを新規隔離MySQL/MariaDB Installerとup/down/upで確認。その後背景競合ルール共有、仕様全11条件の編集UI・天気/位置取得と手動地域の共通設定・動画手動再生へ進む。巨大動画実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃/Admin警告・Phase7完了条件監査も残る。実OAuthはユーザー指示の成功前提を維持し、未確認を最終監査へ留保。

## Phase 7 全9Migrationの新規Installerと全条件編集（2026-10-03）

新規専用project search-phase7-installer-20261003（MySQL8085/MariaDB8086）を作成。独立したDB/config/storageボリュームで既存環境を保持。integration.phpに009の存在・owner FK・不存在owner拒否・Installerの全Migration件数照合を追加。両DBで全9Migration初回up/再実行0件/逆順downで空schema/Web Installerから再up/再実行0件を含む39項目成功。InstallerのCSRF/セットアップ鍵/Session再生成/HTML Escape/秘密値非露出/非公開パス404/再インストール拒否も成功。設定値・秘密値は記録せずコンテナ内のみ。

background-rule-core/editorを追加し製品background.jsの時間だけの編集欄を置換。Time/Day/Date/Period/Weather/Temperature/Season/Random/Login State/Device/Screen Size全11種類の専用入力欄、AND/OR入れ子グループの追加/削除、条件ON/OFF、既存単一条件のグループ編集、保存/再編集/取消を実装。保存前に暦日・範囲・型・未知フィールド・空グループ・最大depth12/nodes100/32KiBを検証。サーバー既存BackgroundInputによる検証は維持。元の複合条件を時間UIで黙って削除する問題も解消。通常背景metadata/ruleへ保存するため既存ファイル・所有権・Cloud Sync保持を維持。新規DB/API/Migration変更は今回なし。

検証1: 全JS単体22ファイル成功。新規rule単体で全11デフォルト条件の一致、入れ子AND/OR、具体性優先、暦日/大小範囲/不正値・未知キー・depth/nodes/サイズ上限とdraftコピーを確認。全追加JS構文確認成功。
検証2: 新規両DB Installer/Migration各39、rule検証各22、基盤39/翻訳key一致、PHP構文各103成功。今回の新規環境は通常remember実HTTP試験をまだ再実行していない（前回8083/4のAPI55/JS実HTTP6グループとは区別）。
検証3: 製品UI8083で全11種類の入力欄を表示。screen幅1920〜320の保存拒否、正しい320〜1920とAND(screen,OR(weather rain,day weekdays+Saturday))の保存→reload→再編集で値/入れ子を保持。グループ削除で条件3→1、取消と再編集で保存済み3条件へ復帰。Englishでも保存済みScreen size/Weather/Day of week/AND/ORを確認。390px幅（document375/scroll375/form341/rules341、各scroll341）で横はみ出しなし、console warn/error0。画像.test-output/phase7-background-rules-mobile.png（Git除外）。IABタブ14保持、通常表示に戻し言語JA・選択背景Local upload retainedへ復帰。新規検証背景Rules verificationは同期OFFで保持し既存データは除去していない。実OAuth/認証済みUI検証ではない。

public/langは既存UI8082・8083/4・新規8085/6へ反映。新規test/integration修正は新規8085/6へ反映済み。最新の全Migration証拠は全9件へ更新、旧環境の設定/ボリュームを維持。今回の実装もPhase7途中のコミットで完了判定ではない。

次に実行すること: Weather IntegrationとBrowser Geolocation/Manual Locationの地域共通設定・ログイン時地域同期を接続（天気はUIに表示せず条件判定専用）。現在条件入力/検証は完成したが、製品描画contextへ実weather/temperatureをまだ与えていない。動画の手動再生、背景競合解決の保存ルール共有（現在checkpointだけ）、巨大動画実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃、Phase7機能ゲート照合を継続。管理画面の圧縮不足警告はPhase9で接続。Phase7未完了、8〜12未着手、Version1.0未完成。実OAuthはユーザーの成功前提指示を維持し未確認留保。

## Phase 7 天気APIと背景条件への接続（2026-10-03）

WeatherService/WeatherCache/WeatherController、POST /api/weather、weather-context.jsを実装。既存settings.themeRegionの手動緯度経度を太陽時テーマと共用し、背景描画contextへlatitude/longitude/weather/temperatureを接続。地域は一般設定同期の対象（同期投影/受信試験も追加）。天気データは背景判定専用、UI表示せずメモリ保持だけ。ライブラリ・条件切替・有効な天気/気温条件・地域がある場合だけ取得。待機中も描画を継続、取得後に再選択。重複通信防止、15分TTL、失敗60秒retry、地域変更時abortと古い応答拒否。

サーバー取得先はOpen-Meteo固定HTTPS（非商用公開/契約customerの2種類）、任意URL拒否。秘密キーはconfigだけ。CSRF必須、未知キー/非数値/座標範囲422、IP毎分30回429、ゲスト利用可/DB不要。小数2桁丸め、座標をブラウザ要求URLへ入れない。証明書検証/redirect拒否/約4秒/32KiB上限、接続URLやキーを例外に含めない。単位/数値/観測時刻/期限/WMO分類を確認（97含む）。私有キャッシュ256スロット上限、地域・設定のhashだけを識別に保存、成功15分/失敗60秒。設定無効化/キー変更はcacheより先に検査。config.exampleと新規Installerへweather設定を追加（既存config未設定も既定値で互換動作）、docs/weather.mdに設定と提供元利用条件を記録。DB/Migration変更なし、全9件の直前両DB往復証拠を維持。

検証1: 新規隔離MySQL8085/MariaDB8086でPHP天気各73項目成功。全WMO分類、0座標/0度気温、異常値/未知キー/古い時刻/単位/過大・不正JSON、取得例外、契約モード/不足キー、cache期限/再取得/失敗backoff/破損cache/無効化、Controller成功・拒否、座標/キー非露出を確認。取得は注入transportによるモデルと区別する。
検証2: 両環境の実HTTP各14項目成功。CSRF403/入力422/GET405、公開都市の実Open-Meteo取得200、製品WeatherContextから実CSRFと実weather APIへ接続し、製品selectBackgroundで天気AND気温に一致する背景選択を確認。実位置情報を取得せず公開都市を使用。認証不要APIであり実Discord成功の証拠にはしない。通常トップ200/Stack Trace非露出確認。初回HTTP試験は試験Cookie jarがSession再生成前後の同名Cookieを両方送って403となり失敗、最新値だけ残す形へ訂正。GET期待もRouter仕様405へ訂正し再成功。製品の認証/CSRFを緩和していない。
検証3: 全JS単体23ファイル・全JS構文成功。新weather contextで製品条件選択、同期投影/受信、TTL/通信重複/失敗再試行/旧地域応答拒否を確認。両DB基盤39/PHP構文107成功。git diff --check成功。新規ブラウザ操作は今回未実施、実画面の天気切替/位置許可は未確認。実HTTP+製品JS選択とブラウザUI検証を混同しない。

app/public JS/lang/config.exampleを既存8082/8083/8084と新規8085/8086へ反映。既存config/config.php・秘密値・ユーザーデータ・ボリュームは保持。新規PHP testsは8085/8086に配置。weather付き新規Installerの実実行は今回していない（直前全9Installer証拠と区別）。今回もPhase7途中の保存。

次に実行すること: Browser Geolocation入力ボタン、手動地域との共通UI・取得失敗/許可拒否/地域解除・提供元リンクとJA/EN案内を実装し、地域保存/再読込/天気条件切替の実ブラウザ検証を行う。OS位置許可を自動承認せず生成位置モデルと実許可を区別。ログイン時地域同期は既存sync対象だが認証済み実UIは未確認留保。その後動画手動再生、背景競合ルール共有、巨大500MiB実HTTP/実圧縮+DB容量、耐久的孤立清掃、Phase7ゲート監査。Admin圧縮不足警告はPhase9で接続。Phase7未完了、8〜12未着手、Version1.0未完成、実OAuth留保継続。

## Phase 7 共通地域設定と位置取得UI（2026-10-03）

region-core.js/region-settings.jsを追加しappearanceへ接続。現在地ボタンからだけBrowser Geolocationを呼び、粗い精度・10秒の取得timeout/20秒の全体timeoutで位置許可待ちも終了可能。小数2桁へ丸めてsettings.themeRegionに保存。手動地域・太陽時テーマ・背景weather/temperature/seasonと共通、一般設定同期の対象を維持。地域解除はnullを保存し、テーマは既存の端末テーマfallback、天気取得は停止。許可拒否/時間切れ/取得不可/非対応/不正座標をJA/ENで案内。入力中変更/解除/破棄で古い位置応答を採用しないgeneration防御、取得成功後の保存失敗はdraft保持。送信先と同期の案内・Open-Meteoリンクを追加、気象データ自体はUI非表示を維持。地域説明とstatusは全幅に配置してmobileの読みにくい半幅表示を補修。DB/API/Migration変更なし。

検証1: region-settings単体で生成座標の成功/丸め/0座標、取得options、許可拒否/取得不可/timeout/非対応/不正座標/同期例外/遅いcallbackを成功。最初はUIモジュールをNodeから直接importしてdocument未定義で失敗、位置取得処理をDOM非依存region-coreへ分離して再成功。全JS単体24ファイル成功。
検証2: 隔離MySQL8085/MariaDB8086で基盤39/翻訳キー一致・PHP構文107成功。既存のweather実HTTP14/全9Migration Installer往復証拠は直前検証を維持、今回はDB/認証/API変更なし。新weather付きInstaller実実行は未確認を維持。git diff --check成功。
検証3: 実製品UI8083で公開都市の手動地域入力→35.68/139.76の丸め→reload保持、緯度91の拒否、解除→入力空/pending=false→Englishへの遷移後も空を確認。JA/ENのボタン・案内・提供元リンクを確認。390pxでdocument375/scroll375、地域333/scroll333、修正後説明309幅、横はみ出しなし。console warn/error0。画像.test-output/phase7-region-mobile.png（Git除外）。IABタブ14を保持、viewport reset/通常トップ/日本語へ復帰。元の地域未設定へ戻し既存背景・検索データは保持。実位置情報の取得/OS許可は行わず、位置取得は生成モデルだけ。実OAuth/認証済み地域同期・実天気による画面切替の証拠とは扱わない。

public/assets/langは8082〜8086の5アプリ環境へ反映済み。config/ユーザーデータ/ボリュームは保持。docs/weather.mdを現状へ更新。Phase7途中の保存、8〜12未着手、Version1.0未完成。

次に実行すること: 製品画面で天気AND気温条件による背景切替を確認（公開都市・生成背景のみ、気象データ非表示）。位置取得UIの保存/拒否/解除中の古い応答は専用生成fixtureで追加検証し実OS許可と区別する。その後動画手動再生、背景競合ルールの他端末共有、巨大500MiB実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃、Phase7ゲート監査へ進む。新weather付きInstallerの新規実実行もゲートで確認。Admin圧縮不足警告はPhase9、実OAuthと認証済み実UIは未確認留保を継続。

## Phase 7 背景動画の手動再生（2026-10-03）

background-playback.jsと検索画面の再生/一時停止ボタンを追加。動画選択時だけボタンを表示、画像/色/モバイル代替画像/ライブラリ無効時は非表示。動画がaria-hiddenの背景層にあるため操作はその外の通常画面へ配置。再生失敗も通常画面のstatusで案内。手動操作は現在の再生sessionだけへ適用し、ライブラリのAuto Play/Loop/Mute/Speed/Pause設定は保持。非表示時pause、元々の再生意図がある場合だけ表示復帰で再開。手動停止を10秒再判定で取り消さない。Loop OFFの終了は勝手に再生しない。再生拒否を自動連打せず手動retry可能。非同期play完了後の背景切替/停止をrevisionで検査し、旧動画をpauseする。DB/API/Migration変更なし。

検証1: 新playback単体で手動play/pause、非表示pause/復帰、Loop OFF終了、再生拒否/retry、設定Pauseから手動play、遅いplay完了/切替後pauseを成功。全JS単体25ファイル/追加JS構文成功。
検証2: 隔離MySQL8085/MariaDB8086で基盤39/翻訳key一致/PHP構文107成功。直前APIと全9Migration往復の証拠を維持、今回変更なし。git diff --check成功。
検証3: 圧縮検証専用コンテナFFmpegで6秒の単色MP4を生成（ユーザーファイル不使用）、8083のpublic/_testにだけ配置。製品UIからURL動画/Auto Play OFF/Cloud Sync OFFとして保存。実video readyState4/paused=true/time0、再生クリックでpaused=false/time進行、一時停止paused=true、再開paused=falseを確認。reload後time0/paused=trueを保持（Auto Play OFF）。390pxでも実再生・停止、document375/scroll375、console warn/error0。画像.test-output/phase7-video-playback.pngとphase7-video-playback-mobile.png（Git除外）。初回mobile操作は未完了onboardingのmodalが出て次ボタンを見つけられず失敗、表示状態を確認し「あとで続ける」で閉じて再成功。許可や認証を緩和していない。IABタブ14保持、viewport reset、通常JAトップへ戻しLocal upload retainedを再選択、video0/操作ボタンhidden/image naturalWidth1を確認。生成検証動画とVideo playback verification背景（同期OFF）は隔離8083に保持。実動画Upload、非表示復帰の実ブラウザ、Loop OFF終端の実ブラウザ、EN操作は今回未確認（単体/翻訳一致と区別）。

public/assets/langは8082〜8086へ反映済み。背景設定/元Blob/ユーザーデータ/ボリュームを保持。Phase7途中コミット、8〜12未着手、Version1.0未完成。

次に実行すること: 天気AND気温の製品実画面切替、位置取得UIの生成fixtureによる保存/拒否/解除中の旧応答拒否を確認（実OS位置許可と区別）。動画のEN・Loop OFF終端・表示復帰/モバイル代替を実ブラウザで追加検証。その後背景競合ルールの他端末共有、巨大500MiB実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃、weather設定付き新規Installer、Phase7ゲート監査へ進む。Admin圧縮不足警告はPhase9、実OAuth/認証済みUIは未確認留保を継続。

## Phase 7 実天気の背景切替とブラウザ通信の補修（2026-10-03）

製品8083のUIでWeather condition verification（同期OFF/単色#17875d）を追加。AND(全6天気カテゴリ, 気温−100〜70, 未ログイン)の3条件を保存・再編集で確認。手動選択はLocal upload retainedにして条件切替へ変更し、地域未設定では検証背景にならないことを確認。公開都市の手動地域を入力しても最初は検証色にならずRegressionを検出。

原因: WeatherContext既定fetchをthis.fetcherとして呼ぶとnative browser fetchのreceiverがWindowでなくなり失敗する。注入fetchとNodeの実HTTP試験はこの条件を再現していなかった。既定値をglobalThis.fetchを呼ぶwrapperへ修正し、native相当のreceiver要求モデルを専用単体へ追加。両DBの実HTTPが成功しても実画面成功とは限らない証拠として記録する。デバッグ中のperformance APIはCUAのread-only scopeで未対応と分かり、その方法を中止。認証/CSRF/ブラウザ制約を緩和せず製品コードを修正した。

地域設定もsetSettingがPromiseを返さないためawaitしても永続化の完了/失敗を確認できない問題を発見。saveSettings({themeRegion},appearance)へ切替え、設定と履歴を同じ原子的保存として待つ。保存完了後だけ成功表示し、失敗時のdraft保持を有効にする。weather HTTP試験の許可先に既存の隔離8083/8084を追加（通常8082は引き続き対象外）。DB/API/Migration変更なし。

検証1: 修正後の実画面で地域あり→rgb(23,135,93)の検証背景、地域解除→元の別条件背景へ復帰を確認。気象データのmain表示なし、console warn/error0。画像.test-output/phase7-weather-background.png（Git除外）。最初の証拠画像は未完了onboarding modalで覆われたため「あとで続ける」で閉じ、通常画面を撮り直した。UI操作は現在地取得を行わず公開都市だけ。
検証2: 全JS単体25ファイル・weather-context/region-settings構文成功。既定fetch receiverがglobalThisであることとcached取得を確認。既存原子的store試験を含む回帰成功。実ブラウザquota失敗/地域UIの保存失敗操作は今回未実施、保存処理の既存原子性証拠と区別。
検証3: 実HTTP weather各14を8083/8084/8085/8086で成功（公開都市の実取得/製品JS選択/CSRF/Validation）。新規MySQL/MariaDB基盤39成功。PHP/SQL変更なし、直前PHP構文107・全9Migration新規Installer往復の証拠維持。git diff --check成功。

修正JSは8082〜8086へ反映済み。通常JAトップ/地域未設定/手動切替/Local upload retainedへ戻しimage naturalWidth1を確認。検証背景は同期OFFのまま「保管」し復元可能、既存背景・ユーザーデータは保持。タブ14をhandoff保持。今回もPhase7途中の保存、8〜12未着手、Version1.0未完成。

次に実行すること: 位置取得UIの生成fixtureによる保存/拒否/解除中の旧応答拒否と永続化失敗を確認し、実OS許可とは区別。動画EN・Loop OFF終端・表示復帰/モバイル代替を実ブラウザで追加検証。その後背景競合ルールの他端末共有、巨大500MiB実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃、weather付き新規Installer、Phase7ゲート監査。Admin圧縮不足警告はPhase9、実OAuth/認証済みUIは未確認留保継続。

## Phase 7 位置取得UIの実IndexedDB保存・失敗・遅延検証（2026-10-03）

地域UIをstore非依存のregion-editor.jsへ分離し、通常region-settings.jsからreadRegion/saveRegion/locateRegion・JA/EN node/tを渡す。通常動作の保存はsaveSettingsのまま、位置取得はrequestRegionのまま。通常storeをimportせず同じ製品UIを独立DB/生成位置へ接続できるようにした。region-preview.php/mjsをtestsに追加。PHPはSEARCH_TEST_MODE以外404、通常View/Routeは参照しない。公開fixtureは8083のpublic/_testのみ、認証バイパスなし。region-settings-verificationという専用DBを使い、通常設定/認証/実位置情報/外部天気通信に触れない。

検証1: 実ブラウザの専用画面で現在地ボタン→生成0/0→成功表示/実IDB保存→reloadで0/0保持。生成許可拒否では値を保持して失敗案内。位置取得中の地域解除→古い0/0応答で未設定を上書きしない。現在地取得中に手動35.68/139.76を保存→古い0/0応答を拒否し手動地域保持。入力破棄→古い応答拒否、reloadでも最後の保存地域保持。OS位置取得・実許可はしていない。
検証2: 専用DBへDataCloneErrorを起こす不正値を同時putし、transaction abortを製品UIから受ける。保存済み未設定のまま、入力0/0保持、pending=true、成功表示なし/保存失敗案内。失敗モード解除後の手動保存で回復・reload保持を確認。実quota枯渇ではなく実IDB DataCloneErrorによる失敗。画像.test-output/phase7-region-save-failure.png（Git除外）、console warn/error0。タブ15をhandoff保持。通常8083タブ14のデータは触れていない（このturnでmarkし直してはいない）。
検証3: 全JS単体25ファイル/region entry・editor・fixture構文成功。隔離MySQL8085/MariaDB8086で基盤39/翻訳一致/PHP構文108成功。DB/API/Migration変更なし、全9Migration Installer往復の直前証拠維持。git diff --check成功。通常UIの再操作は今回していないが同じeditorをfixtureで実行し、既存通常UI証拠と区別。

JSは8082〜8086へ反映。fixture公開はSEARCH_TEST_MODE付き8083のみ、新規両DBのPHPはtests配下だけ。通常config・データ・ボリューム保持。docs/weather.mdの既知未確認（実OS許可/認証済み地域同期）を維持。Phase7途中保存、8〜12未着手、Version1.0未完成。

次に実行すること: 動画EN・Loop OFF終端・表示復帰/モバイル代替を実ブラウザで確認。その後背景競合ルールの他端末共有、巨大500MiB実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃、weather付き新規Installer、Phase7ゲート監査へ進む。位置取得UIの生成/保存/拒否/解除/手動入力/破棄中の古い応答/保存失敗は今回確認済み。実OS許可と認証済み地域同期/実OAuthは未確認留保継続。Admin圧縮不足警告はPhase9で接続。

## Phase 7 動画の英語操作・終端・モバイル代替（2026-10-03）

製品8083の既存Video playback verification（同期OFF）を英語UIで編集。Auto Play OFFを維持、Loop OFF、生成PNGのサイト内fallback URLを保存。通常画面のPlay background videoで実MP4 paused=false、loop=false/muted=true/speed1を確認。6秒の終端でended=true/paused=true/currentTime=duration=6、ボタンがPlayへ復帰。後続の同一動画の再確認でも終端を保持し勝手に再生しない。英語の保存・再生操作が利用できることを確認。

検証1: 上記実動画の終端/英語ボタンを確認。画像.test-output/phase7-video-ended-en.png（Git除外）。生成MP4を利用しユーザーファイル/本番データは不使用。Autoplay拒否や実Uploadとは区別する。
検証2: 390pxへ変更するとvideo0、fallback img naturalWidth1/指定サイト内パス、再生ボタンhidden=true、document375/scroll375。通常viewportへresetするとvideo1/img0/paused=true（Auto Play OFF）、手動再生も成功。画像.test-output/phase7-video-fallback-mobile.png。既存JS単体25ファイル成功（非表示pause/表示復帰はモデルで既に検証済み）。
検証3: console warn/error0、通常JAトップへ戻しLocal upload retained再選択、image naturalWidth1/video0/ボタンhidden=trueを確認。UI設定の変更だけでアプリコード・DB/API/Migrationに変更なし。PHP構文108/両DB基盤39/全9Migration Installer往復は直前証拠を維持、今回は再実行していない。git diff --checkを保存前に確認。

表示復帰の実ブラウザ検証は未確認。別タブ作成後も元ページのdocument.hidden=falseだったため、本来の非表示状態を作れず成功扱いにしない。JSON healthを一時タブで開く試みはbrowser側ERR_BLOCKED_BY_CLIENT、空タブ17として作成されたことをinventoryで確認して閉じた。APIのAJAX通信が失敗した証拠とは扱わない。実非表示・復帰を作れるブラウザ環境で最終監査時に確認する。通常タブ14は前turnで保持されず既に存在しなかったため、新規タブ16で検証。生成地域fixtureタブ15はそのまま保持。新規タブ16をhandoff保持、onboarding案内を閉じ通常表示へ復帰。

Video playback verificationはLoop OFF/fallback追加を保存して隔離8083に保持（同期OFF）。生成PNGを.test-outputからpublic/_testに配置。通常config/ユーザーデータ/ボリュームを保持。Phase7未完了、8〜12未着手、Version1.0未完成。

次に実行すること: 背景競合解決の保存ルールを他端末へ共有する。現状background checkpoint.rulesのみ、一般同期のsettings.syncRulesの共有方式と整合させる。アカウント混同/履歴・一般競合ルールの破壊/並行同期を避け、両DB実HTTP2端末と単体で確認。その後巨大500MiB実HTTP/実圧縮+DB容量、耐久的孤立ファイル清掃、weather付き新規Installer、Phase7ゲート監査。動画非表示復帰、実OS位置許可、認証済み地域同期/実OAuthは未確認留保を維持。Admin圧縮不足警告はPhase9。

## Phase 7 背景競合ルールの他端末共有（2026-10-03）

background-sync-rules.jsを追加し、背景競合の保存ルールを一般同期settings.syncRulesへ接続。一般同期checkpointが現在ownerと一致する場合だけ共有設定を使用し、それまではowner別backgroundCheckpoint.rulesを維持。旧ルールは一度だけ共有設定へ昇格、共有設定の明示削除後に旧checkpointから復活させない。共有済みルールは背景JSON pathだけを読み、一般競合ルール・他の設定を保持。背景ACKとルール保存を同じsetMany transactionへ組込み、latest stateから再計算。背景変更なしでもfinishで旧ルールを移行し、同一settingsの再保存を省略して250ms同期ループを防止。BackgroundSyncSessionは毎処理段階で現在の共有ルールを参照。DB/API/Migration変更なし、既存sync settings Validation/認証/CSRF/owner照合を維持。

検証1: 全JS単体26ファイル成功。新shared-rules試験で旧ルール昇格、共有優先、個別削除/設定全体削除後の非復活、一般ルール保持、未同期accountの遅延昇格、他owner不使用、不正JSON path/選択の除外、背景選択、ACKとの原子計画を確認。変更JS/HTTP試験の構文成功。追加path制限後の専用試験も再成功。

検証2: 隔離MySQL8083/MariaDB8084の実HTTP・製品BackgroundSyncSession/SyncSessionで各8グループ成功。片端末の競合Cloud選択とremember→一般sync APIで共有→別端末の背景競合をdialogなしでCloudへ解決→共有ルール削除と非復活を確認。一般同期の通信中に背景ルールを保存してもACK後に保持、次回実通信で他端末へ共有されることも確認。既存のmultipart/private bytes/409/オフライン/同期OFF/他owner拒否/アカウント切替/ACK失敗・通信応答喪失のreceipt回復/重複uploadを再成功。テスト用rememberログインとstate/Blobモデルによる端末試験であり、実Discord/認証済み実ブラウザ・実IndexedDBの同時sync検証とは区別する。最初の追加試験は試験adapterのString ownerと数値user.idの比較でfalseとなり両DB失敗、adapterの型を合わせ再成功。製品認証やCSRFを緩和していない。専用テストアカウント/ファイル/一時認証JSONはfinallyで清掃、秘密値は記録しない。

検証3: 隔離新規MySQL8085/MariaDB8086で基盤各39（翻訳key一致含む）とapp/public/tests内PHP構文成功。今回PHP変更なし、全9Migration往復の直前証拠を維持（今回再実行なし）。git diff --check成功。今回新規UI操作なし、既存競合UIの証拠を維持。ローカル5アプリ8082〜8086へpublic/assets/js反映、config/ユーザーデータ/ボリューム保持。

Phase7途中の保存。Phase8〜12未着手、Version1.0未完成。

次に実行すること: 巨大500MiB実HTTPと実圧縮+DB容量、耐久的孤立ファイル清掃、weather設定付き新規Installer実行、Phase7ゲート監査へ進む。動画非表示復帰の実ブラウザ、実OS位置許可、認証済み地域/背景ルール共有UI、実OAuthは未確認留保。Admin圧縮不足警告はPhase9。

## Phase 7 実500MiB動画の上限・容量・清掃（2026-10-03）

tests/background-large-http.phpを追加。通常remember/CSRF付き製品upload APIへcurlからファイルをストリーム送信し、PHP内で全体を文字列化しない。検証用FFmpegで生成した1秒128x96のMP4へISO BMFF free boxを追加し、正確に524288000 bytesへ拡張。ユーザー動画を使わず有効な映像を維持し、sparse生成後もHTTPでは全byteを実送信。専用identity ...963を事前存在チェックし、独立/tmp内Cookie config・jar・response・生成動画だけ使用。秘密を出力せずfinallyでアカウント/生成データを清掃。

検証1: MySQL8083で実HTTP12項目成功。500MiBちょうど201・encoderなしwarning・私有保存byte数とSHA一致・DB使用量500MiB・Range32byte/206と全体size・500MiB+1 byteの413/BACKGROUND_TOO_LARGE・拒否時metadata/使用量不変・孤立ファイルなし・URLへのsource変更で巨大file削除/使用量0・PHP diagnostics非露出を確認。PHP upload_max_filesize500M/post_max_size502Mを実環境で確認。HTTPの他CSRF/owner拒否は直前背景API/2端末試験の証拠を維持、今回新たな悪用試験ではない。

検証2: MariaDB8084でも同じ実HTTP12項目すべて成功。両DBで生成データ/一時認証ファイルはfinally清掃。入力seedは.test-output/large-boundary-seed.mp4と両アプリ/tmpに保持（生成データ、Git除外）。圧縮がないときの最大byte保持試験であり、巨大映像のFFmpeg変換/ブラウザUpload/IndexedDB quota枯渇成功とは扱わない。

検証3: 全JS単体26ファイルの既存Regression成功、git diff --check成功。今回製品PHP/DB/Migration変更なし。新large PHPは両DBで実行され構文/Fatal Errorなし。新規UI操作なし。Phase7未完了、8〜12未着手、Version1.0未完成。

tests/background-compressed-quota.phpも作成済み。実PNG encoderから得たfinal bytesをDBへ保存し、原本が全quotaを超えていても圧縮後2件がちょうどquotaに収まること、3件目413とrollback、置換時の旧size減算、原本保持を確認する計画。現時点では構文も実行も未確認、成功扱いにしない。

環境準備の停止理由: 圧縮image search-compression-test:20261002から2個のDB接続用アプリを作るDocker runが、自動承認レビューの利用上限エラーで未実行。unsafe判定ではなくreview自体が完了できなかったとのtool応答。回避して実行しない。search-compression-db-mysql-20261003 / search-compression-db-mariadb-20261003は今回の操作では作成されていない（再開時はinventoryで確認）。予定network search-phase7-20261002_default、既存のmysql-config/mariadb-config volumeをread-only mountし、storageはsearch-compression-db-{kind}-20261003-storageという独立volume。公開port不要、SEARCH_TEST_MODE=1/SEARCH_LOCAL_DEVELOPMENT=1。appソースと新testをdocker cpしてPHP検証する。既存config・DBvolume・ユーザーデータは保持。

今回の新test2ファイルと本進捗は未コミット。Gitへのwriteも承認を必要とするため、review利用上限が解消後に最低3検証記録とともに途中コミットする。spec.mdをstageしない。

次に実行すること: 自動承認レビューが利用可能か確認し、未作成の圧縮+両DB環境をinventoryで確認してから準備する。background-compressed-quota.phpの構文/実行を両DBで検証し問題を修正。次に圧縮付き製品実UploadとDB容量の接続、耐久的孤立ファイル清掃、weather付き新規Installer、Phase7ゲート監査。実OS位置許可/動画非表示復帰/実OAuth/認証済み実UIは未確認留保。

## Phase 7 実圧縮・製品HTTP・両DB容量の接続（2026-10-03）

自動承認review利用上限は今回Docker inventory/runで解消を確認。前回未作成だったsearch-compression-db-mysql-20261003 / search-compression-db-mariadb-20261003を準備済み。search-compression-test:20261002（Imagick/GD/FFmpegあり）、network search-phase7-20261002_default、既存テストconfig volume read-only、新規独立storage volume。公開portなし、内部loopbackでHTTP検証、SEARCH_TEST_MODE=1/SEARCH_LOCAL_DEVELOPMENT=1。最新app/publicを反映。config・ユーザーデータ・既存ボリュームは変更していない。新規Installer環境8085/6とは別。

検証1: background-compressed-quota.phpを両DBで各8成功。生成PNGを実encoderで縮小、原本が全quotaを超える条件でもfinal bytesで2件の保存がちょうどquotaに成功。3件目413はmetadata/usage不変、置換は旧final bytes減算、私有file/DBfile_size/file_path一致、原本保持。限度値を注入した製品Repositoryと実codecによる試験であり、HTTPアプリのquota設定自体は変更しない。

検証2: background-compressed-http.phpを追加、両DBで各15成功。通常remember/CSRF付き製品APIからPNG実圧縮→応答/私有file/DB/usageのfinal bytes一致→認証downloadのSHA/dimensions/MIME保持→実置換/旧file清掃→409古い置換拒否/孤立なし→URL変換file清掃/usage0を確認。生成2秒MP4のHTTP uploadも実FFmpegで縮小、返却type/size・DB/usage・認証download一致、入力stagingを捨てoutput1件だけ保持。検証identity ...965、codec/quota用...964はfinallyで削除。通常ユーザー/本番DBを不使用。最初のHTTP試験は別プロセスのunlink後に試験PHPのstat cacheが残りURL変換のfile不存在確認で両DB失敗。clearstatcacheで実file状態を再取得して成功（製品コード変更なし）。Fatal出力はCLI test自身の失敗であり本番API診断の露出とは区別。

検証3: 新PHP3試験を両codec環境で構文確認成功。既存の実圧縮Regression各17（色/alpha/寸法/JPEG EXIF/GIF frames/失敗fallback/私有permission/partial output清掃含む）と基盤各39/翻訳key一致成功。JS26全単体は直前turnで成功、今回PHPテストのみ追加のため再実行なし。git diff --check成功。新規UI操作/実OAuth/実ブラウザUpload/500MiB実FFmpeg変換は未確認。500MiB入力上限のcodec-free実HTTP各12は前回証拠を維持、今回再実行なし。DB/Migration/製品コード変更なし、全9Migrationの直前証拠を維持。

今回新規tests3ファイルと前回巨大HTTP結果・最新再開記録を途中コミットする。Phase7はまだ未完了、8〜12未着手、Version1.0未完成。

次に実行すること: 耐久的孤立ファイル清掃を実装する。現状HTTP finallyと置換後unlinkは確認済みだが、プロセス停止・unlink失敗・owner削除で残る私有ファイルの後続回復は未実装。DB参照中（保管済み/同期OFF含む）や通信中stagingを削除しない仕組み、安全なパス/シンボリックリンク拒否、両DB+実filesystem検証を追加。その後weather付き新規Installer、Phase7ゲート監査。実OS位置許可/動画非表示復帰/実OAuth/認証済み実UIは未確認留保、Admin圧縮不足警告はPhase9。

## Phase 7 耐久的な孤立ファイル回収（2026-10-03）

BackgroundCleanupとbin/cleanup-backgrounds.phpを実装。既定dry-run、--applyでDB非参照かつ24時間以上経過した正規生成ファイルだけ回収。参照は保管済み/Cloud Sync OFFも含む。アカウント削除後の参照消失も対象。未知名/新しいstaging/symlinkは削除せず、storage ancestor/lockのsymlinkは503で拒否。DB読み取りに失敗したownerは削除に進まない。CLI応答は件数だけ、失敗時generic error、設定/秘密/パス/stack traceを表示しない。unlink失敗はfailed件数を返し残存fileを後続runで再検査。DB/Migration変更なし。

BackgroundUpload::withOwnerLockを追加、製品Controllerのupload受取り前から圧縮・DB保存・finally清掃までowner別flockを保持。collectorは同じlockの非待機取得でbusy ownerを見送る。終了/プロセス停止でOSがlock解放し後続runが回復。lock fileは0600、symlink/hardlink拒否、inode照合。privileged maintenanceが新規作成するlockの所有者は親owner directoryへ合わせ、root-owned lockで次のPHP worker uploadを阻害しない。ControllerにSQLなし、View変更なし、認証/CSRF/owner/receipt/DBquota維持。

検証1: 専用DB/ランダムtmp領域のbackground-cleanup.phpで両DB各15成功。dry-run、古いorphan回収、active/archive/sync-off保持、recent/未知名/外部symlink保持、DBquota不変、再実行0、PHPworker lock所有者保持、別PHPプロセスによる実lock保護→proc_terminate後回収、owner削除後3file回収、owner symlinkをたどらない、lock/ancestor symlink拒否を確認。fixture identity ...966とtmp領域はfinallyで除去。DB障害/実unlink失敗の専用試験は今回未実施（fail-closed制御と区別）。運用CLI --applyを通常検証storage全体へは実行せず、専用tmpで実回収した。

検証2: MySQL8083/MariaDB8084で既存背景API各55と製品JS実HTTP2端末各8グループ再成功。特に同時upload番号のreceipt再利用、ACK/応答喪失回復、置換と409、CSRF/owner/MIME/Range/公開情報非露出を維持。codec付き両DBの製品圧縮HTTP各15も再成功、lock追加による画像/動画保存・clear/置換のRegressionなし。テストremember認証であり実Discord UIではない。

検証3: 変更PHP5ファイルの構文/両DB基盤各39（翻訳一致含む）成功。実運用と同じwww-dataユーザーで両DBのCLI --dry-runを実行、全件数0/exit0（削除なし）。git diff --check成功。今回JS変更なし、直前全26JS単体の証拠維持。新規UI操作なし。全9Migrationの直前証拠を維持、今回DB変更/再往復なし。

appは8082〜8086とcodec両DBへ反映。maintenance binは8082〜8086へ反映済み。docs/background.mdに毎日PHP workerと同じOSユーザーで実行する運用手順を追加。ホストの定期実行やCodex automationは自動登録していない。config/ユーザーファイル/既存DBvolumeを保持。今回もPhase7途中保存、8〜12未着手、Version1.0未完成。

次に実行すること: weather設定付き新規Installerと全9Migrationを新しい隔離MySQL/MariaDBで再検証し、Phase7仕様/機能ゲートの不足を照合する。永久削除の仕様、実動画Upload/巨大圧縮/ブラウザ容量失敗の証拠範囲、ローカルとCloudの所有権・条件・JA/EN/responsiveを監査。実OS位置許可/動画非表示復帰/実OAuth/認証済み実UIは未確認留保、Admin圧縮不足警告はPhase9。

## Phase 7 新規Installerゲートと認証状態の追従（2026-10-03）

新規project search-phase7-gate-20261003を準備。MySQL8089/MariaDB8090、DB/config/storage独立volume、最新Dockerfile/PHP8.2。既存環境は保持。Compose定義.test-output/phase7-gate-compose.yamlはGit除外。ランダムDB秘密はprocess envだけ、終了時に元envへ復元し値は出力・記録しない。既存9MigrationのDB変更なし。

検証1: tests/integration.phpにweather初期設定の確認を追加。両DB各40成功。空DB→全9Migration up→再up0→逆順down/空schema→Web Installerで全9up→再up0を確認。認証Secretなしの初期設定、setup/CSRF/session再生成/管理者予約/秘密非露出/HTML escape/私有パス拒否/再導入拒否を維持。新Installer weather.enabled=true/mode=non-commercial/api_key空を実設定で確認。

監査でbackground.jsがLogin Stateを初回だけ取得する不足を発見。同じページで認証が切り替わった場合に追従するため、sync-api.jsのsyncUserが成功/401の確定状態変化だけsearch-auth-changeを通知し、背景が選択を再判定。重複通知なし、503等では確認済み状態を変更しない。背景library表示中は60秒ごとに確認、表示復帰で再確認、通信重複なし。一般/背景同期OFFでも背景の状態を確認できる。server側認証/owner判定は従来のまま、イベントで権限を設定しない。実OAuth bypassなし。新規sync-auth-state.test.mjsを追加。

検証2: 全JS単体27ファイル/変更JS構文成功。新単体でログイン/ログアウト/別account、重複通知なし/失敗時維持を確認。既存MySQL8083/MariaDB8084の製品JS実HTTP2端末各8グループ成功。今回は認証切替後の製品背景描画を実ブラウザで操作していない（単体/同期実APIと区別）。

検証3: 新規両DBで全PHP114ファイル構文/基盤各39・翻訳一致成功。tests/weather-http.mjsへ専用8089/8090を追加し実HTTP各14成功。新規configから公開都市の実Open-Meteo/CSRF403/Validation422/GET405/製品JS背景選択を確認。実現在地を不使用。git diff --check成功。

docs/phase7-gate.mdへ§57〜67/87/120〜121と持越し§82の証拠と未確認を表に整理。主な背景機能は確認済みだが、初回WizardがTheme/Solidだけで背景ライブラリ/Preset選択が未接続。Phase6持越しを補修してからPhase7完了判定へ進む。永久削除ボタンは必須仕様として明記されず、保管背景のfile/容量保持を維持する。実ブラウザ動画file選択/実容量枯渇/巨大FFmpeg変換/実非表示復帰/実OS位置許可/実OAuth/認証済みUIを成功扱いにしない。

JSは8082〜8086/8089/8090へ反映済み。既存config・ユーザーデータ・volumeを保持。新規UI操作なし。今回もPhase7途中保存、Phase8〜12未着手、Version1.0未完成。

次に実行すること: onboardingのBackgroundステップにPreset/保存済みlibraryを接続し、選択・保存・Skip/Back・初回/再開とJA/EN/mobileを検証する。docs/phase7-gate.mdの未達を再照合してPhase7機能ゲートを判定。Admin codec警告はPhase9、環境依存の実OAuth/実OS/実ブラウザ認証は最終監査への留保を継続。

## Phase 7 初回案内のプリセット・背景ライブラリ（2026-10-03）

onboarding-coreに有効な背景候補を導出するonboardingBackgroundsを追加。3Presetと保存背景、normalize済み/保管除外/ID重複除外。BackgroundステップはTheme/Solid/libraryを選べ、libraryはPreset/保存済みimage/video等を選択。設定保存時に候補IDを再検査し、消えた/保管済み/不正な候補を拒否。library選択はbackgroundSelectedとmanual切替を同じsaveSettingsで保存し、既存の条件/ランダムで指定背景が別に切り替わらないようにする。背景本体/Blob/Cloud設定を変更しない。選択方法ごとにcolor/背景候補を表示、画像/動画追加は設定ライブラリでできるJA/EN案内を追加。DB/API/Migration変更なし。

検証1: オンボーディング単体26と全JS27ファイル、変更JS構文成功。Preset/保存画像、保管/不正URL/duplicate除外、欠落ID拒否、手動切替、旧Theme/Solid/Skip関連Regressionを確認。候補取得は保存直前のstateを使い、候補未存在を成功扱いにしない。

検証2: 製品8083の実UIでJA 3/8 Backgroundから森選択→保存→Backで森保持と実linear-gradient、夜明けへ未保存変更→Skip→Backで森を保持、Local upload retained選択→保存→Back→reloadで背景step/画像保持を確認。保管Weather背景が候補に出ず3Preset+有効libraryが見える。390pxでdocument375/scroll375/dialog358/image naturalWidth1、横はみ出しなし。英語へ変更して同じ背景step、Solid選択時color表示/library候補hiddenを確認。libraryへ戻しVideo playback verification選択→保存→Backで選択保持、mobile video0/fallback image naturalWidth1を確認。画像.test-output/phase7-onboarding-background-mobile.pngとphase7-onboarding-background-en.png、Git除外。最初のEN dialog待機は名称を誤りtimeout、実DOMでMake this your start pageを確認して操作継続。製品不具合ではない。通常JA/Local upload retained/手動へ戻しimage1/video0、onboardingの「あとで続ける」で閉じ、viewport reset、warn/error0、tab16 handoff保持。ブラウザ検証はゲストであり実OAuth成功の証拠にしない。

検証3: 新規両DB8089/8090で翻訳key一致/基盤各39、PHP114構文成功。DB/API変更なし、直前全9Installer往復各40の証拠維持（今回は再実行なし）。git diff --check成功。JS/langを通常8082・背景8083/4・新規8089/90へ反映済み、他隔離環境は必要時に反映。config/既存背景・Blob/ユーザーデータ/volumes保持。

docs/phase7-gate.mdでWizard持越しを確認済みへ更新、docs/spec-audit.mdの古いPhase7状態を最新証拠へ更新。Phase7の最終機能ゲートはまだ監査中、8〜12未着手、Version1.0未完成。

次に実行すること: 生成MP4のブラウザfilechooserから製品Local Upload UI→実IndexedDB保存→描画→reload→編集保持の経路を確認（file-uploads documentationを読む）。既存画像の証拠と動画URL/動画HTTP試験を区別する。必要な修正後にPhase7ゲート判定、環境依存未確認をPhase12監査へ追跡しPhase8へ進む。実OAuth/認証済み実UI/実OS位置許可/非表示復帰/巨大FFmpeg変換/実quota枯渇は未確認留保。

## Phase 7 ブラウザからのローカル動画保存（2026-10-03）

生成済み6秒320x180 MP4（4131 bytes）を隔離8083のゲスト・Cloud Sync OFFで実filechooserから選択し、製品背景追加画面でLocal video upload verificationとして保存。ユーザーファイル/本番DB/実OAuthを不使用。製品コードの修正なし。

検証1: 動画/端末のファイル、Auto Play OFF/Loop OFF/Mute ONで保存成功、ライブラリに同期OFFとして表示。製品videoはblob URL、readyState4/duration6/320x180/paused true。画面の再生ボタンでpaused false/currentTime進行を確認。filechooser setFiles後の読み取り専用DOMでinput.filesを読む試みは観測APIが公開せずTypeError。保存成功と実動画デコードでファイル選択を確認し、観測エラーを製品不具合として扱わない。

検証2: reloadで新しいblob URLが生成され、readyState4/duration6/320x180/paused true/currentTime0。永続Blobからの再描画を確認。再開Wizardは4/8で開き「あとで続ける」で閉じた。設定の編集でvideo/upload/Loop OFF/Cloud Sync OFFを維持。ファイルを再選択せず速度1.5を保存し、実video playbackRate1.5/readyState4/duration6を確認。

検証3: console warn/error0、画面画像.test-output/phase7-local-video-reloaded.pngを保存・目視確認。通常JAのLocal upload retainedへ戻してnaturalWidth1を確認、tab16 handoff保持。新規動画は再検査できるようテストライブラリへ保持。仕様Phase7のImage/Video Upload・Compression・Library・Sync・Rules・Weatherを再確認。PHP/DB/API/Migration変更なしのため既存両DB各40 Installer/114構文/39基盤・全JS27の証拠維持、今回再実行なし。git diff --checkを記録保存後に確認。

Phase7最終ゲート監査中、Phase8〜12未着手、Version1.0未完成。実OAuth/認証済みUI/実OS位置許可/非表示動画復帰/巨大FFmpeg変換/実ブラウザquota枯渇は留保。先頭Phase一覧を最新状態に合わせ、過去記録と現状の混同を解消。

次に実行すること: docs/phase7-gate.mdと添付Phase7完了条件を照合し、未処理の実装がないことを確認して機能ゲート判定。留保項目をPhase12の個別監査へ追跡。Phase8はspec§48〜52のRegistry/Search/Ranking/Commands/Confirmationsを仕様通り実装する。Phase7の確定前にPhase8へ進まない。

## Phase 7 添付仕様のライブラリ補修（2026-10-03）

添付Phase7全文（Library multiple backgrounds/thumbnail/favorite/sort、完了条件9項目）を再照合し、既存ライブラリのサムネイル・お気に入り・並び替え不足を確認。Phase7完了判定を保留して補修した。背景サムネイルは色/gradient/image/video、動画は自動再生せずmuted metadata。ローカルBlobは製品IndexedDBから取得、render世代が変わった遅延応答を捨て、生成URLは再描画/pagehideで解放。bfcache復帰は再描画。失敗時は背景色を保持。背景favorite booleanを既存settings_jsonに保存し同期投影へ追加、SQL/Migration追加なし。並び順は保存順/名前/お気に入り優先、設定保存/Undo/カテゴリreset対象へ接続。JA/EN翻訳。

検証1: 全JS28単体成功。新library8で並び替え/入力不変/不正media除外/boolean厳密正規化/同期payload保持を確認。変更JS構文、settings history19も成功。BackgroundInput PHP構文と翻訳key一致を含む基盤39を背景両DBと新規Installer両DBで成功。

検証2: 隔離8083/8084の背景実API各56成功（新favorite文字列422を含む）。製品BackgroundSyncSession/SyncSession実HTTP2端末各8グループ成功、新favorite trueを他端末で受信→falseへ解除→元端末で受信を確認。従来receipt/同時upload/CSRF/owner/Range/409/オフライン/保存ルール共有を維持。モデル端末＋実APIであり実OAuth/認証済みブラウザ共有とは区別。fixture認証情報と専用アカウントはfinally清掃。

検証3: 製品8083の実JA UIでLocal video upload verificationのお気に入り登録→お気に入り優先で先頭、動画サムネイルreadyState4/paused true、既存画像naturalWidth1を確認。reload後favorite trueとsort favoriteを維持、名前順で順序変更、ENでもFavorite background/Favorites first操作、warn/error0。390pxでdocument375/scroll375、カード各160px、横はみ出しなし。動画未読込時は色fallbackからデコード後表示へ移る。画像.test-output/phase7-library-en.pngとphase7-library-mobile.pngを保存・目視確認。通常JA/Local upload retainedへ保持、viewport reset/tab16 handoff。今回ゲスト、実OS/OAuth未確認。

app/public/langを8082/8083/8084/8089/8090へ反映。DB/config/storage/既存背景を保持。9Migration/Installer各40の直前証拠を維持、今回新Migrationなし/再Installerなし。git diff --check成功。新PHP設定は既存JSONで扱う。Phase7機能ゲートはまだ監査中、Phase8〜12未着手、Version1.0未完成。

次に実行すること: 添付Phase7完了9項目とライブラリ補修をdocs/phase7-gate.mdで最終照合し、機能ゲートを確定する。未確認の実OAuth/認証済みUI/実OS位置許可/非表示動画復帰/巨大FFmpeg変換/実ブラウザquota枯渇をPhase12監査へ個別追跡。残る実装不足がなければPhase8 spec§48〜52と添付Phase8のRegistry/Search/Ranking/Commands/Confirmationsへ進む。

## Phase7機能ゲート確定・Phase8開始（2026-10-03）

添付Phase7の完了条件9項目とspec§57〜67をコード/検証結果に照合。image/video upload、実圧縮、library（thumb/favorite/sort含む）、per-background sync、全11rules、weather、priority、fallbackが機能確認済み。docs/phase7-gate.mdへ個別根拠と未確認を記録し、Phase7機能ゲート検証済みとしてPhase8へ進む。最低3検証は直近補修で全JS28/PHP基盤、両DB API56・2端末8、実JA/EN/mobileを確認。今回監査のみで既存検証の不要な再実行は行わず、spec.mdは未変更/未stage。Phaseごとの確定コミットを作成する。

実OAuth/認証済み実UIはユーザー指定の進行前提による留保。実OS位置許可/実非表示動画復帰/巨大FFmpeg変換/実ブラウザquota枯渇も未確認のままPhase12へ追跡。Admin codec不足警告はPhase9接続。Version1.0未完成。

次に実行すること: Phase8 spec§48〜52/添付全文に従い拡張可能なcommand registryを実装。8カテゴリ横断検索、初期recent/frequent/favorites/search/AI、exact/prefix/relevance/usage/recency/categoryランキング、列挙された実操作と操作別confirm/次回確認なし、Ctrl+Kとキーボード/JA/EN/mobileを順に検証。Phase8未完了、9〜12未着手。

## Phase8 Registry・Ranking・確認実行基盤（2026-10-03）

command-registry.jsを実装。Commands/Favorites/Search/AI/Settings/History/Tags/Foldersの8カテゴリ、実callback登録/解除・ID/effect/key検査・重複拒否、NFKC/大小文字/空白正規化、title/keywordsのexact/prefix/部分一致/部分列検索。ランキングは一致強度→usage/recency/category weight（Commands/Favorites/Search-AI/Settings/Tags-Folders/History）、安定tie順序。初期recent/frequent/favorites/search/AI groups、usage500件上限を導出。検索でcallbackを実行しない。

command-executor.jsを実装。state操作は既定confirm、操作ごとのfalseだけ確認省略。Cancelは副作用なし、次回確認なしの保存が失敗したら操作を中止。await確認中に登録が削除された場合も拒否、非同期run中の二重実行拒否、例外後にbusy解除。confirm/preferences/atomic rememberはUI adapter経由。サーバー権限/CSRFを緩和しない。

検証1: registry専用単体成功。8カテゴリ、exactが高usage prefixより優先、prefix/keyword/部分列、usageとrecency、未来時刻/不正usage、初期groups、結果上限/順序、重複/不正登録/危険ID/入力変更耐性、解除後再登録の古い解除callback無害、確認設定、usage上限を確認。
検証2: executor専用単体成功。取消/実行/操作別skip、保存失敗で副作用0、設定未保存時は次も確認、並行実行拒否、確認中解除拒否、callback失敗後に再実行可能を確認。
検証3: 全JS30単体・新規4JS構文・git diff --check成功。PHP/DB/API/Migration/製品既存UIに変更なし、直前PHP構文/両DB基盤39/API56/Installer9往復40/実UI証拠維持、今回は再実行なし。画面へ未接続のため新UI/Console成功とは扱わない。

docs/phase8-gate.mdに7完了条件の実装・未実装を記録。新規coreは実装済みだがsearch.jsにimportされず、Palette画面/データ登録/列挙操作/確認設定の永続化は未実装。Phase8途中保存、Phase9〜12未着手、Version1.0未完成。Phase7機能ゲート確定コミット7bb4832。実OAuth等の未確認留保は継続。

次に実行すること: command-palette.jsでCtrl+K/modal/キーボード/8カテゴリ検索結果/5初期groupsを製品へ接続。既存Favorites/Providers/History/Settingsの公開関数・イベントを確認して列挙実操作を登録する。command executorへ実確認dialogとsaveSettingsによる操作別confirm設定を接続し、成功後のusage永続化、登録解除/データ変更に追従。JA/EN/mobile実UIと両DB回帰を検証してからPhase8完了判定。

## Phase8 製品画面・操作の接続（2026-10-03）

command-palette.js/palette-product-commands.jsを追加しsearch.jsで起動。Ctrl+Kとボタン、modal検索/listbox/矢印/Home/End/Enter/Escape、初期5groups、検索empty、実行失敗statusを接続。成功後commandUsageをIndexedDBへ原子的保存（500件）、保存できなくても既存storage警告経路で操作継続。新機能はexport paletteCommands.registerで追加できる。標準runが返す移動callbackはusage保存・Paletteを閉じた後に実行する。

既存Favoriteを開く/追加/削除、フォルダ/タグ絞り込み、履歴を開く/再検索/全消去、カテゴリ設定を開く、6テーマ/Customテーマ・背景選択/Random Background、検索先/AI変更、Login/account移動・JSON logout APIを登録。settingsModal.openとfocusFavoritesを追加し既存処理を再利用。データ変更時に登録を更新、タグIDを文字のcode point列から安定導出（順序変化でusageを別タグへ誤付与しない）。ID上限512に拡張。title/keywordsはtextContentのみ。

state確認dialogは実行/取消/次回から確認しない。操作key単位のboolean設定をatomic setManyで保存。ショートカットカテゴリに7種の確認設定を追加しカテゴリresetへ接続。確認省略で権限/CSRFを省略しない。コード監査でLogoutの接続先がHTML /auth/logoutだったため、既存JSON /api/auth/logoutへ修正（実OAuth検証は行わず）。JA/EN key追加時、既存palette_backgroundと競合する命名を変更し外観のラベルを保持。

検証1: 全JS30単体成功、新規Palette/登録/検索接続の構文成功。タグID安定化後にregistry/executor専用単体も再成功、git diff --check成功。今回は製品data adapterの専用単体は未追加、実UI証拠と区別。
検証2: 隔離8083/8084で翻訳key一致含むPHP基盤各39、JA/EN構文、既存認証HTTP/API回帰各42成功。JSON logout/CSRF/長期cookie/owner/current-device/失効を確認。DB/API/Migration追加なし、9Migration Installer往復40の直前証拠維持、今回はInstaller再実行なし。実OAuth成功とは扱わない。
検証3: 実8083ゲストUIでCtrl+K起動→テーマquery→Enter→確認Cancel→再Enter/次回確認なし/実行でDarkへ変更→初期recent/frequentへ反映→Lightを確認なしで変更→Open settingsで実設定dialog、設定履歴にDark/Lightを確認。ショートカット確認設定でテーマ確認を再ON、検索先確認が別途ON維持。Bing Enterの確認を取消、empty queryの該当なし、背景検索を390px document375/scroll375で確認。EN切替（reload）後もrecent/frequent維持、Darkで確認再表示/取消を確認。warn/error0。画像.test-output/phase8-palette-mobile.png/phase8-palette-en.pngを保存・目視確認。最後にJAへ戻しtab16 handoff、viewport reset。JA再起動のWizardが開く場合は次回あとで続けるで閉じる。

今回のUI検証はテーマ/設定/検索先Cancelが中心。お気に入り/tag/folder/history/provider実変更/background/Random/Delete/ログインlogoutの実Palette操作は未確認。確認省略の設定共有、storage失敗時の製品UI、ARIA詳細、拡張登録の実画面も後続検証。Phase8未完了、Phase9〜12未着手、Version1.0未完成。app config/既存DBvolume/背景ファイルを保持、public/langは8083/4に反映。

次に実行すること: 専用検証Favorite・Folder・Tag・HistoryをUIで作り8カテゴリの横断操作と実actionを確認。検索先/AI変更、背景/ランダムと確認、確認設定reset/保存失敗/再読込を検証。Palette product adapter/logoutのテスト可能な境界を分離し実HTTP成功/失敗に接続、許可済み隔離認証でLogoutとclearSyncedOnLogoutの回復も確認。未確認を成功にせずPhase8ゲート判定まで実装・修正を続ける。

## Phase8 ログアウト中断からの回復（2026-10-03）

Palette logoutをcore/製品adapterへ分離。認証済みownerをGET /api/userで取得し、userId/clear booleanだけのpending intentをIndexedDBへ保存してからCSRF/JSON logoutへ進む。保存失敗ならサーバーログアウトを実行しない。成功後の同期データ削除は既存owner限定syncedDataRemovalとBlob削除/pending解除を同一setMany transactionで行う。応答喪失や削除保存失敗はintentを保持し、次のトップ/Account表示でGET /api/userが401または別ownerと確認できた場合だけ後続回復。元ownerで認証中/503等では削除しない。他accountの回復後にLogoutを指示した場合は現在accountも実Logoutする。最新intentが別操作へ変わった場合はadapterで照合し削除しない。

Account画面は回復失敗を既存翻訳のalertで表示。サーバーlogoutは任意user_id hintを認証済みownerと照合し、不一致403/Token維持。PaletteはJSON bodyでownerを指定する。従来hintなしLogoutやCSRFを維持。DB/Migrationなし、秘密はintent/Gitへ保存しない。

検証1: palette-logout単体成功。clear ON/OFF、事前保存失敗ではPOST0、実行後保存失敗のretry、応答喪失後の二重POSTなし、同owner認証中の非削除、503保留、別owner回復と現在accountのLogout、不正owner拒否を確認。IOモデルであり実IndexedDB quota故障・実OAuth成功とは区別。
検証2: MySQL8083/MariaDB8084の認証実HTTP/API各43成功。新owner hint不一致403とremember device非失効、既存JSON Logout/CSRF/失効/他owner/current-deviceを確認。変更Controller/View構文成功。coreワークフロー全体を実HTTPへ接続する専用試験はまだ未実施。
検証3: 全JS31単体成功、logout adapter JS構文/git diff --check成功。新規UI回復操作は今回未実施、既存Phase8の日英/mobile/console証拠を維持。全9Migrationの既存Installer往復40は今回再実行なし。app/public/testsを8083/4へ反映、既存config/DBvolume/背景保持。

ユーザーへPhase別内容と進捗を表で報告。Phase8未完了、Phase9〜12未着手、Version1.0未完成。実OAuth等の環境依存未確認は継続。

次に実行すること: 専用検証Favorite/Folder/Tag/HistoryのUI作成から8カテゴリの実横断操作を確認。provider/AI/background/random/delete/history action、確認設定reset/共有を検証。palette logout coreを隔離認証fixtureの実HTTPとowner限定local cleanupへ接続し、回復条件・clear OFF・storage failureを確認。全DoDと未確認を縮小せずPhase8ゲート判定へ進む。

## Phase8 実HTTPログアウト回復・横断操作（2026-10-03）

前のGoalターンは状態報告のみでno progress。現在のコード/稼働コンテナを再確認し、次の検証を実行して証拠を追加した。

検証1: tests/palette-logout-http.mjsをMySQL8083/MariaDB8084の専用認証fixtureで実行、各6グループ成功。事前保存失敗でPOST0/認証保持、owner不一致403、元owner認証中は削除しない、実Logout後の削除失敗回復、clear OFF、実応答喪失後の二重POSTなし、旧owner清掃と現在account明示Logoutを確認。サーバー通信は実HTTP、ローカル保存と故障はモデルであり実IndexedDB容量枯渇・実OAuthを証明しない。fixtureの秘密一時JSONと専用accountはfinally清掃。

検証2: 8083実JAゲストUIで既存の専用Palette verification folder/tag/favoriteを横断検索し3カテゴリ表示。Enterでfolder選択、tag絞り込み、favoriteでlocalhost同一画面へ実移動と保存データ保持。名前のimg/onerror文字列は文字として表示、alertなし。削除confirmのCancelでfavorite保持。Bingを確認後実変更、reloadでBing保持、Paletteで確認後Googleへ戻す。console warn/error0。画像.test-output/phase8-cross-search.pngを保存・目視確認。旧tab16はinventoryに存在せず、同じbrowser2から新tab18を開いた。JA/Light/既存背景保持、横断検索を開いたtab18 handoff。

検証3: 新HTTP試験JS構文/git diff --check成功。製品コード変更なし、既存全JS31/両DB認証43/Installer9Migration往復40の直前証拠を維持（今回再実行なし）。docs/phase8-gate.md先頭の古い未接続表を現在の証拠へ更新。

Phase8未完了、Phase9〜12未着手、Version1.0未完成。実OAuth/認証済み実UI等の留保は継続。

次に実行すること: tab18（存在を再確認）からPalette AI変更、背景選択/Random、履歴の再検索/消去、確認設定reset/共有、拡張登録とARIAを検証。永久削除をUIから無確認で実行しない。保存故障の製品adapter/実IndexedDB回復を検証し、未確認を成功扱いにせずPhase8機能ゲートを判定する。

## Phase8 AI・背景操作と履歴保存の補修（2026-10-03）

前のGoalターンは実HTTP/実UIの証拠追加とa830007保存によりprogress。progress/status/gitを確認してPhase8を継続。

検証1: 実8083 JAゲストUI/tab18でPalette Claude→confirm→実行しAI mode/Claude selected、reload後AIへ切替してClaude保持。森背景→confirm実行でlinear-gradient(90deg,17/37/29,85/120/98)実描画。RandomのCancelで森style不変、再実行で夜gradientへ変更。背景はLocal upload retainedへ確認後に戻す。履歴を開くコマンドで実History dialog/空欄表示。既存履歴が空のため履歴項目再検索/実消去は未確認。console warn/error0、背景コマンド画像.test-output/phase8-background-commands.png保存・目視確認。JA/Web/Google/Light/Local upload retained、AI既定Claude、tab18で背景queryを開きhandoff。

監査でPalette履歴消去がclearHistoryの先行メモリ更新→flushであり、保存失敗でも画面データを先に消す経路を発見。palette-product-commands.jsをsetMany({history:[]})へ変更し、原子的保存成功後にメモリ/通知を更新する既存store処理へ接続。失敗はexecutorへ伝播、成功usageを記録しない。通常履歴UIの既存clear処理は今回変更なし。

検証2: 変更JS構文、全JS単体31ファイル成功。最初の単体一覧に引数必須sync-http.test.mjsを含めて実行しpath undefinedで失敗、HTTP専用を除外して未実行のsync-session/weatherを実行成功。失敗を製品不具合/成功として扱わない。既存store/IndexedDB原子保存失敗の回帰は合格。今回Palette実UIのquota故障は未検証。

検証3: 変更JSを8083/8084へ反映、両DB基盤各39成功、git diff --check成功。DB/Migration/PHP/API変更なし、9Migration Installer往復40の直前証拠は維持（再実行なし）。既存config/DBvolume/背景/専用検証favorite保持。

Phase8未完了、Phase9〜12未着手、Version1.0未完成。実OAuth/認証済み実UI等の留保継続。

次に実行すること: 確認設定reset/同期共有、拡張登録とARIAを検証。隔離した専用履歴をUI検索で生成してPalette再検索を確認（外部への個人データ送信なし）。履歴消去・favorite削除は実データの永久削除をUIから無確認で行わず、専用adapter試験で原子保存失敗/成功を確認。製品adapter/実IndexedDB回復と残るPhase8ゲートを監査する。

## Phase8 確認設定の同期・初期化とEsc補修（2026-10-03）

前のGoalターンは実UI検証と履歴原子保存修正42f035fによりprogress。再開記録/status/gitを確認しtab18の生存をinventoryで確認。

検証1: JA実UIでPalette→ショートカット設定、AI変更確認OFF→カテゴリ初期化のconfirm→7項目すべてON。reload後も7項目ON。Ctrl+K実起動、Endで3件目/aria-activedescendant参照一致、Home→ArrowUpで末尾循環、該当なしでactive参照削除/status表示を確認。Escは検索文字ありで初回に文字のみ消去されるnative search入力動作を発見。command-palette input keydownでEscapeをpreventDefaultしてshutし、reload後の文字ありEsc1回でdialog open false/起動buttonへfocus復帰を確認。console warn/error0。画像.test-output/phase8-confirmations-reset.png保存・目視確認。tab18/設定ショートカット/JA/Web/Google/Light/既存背景/AI Claude保持、handoff。

検証2: 新tests/palette-preferences-http.mjsを隔離MySQL8083/MariaDB8084で各4グループ成功。製品SyncSession/sync-data/sync-api/confirmation判定を使用し、2モデル端末＋実APIで操作別false伝播、true再有効化/独立AI設定、削除reset伝播/既定confirm復帰、別account分離を確認。実認証済みブラウザ・実OAuthの証明とは区別。fixture秘密JSON/専用accountはfinally清掃。

検証3: registry/executor/settings-history19/sync-data28回帰成功、変更JS/新HTTP試験構文/git diff --check成功。JSを8083/8084へ反映、DB/PHP/API/Migration変更なし、全9Installer往復40/全JS31の前回証拠を維持（今回全体再実行なし）。

Phase8未完了、Phase9〜12未着手、Version1.0未完成。未確認: Palette履歴再検索、永久削除・履歴消去成功/失敗の製品adapter、実拡張登録、製品IndexedDB回復、実読み上げ/全browser。実OAuth等留保継続。

次に実行すること: 既存UIを使って隔離localhost専用検索先と履歴を作りPalette再検索を確認。新機能の登録/解除を専用プレビューで実画面検証。履歴消去/favorite削除と保存故障を製品adapter試験で確認し、実データをUIから無確認で永久削除しない。残るPhase8完了条件7項目を監査してゲート判定する。

## Phase8 拡張登録・失敗表示と履歴再検索（2026-10-03）

前のGoalターンは確認同期4×両DB/実UI/Esc補修cebf14cによりprogress。progress/status/gitと現ソースを確認して再開。

tests/palette-extension-preview.php/mjsを追加。SEARCH_TEST_MODE=1のみ、テスト環境public/_testへ手動配置する方式で本番routesに未登録。既存製品画面・paletteCommands登録口・IndexedDBを利用し、生成コマンドの登録/解除/成功/例外、localhost専用provider準備をUI操作で検証。通常8083と別origin8089、秘密/本番DB/実OAuthなし。8089 app/public/langへ最新ソース反映、config/storage/volume保持。

検証1: 実8089 JAゲストtab19で登録→Palette検索、文字列img/onerrorは文字として表示、取消では成功/usage0、再実行で成功1/保存usage1。例外注入後に実行すると失敗status/入力focus、成功1/usage1維持、Esc後解除→検索0件。console warn/error0、画像.test-output/phase8-extension-failure.png保存・目視確認。試験callbackの故障であり容量故障は未確認。

検証2: テストUIからlocalhost providerを追加（既存provider保持、saveHistory ON/externalSuggest OFF）。検索先を実UIで選択し生成文字列Palette history verification 20261003を検索、専用preview?q=...へ実遷移。PaletteのHistoryカテゴリで検索→Enter（確認なし）→同localhostへ再遷移、実履歴dialogに同query2件を確認。画像.test-output/phase8-history-research.png保存・目視確認、console warn/error0。個人データ/外部検索サービス送信なし、生成履歴は保持、実消去未実施。

検証3: 新preview PHP構文/JS構文、registry/executor回帰、両新規DB基盤39、git diff --check成功。製品コード/DB/API/Migration変更なし、9Migration Installer40/全JS31の既存証拠維持（今回再実行なし）。通常8083/tab18設定ショートカットは保持、tab19は履歴dialog、両tab handoff。

Phase8未完了、9〜12未着手、Version1.0未完成。拡張登録/履歴再検索の未確認は解消。残る主要検証は履歴消去・favorite削除の製品adapter成功/失敗、Paletteの保存失敗、ログアウト回復adapterの実IndexedDB。実OAuth/認証済みUI・全browser/実読み上げは留保。

次に実行すること: 製品登録をNode/専用IndexedDBで検証できる境界へ分離し、実callbackから履歴/favoriteの原子削除成功・保存失敗の保持を確認。通常ユーザーデータをUIから永久削除せず、隔離専用データで検証。Phase8完了7項目と添付仕様を再照合し、残る実装不足を修正して機能ゲート判定。

## Phase8 製品保存adapterと別タブ清掃競合（2026-10-03）

前のGoalターンは拡張登録/履歴再検索の実UI証拠7f22420によりprogress。progress/status/gitと現ソースを確認して再開。

palette-storage.js/createPaletteStorageを追加し、Palette履歴消去・favorite削除・Logout intent保存/清掃を共通の製品store callbackへ分離。product commandsとpalette-logoutが同じfactoryを使用し、認証通信・UI・既存CSRFは変更なし。完了清掃は現在intentの一致だけでなくIndexedDB transaction内でもpendingの条件照合を行い、別タブが通知前に新intentを保存した場合はstorage_conflictで書込・Blob削除を拒否し最新stateへ再読込。

検証1: 既存store-indexeddb.test.mjsへ製品factory経由の検証を追加。履歴/favorite削除のtransaction abortでメモリ/保存値保持→retry成功、Logout事前intent失敗/清掃失敗で所有データ・Blob・intent一括rollback、成功後の再読込保持、古いintent無害/clear OFF保持、別タブがDB上pendingを置換して通知前に旧清掃する競合の拒否とfile保持を確認。IndexedDBは既存模擬driverであり実ブラウザ容量枯渇ではない。最初のreload null照合はget fallback省略によるundefinedで試験失敗、製品pendingと同じfallback nullへ修正して再成功。

検証2: 全JS31単体成功、変更3JS構文/git diff --check成功。別タブ条件照合追加後に該当IndexedDB単体を再実行成功。DB/PHP/API/Migration変更なし、全9Installer往復40の既存証拠維持（今回再実行なし）。

検証3: 変更JSを8083/8084へ反映、両DB基盤39成功。実JAゲストtab18 reload/Wizard閉じ/Palette横断queryで3カテゴリと既存favorite保持、console warn/error0。今回Paletteでの永久削除・ログインは未実施。tab18と19 handoff、通常背景/config/DBvolume保持。8089のtestpreviewへ最新factoryはまだ反映していない。

Phase8未完了、Phase9〜12未着手、Version1.0未完成。製品adapter経由の削除/故障/別タブ競合の単体証拠を追加、実IndexedDB故障UIの確認は残る。実OAuth/認証済みUI・全browser/実読み上げ等の留保継続。

次に実行すること: 新factoryと専用DBを使うブラウザstorage previewで生成データだけの原子保存失敗と回復を確認（通常ユーザーデータ不使用）。最新コードを8089へ反映。Phase8完了7項目と添付仕様を監査し、残る実装不足を修正して機能ゲートを判定、Phase9 Adminへ進む。

## Phase8 実IndexedDB保存故障検証・機能ゲート確定（2026-10-03）

前のGoalターンは保存adapter共通化・別タブ競合防御02e90c5によりprogress。progress/status/gitを確認して再開。

tests/palette-storage-preview.php/mjsを追加。SEARCH_TEST_MODE=1で隔離8089/public/_testのみ配置、本番routes未登録。専用namespace palette-storage-verificationを使用し通常ページのstore・認証・外部通信不使用。製品createPaletteStorageとopenStateDatabaseを使い、試験store facadeから実DBへ原子書込み。故障は保存できない関数を含む値でDataCloneError/transaction abortを発生させる方式、実容量枯渇を証明しない。

検証1: 実ブラウザtab20で生成データの履歴/favorite削除失敗保持→retry、intent事前保存失敗、清掃失敗時intent/metadata/Blob保持、DB上pending置換後の古い清掃storage_conflict、clear OFF、所有fileだけ除去/端末専用保持を一括確認。成功表示、history0/favorite1/cloudfile0/localfile5bytes/pendingなし、reload後同値保持、console warn/error0。画像.test-output/phase8-storage-recovery.png保存・目視確認。これは専用実DB＋製品factoryであり通常store facade全体/実OAuth成功の代替ではない。Node製品store統合の模擬DB試験と組み合わせて証拠を区別する。

検証2: 新preview PHP/JS構文成功、既存store-indexeddb単体の製品adapter/conditional commitを再成功、git diff --check成功。製品コード変更なし、前回全JS31/両DB基盤39/認証43/実HTTPLogout6/確認同期4の証拠維持、全9Installer往復40は再実行なし。最新3JSを8089へ反映、config/DB/storage保持。

検証3: 通常8083/tab18 Palette Add Favorite→実editor表示/Cancel、Discord login→確認なしで/accountへ遷移、未設定表示/console0確認。実Discord往復未確認はユーザー指示で留保。header linkでトップへ戻す操作は後続Playwright/Emulation timeout、同handle再観測もtimeout。inventoryではtab18存在/account、tab19/20も存在を確認。トップ復帰を成功扱いにせず、3tabをhandoff。次回同browser/handleを再確認し、観測失敗だけを根拠に環境を再作成しない。

添付Phase8全文とspec§48〜52をregistry/search/ranking/cross search/actions/confirmations/extensible architectureの7項目で照合、docs/phase8-gate.md先頭へ最新証拠を反映。機能ゲート検証済みとしてPhase9へ進む。環境依存の実OAuth/認証済みUI、実quota枯渇、全browser/実読み上げをPhase12監査へ追跡。前述専用検証と未確認を区別しVersion1.0未完成。Phaseごとの確定コミットを作成。

次に実行すること: Phase9添付全文とspec§90〜100/118を読み、既存administrators/Installer予約/認証middleware/Logs/configを監査する。Admin権限はserver side、Dashboard/Users/Storage/Presets/Feature Flags/Maintenance/Limits/Logs/Audit/Statistics/Update管理導線を順に実装。Phase9の初期管理者設定を本番操作せず、隔離DBで権限/CSRF/Validation/一般ユーザー拒否を検証。codec警告はPhase7から接続、Updater実動作はPhase10へ。Phase9〜12未着手、Version1.0は未完成。

## Phase9 管理者権限とダッシュボード（2026-10-03）

前のGoalターンはユーザーへの表形式の状態報告のみでno progress。progress/status/gitを確認し、Phase9の実装へ再開。

実装: 010_admin_flag Migration（既存管理者はdefault 1、DDL再実行可能）。AdminRepositoryがprepared statementで現在のmembership/admin_flagを確認し、AdminMiddlewareが各requestで永続ログインを復元してDB権限を照合。セッションやclientのadminフラグは権限根拠にしない。GET /admin、GET /api/admin/dashboardを追加。実DBのユーザー数/管理者数/背景数/保存容量、実圧縮capabilityと不足警告を日英表示。ControllerにSQLなし、ViewにDBなし。設定値・秘密・本番データ不使用。

検証1: 隔離MySQL8083/MariaDB8084へapp/lang/database/testsのみ反映（config/storage/volume保持）、Migration各1適用→再実行0。tests/admin.php各16成功。guest401/regular403/admin200、権限flag取り消し・復帰・membership削除の即時反映、client偽装拒否、実counts/codec flags、秘密非露出を実HTTPで確認。専用fixtureはfinally削除。

検証2: 両DB基盤39、認証43の回帰成功。Installer予約の初期管理者昇格、長期login/端末/logout/CSRFを維持。

検証3: 両DB PHP構文各122ファイル成功、git diff --check成功。実ブラウザ管理者UI/レスポンシブ/Consoleは今回未確認。全10Migrationの新規Installer/down/upはまだ未実行、旧9Migrationの証拠と区別。read-only routesのみで管理更新CSRFは今後実装時に検証。

Phase9進行中、Version1.0未完成。残りUsers/Storage/Presets/Flags/Maintenance/Limits/Logs/Audit/Statistics/Update導線、匿名統計イベント/Privacy/90日log保持。Updater実動作はPhase10。実Discord OAuthは留保。

次に実行すること: spec§90〜100/118と添付Phase9の完了条件を照合し、管理者Users/Storage一覧と検索/pagination、管理変更のCSRF/validation/auditを実装。続いてメンテナンス/feature flags/limitsと一般user制御、統計/ログを接続。新規Installer全10Migration往復、日英/モバイル実UIとconsoleを検証する。既存ブラウザtab18は観測timeoutであり終了とは判断せず、必要時に同handle/inventoryを再確認。

## Phase9 ユーザー・背景容量一覧（2026-10-03）

前のGoalターンは管理者基盤2ff9e71と両DB検証によりprogress。progress/status/git/specと添付Phase9を確認して継続。

実装: GET /admin/users・/admin/storage、GET /api/admin/users・/api/admin/storage。全routeにAdminMiddleware。Discord ID/username/display nameでliteral検索、page/page_size（最大100件）検証、安定順序のpagination。ユーザー一覧は新しいID順、容量一覧は背景bytes順。背景はdeleted=0のみ集計しゼロ容量ユーザーも表示。ユーザー登録/最終login/役割/背景数/bytesを日英表示。Viewは値・検索欄・pagination URLをEscape、ControllerにSQLなし、prepared statementをRepositoryへ集約。read-onlyでデータ削除/権限変更なし。

検証1: 隔離MySQL8083/MariaDB8084の実HTTP tests/admin.php各64成功（最初60、日英/検索Escapeを追加し64再成功）。guest401/regular403/admin200、即時権限失効、2user検索/1件pagination/2ページ、削除済み背景除外/37bytes、%_ literal/SQL injection literal、user/search XSS Escape、UTF8/control/array/範囲不正422を確認。専用生成fixtureはfinally清掃、秘密は出力せず。

検証2: 両DB基盤39成功（日英key parity/CSRF/router/escape/log秘匿等）。認証43は直前変更後の証拠維持、今回は再実行なし。

検証3: 両DB PHP構文各123ファイル/git diff --check成功。Migration変更なし、10の再実行0をadmin試験で確認。全10新規Installer/down/up未実行、実管理UIのブラウザ/mobile/console未確認。今回はHTTP HTML日英の確認でありブラウザ検証とは区別。

Phase9進行中、Version1.0未完成。Users/Storageの一覧・検索は実装済み、管理変更/audit/limits/容量設定/cleanup等と他管理項目は未完了。Phase10〜12未着手、実OAuth留保継続。

次に実行すること: 管理操作の監査ログ・DB設定Migrationを追加し、Maintenance/Feature Flags/Limitsの編集をCSRF/権限/validation付きで実装する。Maintenanceは一般user full stop/admin通常利用という仕様を全routeと照合する。管理者追加/失効操作もauditと競合/最後の管理者保護を考慮して実装。Presets/匿名Statistics/file+DB Logs/90日保持/Privacy/Updater導線を残りPhase9ゲートへ接続し、全10以降Migration Installer往復と実日英/mobileUI検証を進める。

## Phase9 メンテナンス制御・監査基盤（2026-10-03）

前のGoalターンはUsers/Storage実装2adb9a4と両DB検証によりprogress。progress/status/git/spec§90〜100を確認して継続。

実装: 011_admin_settings_logs（site_settings/log_entries、FK・date/type/user indexes）。GET/POST /admin/maintenance と /api/admin/maintenance。AdminMiddleware→Csrf、bool/version検証、version不一致409。設定更新とadmin_auditを同じDB transactionで保存。監査はDB outbox/file_written経由でFileLoggerへ配送し、file失敗はDBに保持して次の管理訪問/変更で再試行。contextはsetting/before/after/version/actor/audit IDのみ、秘密/リクエスト本文なし。現在はmaintenanceの監査のみで全Logs要件の完成ではない。

全動的routeの前にMaintenanceを適用しguest/一般userに503、HTMLは日英のメンテナンス文とReloadボタンのみ（通常header非表示）、APIは日英error+Retry-After。DB membership/admin_flag=1の管理者は通常利用。入力JSON解析より前にgateを置き、正常/不正mutationも停止する。private storage/runtime/maintenance.jsonとflockを用い、enableはDB commit前にsignal true、disableはcommit後にfalse。設定変更とsnapshot再取得を共通lockで直列化。missing/corrupt signalはDBで確認するまでpublicを許可しない。通常false snapshotではguestローカル画面にDB接続を追加しない。実DB障害HTTPは今回未検証（DB不要signal単体は確認）。

検証1: 既存隔離8083/8084へcode/Migration反映、各1適用。最初の実HTTP maintenance各53成功。新規専用search-phase9-gate-20261003（MySQL8091/MariaDB8092、新volumes）をbuild/startし全11Migration up/down/up＋Web Installer各40成功。秘密は環境変数/専用configのみ、既存DB/storage/configを保持。

検証2: 新規両DBでwww-dataとしてmaintenance最終62成功、signal8、admin64、基盤39、認証43。一般全route/unknownroute/API停止、admin継続、CSRF/validation/staleversion、DB失敗rollback、file監査再試行、日英/フォーム303/private snapshot拒否を確認。専用fixtureはfinally清掃、生成監査はDB/fileに保持、actor削除でDB user_idはNULL。試験file sinkの故障は意図した注入、error_logのpending文は実障害とは区別。

失敗と補修: private signal拒否の試験が403固定で失敗。既存InstallerとApache DocumentRootにより実際は404、403/404かつ値非露出へ修正して再成功。mutation POST []の試験が入力400で失敗し、製品gateがRequest::capture後だったことを発見。gateを入力解析前へ移してPOST/PUT/DELETEすべて503を再成功。両失敗を成功扱いにしない。

検証3: 最終新規両DB PHP各132ファイル構文/git diff --check成功。最終修正前の既存8083/84 lintは135/131（個別テストfixture配置差）、最終bootstrap/testsを既存環境にも反映。tests/docker.ps1の通常回帰へadmin/maintenance/signalを追加。実管理者ブラウザUI/mobile/console・実OAuthはまだ未確認。11Migration Installer40の証拠はbootstrap最終入力順修正前、その後基盤/認証を再成功。

Phase9進行中、10〜12未着手、Version1.0未完成。Maintenanceと監査保存基盤を実装したが、Feature Flags/Limits/Presets/Statistics/全Logs/90日保持/Privacy/管理者操作/容量操作/Update導線は残る。

次に実行すること: 最新専用環境8091/8092を利用。admin_audit/log_entriesの閲覧・検索/type/date/user/errorcode/keyword filterと90日DB/file保持を実装し、ErrorHandler/OAuth/Sync/Securityなどへ安全な記録を接続。Feature Flags/Limitsとサーバー・UI制御、Presetsとanonymous event Statistics/Privacyを順に実装。設定ファイル/DB/secretを保持してUpdater導線をPhase10へ引き渡す。実管理日英/mobileUI/consoleとsnapshot障害時のHTTPを検証。Phase9機能ゲートを満たすまでPhase10へ進まない。

## Phase9 ログ閲覧・検索・90日保持（2026-10-03）

前のGoalターンはメンテナンス/監査4f84822と11Migration Installer/両DB検証によりprogress。progress/status/gitとspec§98/99を確認して継続。

実装: GET /admin/logs・/admin/audit-logs と /api/admin/logs・/api/admin/audit-logs。全routeに現在DB権限のAdminMiddleware。種類7種、UTC開始/終了日、内部user ID、error code exact、keyword literal、stable pagination（25既定/最大100）。Audit専用は常にadmin_auditのみ。日英UI、context/filter入力/URLはEscape、長いcontextは折返し。SQLはLogRepository、入力はLogFilters、ViewにDBなし。

LogRetention: DB90日cutoffとdaily fileの古い日を整理し、境界日fileは実at timestampで古い行だけ除去。UTC終了日は23:59:59まで含む。FileLoggerと共通LogFileLockにより整理/書込みを直列化、境界再保存はtemporary/rename。リンク・不正date filename・無関係fileを削除せず、writerはlinked lock/dailyfileを拒否。監査再配送は元のevent日時をFileLoggerへ渡し保持期限を延長しない。bin/cleanup-logs.phpを追加、管理ログ訪問でも整理/監査再試行。docs/admin-logs.mdに毎日のサーバー側実行を記載、OS定期実行は未設定。

検証1: 専用新規Installer済みMySQL8091/MariaDB8092へapp/lang/public/bin/testsだけ反映、config/storage/DBvolume保持。実HTTP tests/admin-logs.php最初各52、linked writer検証を追加して最終各54成功。guest401/一般403/admin200、7種類/date/user/error/keywordと組合せ、1件pagination2ページ、audit固定、SQL fragments/%_ literal、日英XSS Escape、不正UTF8/arrays/date/range拒否を確認。90日境界DB/fileと再実行、links/無関係file保持、writer linked path拒否も確認。専用fixture/log rows/temp fileはfinally清掃。

検証2: 両DB基盤39/admin64/maintenance62/auth43の回帰成功。FileLogger lock追加に合わせ試験directory cleanupのみ調整、監査file失敗/retryも維持。最終linked guard後にlogs54/基盤39を再成功。他回帰の証拠はguard直前。

検証3: 両DB PHP各140構文成功、bin cleanup両DB成功（期限外0/ファイル0/行0）、git diff --check・tests/docker.ps1 Parser成功。通常Docker回帰へlogs試験追加。DB/Migration変更なし、全11 Installer40の直前証拠維持（今回は再実行なし）。最終2writer guardsはPHPを実試験で読込・実行済み。実ブラウザ管理UI/mobile/consoleは未確認。

Phase9進行中、Version1.0未完成。ログ閲覧・保持は実装済みだが、全エラー分類の実収集は未完了。現実に接続済みのauditはmaintenanceのみ、7type選択肢/生成fixtureを全収集の証拠にしない。PHP errorsは既存file経路のみでDB接続が次作業。匿名Statisticsの無期限データをLogRetentionへ含めない。

次に実行すること: ErrorHandlerを安全なdual file/DB loggerへ接続する。PHP/API/OAuth/Sync/Update/Securityを実経路で分類し、query/body/secret/例外messageを記録しない。DB停止時もfileへ残し、復帰時に重複なくDBへ戻せる耐久処理を設計・実装・検証。全Logs収集と管理監査を完了後、Feature Flags/Limits/Presets/anonymous Statistics/Privacy/管理者・容量操作/Update導線を接続する。全Phase9ゲート、実管理日英/mobileUI/console、残る環境依存を区別して監査する。

## Phase9 エラー収集・DB配送回復（2026-10-03）

前のGoalターンはログ閲覧/保持aa74a0aと両DB検証によりprogress。progress/status/gitを確認して次の収集経路を実装。

実装: 012_log_event_ids（nullable event_idとunique index、列/index単位で再実行可）。ApplicationLoggerがPHP/API/OAuth/Sync/Update/Securityを分類し、非公開storage/log-pendingの原子的queueへ先に保存、DBと日別fileへ配送。event IDによるDB unique/upsertで再試行を重複排除。DB未接続時はfile＋queue、file失敗時はDB＋queueを維持。再配送は元日時/actor/code/categoryを保持し、90日超を復活させない。不正queueは隔離し、隔離/孤立temporaryにも90日期限を適用。file記録の部分書込は検出して追記前の位置へ戻す。fileへの再配送は中断位置によって同event IDの重複行が起こり得るがDBは1件。

ErrorHandler（PHP warning/exception/fatal handler）、Response.send（例外を投げないAPI失敗）、Installerのcatchを接続。Response errorCode metadataを追加し、SYNC_CONFLICT/Maintenanceなど直接Responseも記録する。ユーザーデータを含む応答bodyのdecodeを行わず、大きな同期競合を余分に複製しない。同requestの二重収集を防止。記録は安全なcode/status/class/source basename/line/method/request ID/internal user IDのみ。例外message/stack/query/URL/body/cookies/token/OAuth codeを渡さない。SQLはLogRepositoryへ集約。管理ログ訪問/CLI cleanupでqueueを回収。

検証1: 専用MySQL8091/MariaDB8092へ012適用→再実行0。ApplicationLogger各28成功、最終orphan期限を加え新規8093/8094で29成功。分類6種、秘密除外、DB未接続/file失敗、再配送、DB dedupe、期限超/不正context隔離と清掃を確認。DB停止の単体はnull repository、file故障は注入であり実quota枯渇の証拠ではない。

検証2: 実HTTP両DB各25成功。404 API/OAuth State/Sync Validation/CSRF/JSON不正と直接SYNC_CONFLICTを1回ずつfile＋DBに確認、正常sync writeも維持。両DBPHP警告/例外＋専用開発DB接続不能設定のHTTP各16成功。PHP message/Warning/Stackを画面・fileへ出さず、実PDO接続失敗の503と耐久queue/復帰後DB回収を確認。通常false maintenance snapshotのguest UIは接続不能時も200。秘密の開発設定は元の内容へfinally復元、実DBは稼働を保持（DB process crash試験ではない）。テスト専用PHP previewはpublic/_testへ配置して検証後両環境から除去、本番route未登録。

失敗と補修: 最初のHTTP sync正常試験は空objectを連想arrayへdecodeして[]になり422、試験をobject保持へ修正。先行MySQL試験の中断によりMariaDBへ最新Responseの反映が未実行となり直接409ログが0件、実コンテナfileを確認し再反映して両25成功。DB接続不能試験はPHP workerが直前設定を使用し401を返して失敗。CLI実接続失敗とhealthの503/復帰200を同workerで観測するまで短くpollし、server再起動せず再成功。未反映/未観測を成功扱いにしない。

検証3: 最新専用search-phase9-logs-20261003を新volumesで作成（MySQL8093/MariaDB8094）。空DBの全12 up/down/up＋Web Installer各40成功。導入前queue各4件を導入後CLIで回収。新規後application28/http25/logs54/基盤39/PHP146成功、orphan expiry後application29を再成功。既存8091/92もapplication28/http25/logs54/基盤39/admin64/maintenance62/auth43/sync17/cloud API52/PHP146とCLI cleanupを成功。Docker test runner Parser/git diff --check成功、通常回帰へapplication/httpエラー試験を追加。旧config/storage/users/uploadsを保持。最終orphan patchは8093/94へ反映、8091/92はそれ以前のコード。

Phase9進行中、10〜12未着手、Version1.0未完成。PHP/API/OAuth/Sync/Securityの収集経路は接続・検証済み。Updater分類は単体のみで実Updater未実装、Phase10で実経路検証する。管理auditはmaintenanceだけ、残る管理操作と連動が必要。OS定期cleanup・実管理ブラウザUI/mobile/console・実OAuth等は未確認。

次に実行すること: 最新8093/8094を使いFeature Flags/LimitsのDB設定と監査付き更新API/日英UIを実装する。flagは表示だけでなくserver APIの権限/可否へ接続し、容量制限は既存BackgroundRepositoryのquota/同時uploadを守って動的設定へ接続。Presets/anonymous Statistics/Privacy/管理者・容量操作/Update導線と実UIを残るPhase9ゲートへ接続。最終Phase9条件を監査してからPhase10へ進む。管理UI試験にはtest-mode専用の通常token fixtureを用い、実Discord OAuth成功の証拠と混同しない。

## Phase9 機能制御・動的制限と同期停止（2026-10-03）

前のGoalターンは状態表の報告のみでno progress。progress/status/gitを確認し、既存未コミット実装から再開。

実装: 013_site_policyで5機能フラグと背景容量/ログイン回数/時間窓を保存。nullはconfigを継承。AdminPolicyController/View、SitePolicyRepository/PolicyState、GET/POST /admin/policy・/api/admin/policy、公開flagsのみ/api/site-policy。DB権限/CSRF/validation/version409、設定とSITE_POLICY_CHANGED監査の同一transaction、private snapshot排他・失効・再取得。FeatureFlagsは入力解析前にserver APIを停止し、既存私有file取得は認証・owner検証付きで継続。BackgroundRepositoryはpolicy共有lock→owner lockで動的quotaを確認し、上限引下げでも既存file・非増加編集を保持。背景総容量0は無制限、個別25MiB/500MiBは維持。spec97に合わせweatherの一律回数制限を外しloginだけ動的制限。

追加: site-policy.jsの厳密bool判定と最新flags確認を設定/背景同期の前へ接続。停止応答FEATURE_DISABLEDを明示的に伝播し、JA/EN理由表示。データ/所有者/背景intentを削除せず定期再試行。SyncSessionの403は元からログアウトへ接続していないことを監査。同期以外の各機能停止表示、accountページ専用表示、実UIは未完了。

検証1: 専用MySQL8093/MariaDB8094でpolicy最終各29成功（権限/CSRF/CAS/全flags/監査/容量/実OAuth route login制限）。動的quota同時2writer各6成功。013各1適用→再実行0は先行実装時に確認。最終更新後の両DB基盤39/PHP154構文成功。全13新規Installer往復は未実行、全12 Installer40の既存証拠と区別。

検証2: Node policy12、sync session37、background transport/session/intent/recoveryの回帰成功、変更5JS構文成功。停止→復帰/不正flags/503とAUTH_REQUIREDとの区別はmock transport試験、実ブラウザ停止UIの証明ではない。先行backend検証のadmin64/maintenance62/auth43/background-api56両DB成功は既存証拠維持、今回それら全体の再実行なし。

検証3: tests/docker.ps1にpolicyとdynamic quotaを追加。git diff --check成功。最初のcopy先を/var/www/htmlと誤り、実WORKDIR /var/www/appをDockerfileで確認して正しいpublic/lang/testsへ再反映、site-policy.jsとJA keyの配置を確認後両DBを再検証した。誤った配置の結果を最終反映の証明にしない。config/storage/DBvolume/通常ユーザーデータ保持。docs/admin-policy.mdを追加。実管理ブラウザ/mobile/Console、全browser、実Discord OAuth、OS定期cleanupは未確認。

Phase9進行中、10〜12未着手、Version1.0未完成。機能・制限のserver管理と同期停止処理は実装済み、全Phase9の完了を意味しない。

次に実行すること: docs/admin-policy.mdとspec90〜100/118に沿い、天気/候補/metadata/背景uploadの停止表示とaccount専用表示を接続し実日英管理UI/mobile/Consoleを検証。全13Migration Installer往復。Presets、匿名Statistics/Privacy、管理者追加/解除/最後の管理者保護、容量操作、Update導線を順に実装。Phase9機能ゲート確定前にPhase10へ進まない。既存browser2/tab18(account観測timeout)/19/20は未操作、存在を確認して再利用する。

## Phase9 各機能の停止表示と実ゲスト画面（2026-10-04）

前のGoalターンは機能制御49544e4と両DB検証によりprogress。progress/status/gitを確認して次の停止表示を接続。Phase9継続、10〜12未着手、Version1.0未完成。

実装: rejectDisabled共通判定、外部候補停止の専用status（端末候補を維持）、metadata停止のJA/EN理由（手入力可能、旧URL/旧request応答を無視）、WeatherContext停止理由と60秒retry/復帰時clear、背景設定内の天気status、accountに保存済みsite_disabled状態表示。既存設定/データ/所有者/intentは変更しない。site-policy-preview-state.phpはCLI/testmode専用で2flagsだけ一時停止・非公開元設定保存・復元する補助、本番routeなし。両DBのstop/restore完了、元policy復元・退避file除去。

検証1: Node site-policy判定/復帰/認証分離、WeatherContext既存＋停止/fallback/retry/復帰、account-data既存21＋intent清掃、SyncSession37成功。変更7JS構文/git diff --check成功。模擬通信のweather試験を実サービス成功の証拠にしない。

検証2: 最新app/public/lang/testsを専用8093/8094の/var/www/appへ反映。両DB基盤39/policy29/auth43、PHP154成功。追加preview補助を両DBへ反映し最終PHP155成功、MariaDB stop/restoreも成功。今回Migration変更なし、全13空DBInstaller往復はまだ未実行。通常config/storage/volume保持。

検証3: 実IAB browser2新tab21/8093でJA外部候補停止理由、JA/ENサイト情報停止理由、編集dialog/Cancelを確認。URLは生成example.test、server flagで外部取得前に拒否。favoriteの保存・削除は未実行。Console warn/error0。最初の入力は初回Wizardが遅れて開きtarget mismatch、同tab AXで確認してContinue later後に入力成功。環境を再作成せず解消。画像.test-output/phase9-metadata-disabled.pngと-en.png保存、JA画像を目視確認。テストInstallerのXSS titleは文字列として表示、実行なし。設定復元後tab21はENホーム/生成query保持、復元後の外部provider取得成功は未検証。既存tab18/19/20 inventoryで生存、利用可能な旧handlesと新tab21をhandoff。

未確認: 実管理者画面/mobile、認証済みaccount専用状態、天気停止実UI、外部候補EN停止、実OAuth/全browser/OScleanup。背景upload停止は既存同期の汎用クラウド停止表示であり実UI停止試験は残る。docs/admin-policy.mdを最新証拠へ更新。

次に実行すること: 全13Migrationの新規隔離Installer往復を検証し、testmode専用通常token fixtureで管理画面の日英/保存/モバイル/Consoleを確認（実OAuthの代替と混同しない）。残るPresets/匿名Statistics/Privacy/管理者追加解除/最後の管理者保護/容量操作/Update導線を実装。Phase9ゲート確定前にPhase10へ進まない。実ブラウザの天気停止/背景upload/復帰とaccount状態を追跡する。

## Phase9 全13Migration新規Installer・管理policy実UI（2026-10-04）

前のGoalターンは停止表示bf653e2/実ゲストUI/両DB検証によりprogress。progress/status/gitを読み次の新規Installerと管理UIへ進んだ。

検証1: 最新コードから新しい専用search-phase9-policy-20261004を独立DB/config/storage volumesでbuild/start（MySQL8095/MariaDB8096）。tests/integration.php各40成功、空DBの全13Migration up→idempotent→down→Web Installerによる再up、全Migration適用数/初期admin予約/Secret非露出/再導入拒否を確認。既存8093/94等は変更せず保持。

検証2: 新規両DBpolicy29/admin64/maintenance62/logs54/application29/HTTP errors25/auth43/基盤39/PHP155、動的背景quota同時writer6成功。maintenance/applicationのfile delivery pendingは注入したsink故障の試験出力、回復結果を含み成功。新規環境は画像/video codecなしでdashboard警告を実表示、codec検証済み環境の証拠と区別。新test補助2件を追加して両DB最終PHP157成功、両DBfixture prepare/cleanup成功。

追加: tests/admin-ui-fixture.phpとadmin-ui-preview.php。CLI/testmodeだけで専用user/admin/token/deviceと15分keyを非公開0600へ生成、手動public/_test previewはtestmode/localdev/期限/key確認のみ。通常製品Auth.restore/AdminMiddlewareを通り、製品routes・OAuthを迂回する恒久機能は追加しない。キーはGit除外一時fileでのみ受渡し、chat/進捗/Gitへ実値なし。docs/admin-ui-testing.mdを追加。

検証3: 実browser2/tab22最初127.0.0.1:8095でJA管理dashboard/policy、metadata無効・quota1024を保存→reload保持、EN表示、390px幅（document scrollWidth=390/innerWidth390）成功。ENから復元SaveするとAUTH_REQUIREDになり復元を成功扱いにしなかった。複数DBの同ホスト別portでCookie名/pathを共有しAuth.clearが無効tokenを削除するコードを確認、別ゲストtabの影響が原因と推定（通信を捕捉して原因確定した訳ではない）。同環境をlocalhost:8095へ分離して再ログインしJA enabled/空欄へ復元保存→reload保持を再成功。製品の認証を緩めず対処。

JA/EN mobile画像.test-output/phase9-admin-policy-mobile-ja.png/-en.pngを保存・両画像目視確認。localhost実監査画面に今回変更前後/actor/DBとfile保存済みを確認、390pxでscrollWidth375<=390、Console warn/error0。dashboard監査link clickは遷移を観測できずheading待ちtimeout、同tabの既知リンク先URLへ直接移動して表示成功。リンク操作成功とは区別して追跡。viewport reset済み。

清掃: 両DBfixture cleanupで元policy復元、専用user/token/device/非公開fixture除去。MySQL public/_test previewとhostキー一時fileを除去。tab22 reloadでPlease sign inを実確認、role/token失効は通常認証に反映。生成auditは保持。tab22 localhost auditログイン要求と既存tab18/19/20/21をhandoff。秘密/通常user/config/uploads不変更。

未確認: EN管理Save、他管理画面全体/リンク遷移、実天気停止/背景upload/認証済みaccount状態、実OAuth/全browser/OScleanup。Phase9進行中、10〜12未着手、Version1.0未完成。全13Installerと管理policy表示/JA保存の未確認を解消したが全Phase9完成ではない。

次に実行すること: spec92/94〜96/118と添付Phase9を再確認し、残るPresets、匿名Statistics/Privacy（直接Discord IDなし・無期限・期間/graph・必須イベント）、管理者追加/解除/最後の管理者保護、容量操作、Update導線を実装。管理UIはlocalhostで新fixture準備しEN Save/他管理page/リンクを継続検証、既存guest127環境とはCookie分離。Phase9ゲート確定前にPhase10へ進まない。

## Phase9 匿名統計の収集・Privacy（2026-10-04）

前のGoalターンは全13Installer/管理UI/c0238edによりprogress。progress/status/git/spec94〜96/117/118と添付Phase9全文を確認。ユーザー途中の状態質問へ回答し実装を継続。

実装: 014_statistics（statistics_events、anonymous_id/event_id uniqueとdate/actor/type/source indexes、ユーザーFK/直接識別子なし）。StatisticsInput/Repository/Controller、POST /api/statistics/event（guest可/CSRF必須/余分field拒否/100件最大/許可categoryのみ/全件validation→DB transaction/dedup）。DB無期限、90日LogRetention対象外。時刻は発生時Unix秒、未来5分まで。源web/extensionは分類入力、実Extensionは未実装。

JS: statistics-coreのsafe schemaと20件batch/再送/ACK保留、statistics.jsの製品store条件付queue/競合retry/60秒通信retry。匿名IDは端末ランダム、クラウド同期・account対応表なし。query/url/custom provider name/DiscordID/IPを送らず、未知providerはcustom分類。Web visit/search/AI/favorite open/settings open/background save/成功Palette/syncを接続。個人favoriteStats OFFでも匿名イベントを収集。統計保存に失敗しても検索/編集を止めない（その場合の計測成功は保証しない）。OFF設定なし。

Privacy: CoreController/ViewとGET /privacy、通常layout footer導線、JA/ENでRequired/Login Cookie、端末/クラウド、Discord account情報、匿名統計無期限とOFFなし、外部サービス、90日logsを説明。Maintenance footerは隠す。docs/statistics.mdを追加。

検証1: 専用MySQL8095/MariaDB8096で014適用、再実行0、実HTTP各30成功。guest/CSRF、5event分類、web/extension入力、secret fields/SQL provider/未知source/type/日時拒否、100件境界、全件検証後write、lostresponse dedup、1970offlineevent保存と90日log整理でstats保持、直接ID列なし、JA/EN privacy200を確認。専用anonymous fixture行はfinally削除。全14新規Installer/down/upはまだ未実行、前回全13の証拠と区別。

検証2: Node statistics queueの秘密除外/自動custom分類/offline/再読込/ACK保存失敗/20+5batch/同期文書にIDなし/Extension source/通信中追加保持成功。favorites回帰、sync-data28、CommandExecutor回帰成功。変更8JS構文成功。両DB基盤39/auth43/maintenance62/policy29/PHP163成功。tests/docker.ps1へ統計試験を追加、Parser/git diff --check成功。codec/config/storage/既存DBvolume保持。

検証3: 実browser2新tab23/localhost8095 home→settings開閉→footer Privacy EN実遷移→JA表示。DB実件数0→visit1→最終visit2/feature1、各distinct匿名端末数1を確認（ID値は出力しない）。最初のfooter clickは遅れて開いたWizardが阻止、同tab AXでContinue later後に実遷移成功。EN390px scrollWidth375<=390、JA/ENモバイル画像.test-output/phase9-privacy-mobile-ja.png/-en.png保存、JA画像目視確認。Console warn/error0、viewportreset。tab23 JA/privacyと既存18〜22をhandoff。実ブラウザのsearch/AI/favorite/背景/Palette/sync匿名イベント・故障再送・別タブ競合は未検証で、unit/APIを代替証明にしない。

Phase9進行中、10〜12未着手、Version1.0未完成。匿名収集基盤とPrivacyを追加したが、集計/期間/graph/全指標は未実装。

次に実行すること: spec94と添付Phase9の全指標を列挙し、StatisticsRepositoryの集計とGET /api/admin/statistics・/admin/statistics、期間/グラフ/DAU/WAU/MAU/NewUsers/Retention/各provider/source/featureを実装。実利用人数と匿名端末数の区別をUIで説明する。Presets/管理者追加解除/最後の管理者保護/容量操作/Update導線、全14Installer/統計実端末検証と管理EN Save/リンクの留保を継続。Phase9ゲート前にPhase10へ進まない。

## Phase9 統計管理・期間集計・グラフ（2026-10-04）

直前のGoalターンは状態報告のみでno progress。progress/status/gitを再確認し、最新再開手順から実装した。Phase9進行中、10〜12未着手、Version1.0未完成。

実装: StatisticsPeriod/StatisticsReportRepository/AdminStatisticsController、GET /admin/statistics・/api/admin/statistics、管理dashboard導線、JA/EN画面とSVGグラフ/数値表。UTC両端を含む期間、既定30日/最大366日。登録account/有効長期login/account数/非削除背景bytesは現在値、新規accountは選択期間。イベント検索/AI/favorite・provider/feature/source比率、終了日から1/7/30日間の匿名DAU/WAU/MAU、新規匿名端末、成熟した初利用cohortのD7再利用率。実accountと匿名IDを結合せず集計応答に識別子なし。Controller SQLなし、prepared Repository、read transaction。追加Migrationなし。

検証1: 専用MySQL8095/MariaDB8096で最終各48成功。日付不正/leap day/366日、inclusive境界、欠損日0、過去初利用/成熟cohort/50%再利用率、provider/feature/source ratio、accounts/login/storage/new_users、guest401/user403/admin200/権限剥奪、JA/EN HTML/graph/table、秘密非露出。生成イベント/ユーザーだけfinally清掃。初回MariaDBはSQL alias returningが予約語で失敗、returned_devicesへ変更して両DBを再成功。最終UTC日付iterator補修後も両48成功。

検証2: 両DB統計収集30/admin64/policy29/authHTTP12/基盤39/PHP168成功。Docker runnerへadmin-statistics追加、PowerShell Parser/git diff --check成功。追加Migrationなし、全14新規Installer往復は留保。通常config/storage/volume/ユーザーデータ保持。

検証3: 同browser2/tab23、localhost8095専用admin fixtureで通常Auth/AdminMiddlewareを使いログイン。JA dashboard統計linkの実遷移成功、JA期間変更/日別表開閉成功。最初mobile390pxはSVG固定600でscrollWidth677、外部core.cssの幅100%規則へ修正してJA/EN scrollWidth375<=390。JA/EN mobile画像.test-output/phase9-statistics-mobile-ja.png/-en.png保存・両目視確認、Console warn/error0、viewportreset。

開発preview初回500はCLI root作成0600fixtureをwww-dataが読めなかったため。秘密値を出さず所有者だけwww-dataへ合わせて正常化。エラー画面となったtab22はbrowser data URL policyで再navigation不可、正常な既存tab23を同browserで再利用し環境再作成なし。専用fixture/preview/keyは清掃済み。清掃後EN期間変更を試すとPlease sign inで拒否、これは失効の証拠でありEN期間操作成功には数えない。tab23はEN統計ログイン要求へhandoff。tab22エラー画面の復帰は未解消。

未確認: 大規模統計性能、全ブラウザ/Extension実収集、統計の実検索/AI/favorite/背景/Palette送信と故障再送/実複数タブ競合、実DiscordOAuth。匿名収集からの実feature同期イベントはENホーム遷移後UIで2件表示、生成fixtureaccountの通常同期であり実OAuthの証拠ではない。

次に実行すること: spec92/添付Phase9のPresetsを既存provider/default bootstrapと接続し管理API/日英UI/監査/権限を実装。続いて管理者追加解除/最後の管理者保護/容量操作/Update導線。全14空DBInstaller往復、EN管理Save/統計期間操作、天気/背景upload/account停止実UI、統計実端末収集を継続。統計指標はdocs/statistics.mdに定義を保存、Phase9ゲート前にPhase10へ進まない。
## Phase9 検索・AIプリセット管理と全15Installer（2026-10-04）

前のGoalターンは統計集計d383d7b/両48/実JA期間と日英mobileによりprogress。progress/status/git/spec13〜19/92から次のPresetsへ再開。Phase9進行中、10〜12未着手、Version1.0未完成。

実装: ProviderPresets/PresetState/ProviderPresetRepository/AdminPresetsController、管理API GET/POST /api/admin/presets・HTML /admin/presets、公開 /api/provider-presets、home bootstrap、dashboard導線、日英フォーム。全体versionによる409/CSRF/server admin/厳密schema/HTTP(S) URL認証情報禁止/IDとPrefix全分類一意/各最低1enabled/順序。保存と監査before/after/actor/versionを同一transaction、file outbox、private排他cache失効・再公開。通常homeはcacheを利用しDB不能時は同梱値で端末検索を維持。個人の保存済み一覧はそのまま使い、利用者の検索設定から追加可能なpresetを明示追加する導線を実装。

Migration015: provider_presets行のINSERT IGNORE、site_settings.value_jsonとlog_entries.context_jsonをMEDIUMTEXTへ拡張。大きいcatalog/beforeafter監査を切り詰めない。test-only downは既存設定/監査が64KiB超なら拒否して保護。公開形式の並び順は既存同期のsortOrder、管理内部はsort_order。新しいpresetIDの匿名統計はcustom分類で名前/URLを送らない。

検証1: 既存専用8095/8096へ015追加、最終API/DB各36成功。権限/CSRF/不正URL/余分field/bool/重複/空enabled/順序/CAS、public更新とHEX Escape、日英編集HTML、同じ通常HTML保存、DB/file audit、再seedで編集保持、64KiB超catalog/audit、cacheでDBloader未呼出、権限剥奪。fixtureだけfinally削除、初期preset復元。最初HTML試験は303を200として失敗判定していたため303+GET200へ修正。同期仕様の監査でsort_orderを公開するとSyncDocumentが拒否することを発見し、sortOrder投影と実server validator試験で修正。実同期リクエストで新presetを編集するブラウザ検証は未実行。

検証2: Node provider-presets保存済み保持/disabledpreset明示有効化/衝突/安全URL/同期互換成功、search23/sync-data28、JS構文成功。tests/docker.ps1へadmin-presets追加、Parser/git diff --check成功。UI追加は純粋処理の試験で、実ユーザー操作の証拠ではない。

検証3: 新規search-phase9-presets-20261004を独立DB/config/storage volumesでbuildしMySQL8097/MariaDB8098で空DB全15up/repeat/down/Web Installer再up各40成功。これにより全14Installer未確認を最新全15で解消。続けて各presets36/statistics admin48/collection30/admin64/policy29/sync17/cloudAPI52/searchAPI/auth43/基盤39/PHP175成功。プロセス39825/13621/90698すべて正常終了。codecなしのimageであり圧縮環境の既存証拠と区別。既存8095/96等のconfig/storage/volumesを保持。最新Docker runner変更はhostのみ、製品コードは新imageに反映済み。

未確認: プリセット実管理ブラウザ保存/追加/削除/日英mobile/Console、ユーザー検索設定からのpreset追加と保存済み一覧保持、cache破損/実DB停止、大量同時編集、実Extension。今回はブラウザ操作を実行しておらず以前のtab23 EN統計ログイン要求等を最新UI成功の証拠にしない。実DiscordOAuth/全browser/大規模統計性能などの留保を維持。

次に実行すること: docs/admin-presets.mdとdocs/admin-ui-testing.mdに従い新環境localhost8097でwww-data所有の専用短期fixtureを準備し、JA/ENの管理preset保存/追加/削除・mobile/Consoleと利用者のpreset追加/既存一覧保持を実検証。認証fixture清掃後の画面を保存成功と誤認しない。続いて管理者追加解除/最後の管理者保護/容量操作/Update導線、EN管理Save/統計期間操作と残る機能停止/統計実端末試験。Phase9ゲート確定前にPhase10へ進まない。
## Phase9 プリセット実UI・保存済み一覧保持（2026-10-04）

前のGoalターンはプリセット9d381a4/両DB36/全15Installer40によりprogress。progress/status/gitとadmin-ui-testingを確認して実UIの留保から再開。Phase9進行中、10〜12未着手、Version1.0未完成。

実装修正: プリセット選択欄のdata-setting分類が一般へ移動する不具合を修正。data-provider-presetsで描画後の清掃だけ行い、検索設定のprovider-settings内へ配置。管理preset-formにfieldset/labelのgridと入力幅を追加しラベル・入力の混在を解消。tests/admin-ui-fixtureは015があればpresetも退避/cleanup復元し、www-dataでprepareして0600の所有者を一致させる。従来環境は設定行がなければpolicyのみ、他の異常は再throwする。

検証1（実ブラウザ）: browser2の新tab24/localhost8097、通常token fixtureでJA dashboardからpreset link成功。生成検索/AI各1件追加・Save・reload保持。ENで検索名変更/新たなdisabled検索preset追加/生成AI削除・Save・reload保持、生成検索2件もENから削除しexamples0確認。JA/EN管理モバイルは390px/content375、Console0。最初の画像で入力がinline混在したため修正後に画像再取得、JA/ENを目視確認。画像.test-output/phase9-presets-admin-ja.png/-en.png。追加/削除は生成専用catalog項目だけ、最後に元の全presetをfixtureから復元。

検証2（利用者実操作）: tab25/127.0.0.1:8097で無保存状態の新preset表示、GoogleをMy saved Googleへ変更して独自一覧保存。管理側で初期値を変更してもreload後に独自Google名と元のpreset名を保持。新しいdisabled初期presetを選び明示追加するとenabledとして末尾に表示。JA/ENの検索カテゴリ内の選択欄、全初期preset削除後も保存済み一覧を保持、390px/content375とConsole0を確認。画像.test-output/phase9-presets-user-mobile-ja.png/-en.pngとdesktop-jaを保存。ENとJA mobile画像を目視。生成した端末一覧は専用originに保持し、通常ユーザーの端末データを変更しない。

操作上の留保: 初回Wizardがreload後に遅れて開きカテゴリ操作を阻止、同tabの状態を確認してContinue laterを閉じ再操作成功。AXではpressed buttonがcheckboxとして見えるためDOM snapshotでnavigation/buttonを確認して操作。English検索先labelはselectとショートカット領域が重複しstrict selector失敗、表示済みselect #providerで確認。失敗操作を成功扱いにしない。

検証3（回帰）: 両DBpresets36/statistics admin48/admin64/auth43/基盤39/PHP175成功。Node preset保持/衝突/同期互換、search23/sync-data28とJS構文/git diff --check成功。MariaDBでもwww-data fixture prepare/cleanup成功。今回Migration変更なし、全15新規Installer40の直前証拠を維持。最後のfixture出力文言の変更後はPHP単体構文を確認する。secret/key/cookieは進捗/Git/chatへ出力しない。

清掃: MySQL fixtureのpolicy/presets復元、専用user/token/device/fixture/手動public preview/host key除去。実tab24 reloadでログイン要求に戻ることを確認。viewportreset。tab24 JA presetログイン要求/tab25 ENホームと既存18/19/20/21/23をhandoff。tab22エラーの復帰は未確認。管理実OAuth/全browser/Extension/cloud経由のpreset実端末共有、EN統計期間操作などの留保を維持。

次に実行すること: specとPhase9仕様を確認し、管理者追加/解除・最後の管理者保護・同時変更時の権限確認・DB/file監査・日英users UIを実装して両DBと実画面で検証。続いて容量管理操作とUpdate導線、未確認のEN管理policy Save/統計期間操作、weather/upload/account機能停止と匿名統計実端末収集。Phase9ゲート前にPhase10へ進まない。
## Phase9 管理者付与・解除と同時操作保護（2026-10-04）

直前のGoalターンは状態報告のみでno progress。progress/status/git/spec90〜92を再確認し、作業中の管理者権限処理を再検証してDocker runnerとMigration専用試験を追加した。Phase9進行中、10〜12未着手、Version1.0未完成。

実装: Migration016のadmin_roles版、AdminRoleRepository、POST /admin/users/role・/api/admin/users/role、日英usersフォーム。server admin/CSRF/厳密型/対象存在確認、一覧role_versionとexpected_admin_flagによる409、全変更を共通行ロックで直列化、待機後に現在の操作者権限を再確認。最後の管理者解除409、他の管理者がいれば自己解除可能。DB変更/版/ADMIN_ROLE_CHANGED監査を同一transaction、file outbox。no-opは版/監査を増やさずCreated By/Atは再付与でも保持。詳細docs/admin-roles.md。

検証1: 専用MySQL8097/MariaDB8098で管理者HTTP/DB各29成功。guest401/user403/CSRF403/Validation422/target404/stale409/最後の管理者409、付与解除、既存loginの即時権限反映、日英HTML/HTML303、actor/before/after/version/file監査を確認。生成ユーザーのみfinally削除、監査保持。

検証2: 両DB別プロセス競合各13成功。実ロック待機中の子プロセスを確認し、同時自己解除は一方のみ成功して管理者1人を維持。待機中に権限を失った操作者はADMIN_REQUIREDで拒否、残る管理者を解除できない。

検証3: 両DBMigration016各6成功。up/down/repeat/up-after-downと元設定の完全復元を確認。全16の空DBInstaller/down/upは未実行で、全15Installer40の既存証拠とは区別する。回帰は各admin64/auth43/policy29/presets36/statistics admin48/基盤39/PHP180成功。Docker runnerへ3試験追加。今回Docker起動の通常権限ではアクセス拒否となったが、承認された実行権限で既存専用環境を確認して検証成功、再作成なし。実画面の付与解除/mobile/Consoleは未実行。

次に実行すること: docs/admin-ui-testing.mdの専用短期fixtureを使い、生成した専用対象ユーザーで日英の管理者付与/解除・最後の管理者拒否・390px・Consoleを実画面検証。fixture/preview/keyは最後に清掃する。続いて独立した新規環境で全16Installer往復、容量管理操作とUpdate導線、EN policy Save/統計期間と残る機能停止/匿名統計実端末検証。Phase9ゲート前にPhase10へ進まない。実OAuth/全browserなどの未確認を成功扱いにしない。
