# 実装と検証の記録

更新日: 2026-09-27

## Phase一覧

| Phase | 内容 | 状態 |
| --- | --- | --- |
| 1 | Core Foundation | 完了（2026-09-27、PHP 8.2 / 両DB / ブラウザ検証） |
| 2 | Search Core | 完了（両DB Migration、API、JS単体、ブラウザ主要操作） |
| 3 | Favorites | 完了（2026-09-29） |
| 4 | Discord Auth | 実装中 |
| 5 | Cloud Sync | 未着手 |
| 6 | Appearance | 未着手 |
| 7 | Background System | 未着手 |
| 8 | Command Palette | 未着手 |
| 9 | Admin | 未着手 |
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
