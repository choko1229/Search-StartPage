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
