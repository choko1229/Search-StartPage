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
