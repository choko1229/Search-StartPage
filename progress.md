# 再開ポイント

最終更新: 2026-10-02（Asia/Tokyo）

## 現在の状態

Version 1.0未完成。Phase 1は基盤検証済み。仕様全文の監査で見つかったPhase 2/3の不足を補修・再検証済み。Phase 4の実Discord往復は未確認。ユーザーの「ログインできたていですすめて」を優先。Phase 5の同期機能ゲートは検証済み（実OAuthを留保）。次はPhase 6。Phase 6〜12未着手。過去の判定より本記録の最新追記とdocs/spec-audit.mdを優先する。

Phase 2のAI頻度/最近順、検索・履歴キー変更、URL方針、クリック候補、履歴件数/期間/エリアを実装し3種類の検証を実施。ヘッダー履歴導線（§33）も実装・ブラウザ検証済み。Command Palette導線はPhase 8、履歴同期はPhase 5に接続する。

## 再開ルール

最初に本ファイル、docs/phase-status.md、git status --shortを確認。spec.mdはユーザー提供で変更しない。Phase順、最低3回の検証、Phaseごとにコミットを守る。未確認を成功扱いにしない。本番DB・push・公開・Windows再起動は自動実行しない。秘密値を記録しない。

## 環境

- Docker: C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe
- 両DB検証: search-test-20261002020651。app-mysqlは8080、app-mariadbは8081。新規Installer/全Migration/全PHP検証済み。
- UI: search-phase1-ui、http://127.0.0.1:8082/ 。DBはsearch-test-20260927223223-mysql-1。007_folder_owner_cascadeまで適用済み。
- 9/30に停止していた上記コンテナのみ再起動。旧環境のボリュームを保持。他の旧アプリは停止したまま。
- UI configはコンテナ内/var/www/app/config/config.php。ホストとは共有しない。
- Docker cpで反映する構成。直近public/lang/appは両DB検証環境へ反映済み。
- IABタブ2、検証画面。画像.test-output/phase3-layout.png（Git除外）。

## 今回の検証

1. JS: search-preferences 18項目、既存search 23項目合格。
2. MySQL 8 / MariaDB 10.11それぞれPHP構文59ファイル、単体39、検索API4、認証24合格。
3. Browser: Control+Enterの同一タブ検索、履歴エリア、空欄フォーカスの履歴候補、Control+Shift+H、件数25/期間7の遷移後保持を確認。390px幅でdocument375/dialog375/内容358、横はみ出しなし。console warn/error 0。

数値・キー設定は入力時にも妥当な値を保存するよう修正。検証用ローカル検索先と履歴は127.0.0.1のブラウザ内だけに保存。既存データを消していない。

## 次に実行すること

1. Phase 6の仕様をspec.md §37/53–56/68–82と添付Phase 6から照合。既存settings/storeを拡張し、設定メニュー、検索・お気に入り・パネルの共通配置、Undo最大20、設定初期化とユーザーデータ初期化の分離を実装する。
2. Theme（Light/Dark/OS/Custom/Presets、複数保存、地域の日の出/日の入り、0.5〜1秒transition）、Font（system/preset/Google/custom、size/weight/line height/spacing）、Animation None/Low/Standard/Richを実装。検索box/glassと時計/日付/挨拶の既定OFF、オンボーディング可変7〜8stepsへ接続する。
3. Phase 5は下記最新のゲート記録を参照。IndexedDBへ移行済み、300件長文+checkpoint、原子的ACK/失敗/保存中編集、旧データ移行/複数タブ/検索遷移/所有権削除を確認。背景ファイル本体はPhase 7へ。
4. 各Phaseを3回以上検証してコミット。Phase 4実OAuth/実認証済みブラウザは未確認を最終監査へ留保し、認証バイパスを追加しない。Version 1.0は全DoDまで未完成。
## 保存履歴

6254f38: 既存Phase 1〜3とPhase 4途中の基準保存。
6ca6bd3: アカウント容量表示・同期済みデータ削除処理。同期所有権マニフェストの書込みはPhase 5に未接続。
29d5bdb: 仕様全文監査。今回のPhase 2補修は別コミットで保存する。

## Phase 4最新検証

両DBで認証30/HTTP12/Rate Limit7/単体39/検索API4合格。専用DB停止試験各4項目合格、両DB復旧healthy。HTTPログイン429と通常検索200も合格。API成功ログアウト・現在端末解除・同時Rate Limitは確認済み。実Discord Callback成功・ログイン後ブラウザ操作が残る。設定状況の返答待ちでも独立した検証を進める。詳細はphase-status末尾。

## Phase 4追加検証

両DBで認証39、HTTP12、OAuth応答11、同時要求24プロセス（許可5/拒否19）を確認。API専用Callbackを使用するよう補修。docs/discord-development.mdの2つのRedirect URLをDiscordへ登録する。開発UI /accountの未設定表示をHTTPで再確認済み。ユーザーへの設定状況の質問は未回答。次は実Discord往復・ログイン後ブラウザ操作、残るPhase 4条件の照合。Phase 5未着手。

## 10/1の再開・修正

通常トップ/CSRF/検索候補へのアクセスで長期ログインが延長されないRegressionを修正。OptionalAuthenticationでCookieを検証し期限更新、DB障害時は未認証としてローカル機能を継続。両DBで構文66・認証42・HTTP12・検索API4合格。実DB停止試験はCookieあり/なしで各8合格。JS account-data12/検索23/既存favorites回帰合格。検証開始時はコンテナ停止を確認し、最新検証環境とUIだけ再起動した。旧環境・ボリューム保持。次は実Discord認証とログイン後ブラウザ操作。設定状況の質問は未回答で、Phase 4未完了。

## 現在の阻害条件（2026-10-01再確認）

開発UI /accountを再確認しHTTP200・Discord未設定表示を確認。9/30の成功系検証終了時、10/1の期限更新修正終了時、今回の再確認の3回で同じ設定不足が継続。Phase 4で独立して修正可能だったAPI/制限/期限更新/障害対応は検証済み。残る実OAuth往復とログイン後ブラウザ操作は開発用アプリ設定が必要。Phase順のゲートに従いPhase 5へ進めない。

再開条件: docs/discord-development.mdの2つのRedirectをDiscord Developer Portalへ登録し、bin/configure-discord.ps1を実行して非表示入力する。設定済みとユーザーから連絡後、/accountのログインボタンを確認し実認証・端末操作・ログアウト・再ログインを検証する。秘密値をチャットに求めない。

設定スクリプトもWeb/APIの両Redirectを案内するよう修正、PowerShell構文確認済み。スクリプトの実値入力は未実行。Goalは外部設定待ちのblockedへ変更する。Version 1.0未完成。

## 最新指示による再開（2026-10-01）

ユーザー指示「ログインできたていですすめて」を優先し、実Discord成功を仮定した開発前提でPhase 5へ進む。Phase 4の実OAuth・ログイン後ブラウザ操作は成功扱いにせず未確認として最終監査へ持ち越す。これまでの「設定待ちのためPhase 5へ進めない」はこの指示により解除。実Secretや認証バイパスを追加しない。

Phase 5着手: public/assets/js/sync-core.jsに3-way項目マージと競合選択処理を追加。ID単位のコレクション、設定の異なる項目は自動マージ。同一項目、削除対編集はPrevious/Local/Cloudを保持。未解決競合の保存を拒否。選択ルール適用、危険なキー・重複ID拒否。単体23項目・JS構文・account-data12の既存回帰成功。

次に実行すること: 両DB対応の同期版管理MigrationとRepository、認証/CSRF付き同期APIを実装し、同時更新の版不一致409を検証。その後既存CRUDのAPI、初回Local/Cloud/Later、競合UI/保存ルール、即時/適応間隔の同期、同期成功時所有権記録を接続する。今回のマージ関数だけでPhase 5を完了扱いにしない。

## Phase 5最新の再開地点（2026-10-02）

同期版管理の005_syncを両DBへ適用。GET/PUT /api/syncを実装、認証/所有者/CSRF/Validation/版競合409を確認。文書は同期用のIDマップ、空オブジェクトを保持する。SQLはSyncRepositoryへ配置。既存エンティティCRUD API・DB接続とフロント同期はまだ未完了。

両DB: 構文71、同期15、基盤39合格。JS: 同期23、検索23、account-data12合格。初回HTTPテストのGET Content-Type誤りを修正し再合格。UI環境へ005/APIをまだ反映していない。最新両DB検証環境へ反映済み。

次は既存エンティティCRUD APIと同期文書の整合を実装し、初回選択/競合画面・ルール保存・即時/適応間隔・所有権記録を接続する。Phase 4実OAuthはユーザー指定で未確認を留保。Phase 5完了を判定する前に新規隔離環境でInstaller/全Migrationも再検証する。

## Phase 5 フロント同期接続（2026-10-02）

sync-session/data/api/dialogs/sync.jsを接続。初回Local/Cloud/Laterは両方にデータがある場合に選択、競合はPrevious/Local/Cloud表示・項目選択・ルール保存。変更後250msの同期と10秒/1分/5分の整合性チェック、通信中編集保持、最大3回の版競合再試行、変更なしのPUT省略を実装。Laterは現在の画面で保留し手動再開。履歴同期は端末設定の既定OFF、クラウド履歴を維持し端末履歴は送受信しない。背景は対象外でPhase 7へ。

ACK後にcheckpoint/所有権/データをまとめて保存。容量超過時はcheckpointを進めない。プロバイダー順序をsortOrderで同期、クラウド文書に検索先キーがない場合は標準検索先を維持。複数タブのstorageイベントで再読込、同期メタ情報だけの変更でタブ間の同期ループを起こさない。ログアウト削除はcheckpointも除去。アカウントの最終同期は現在ユーザーの所有権と一致する場合だけ表示。

同期中のアカウント切替を再確認し、PUTのuser_idは認証済み所有者との不一致を403で拒否する照合用。user_idで所有者を選択しない。既存認証・CSRFを保持。CSRF応答キーはcsrf_tokenを使用し通信契約の回帰を追加。

検証1: JS構文、同期merge23/session32/data18/API通信12/store12、account-data12、検索23/設定18、お気に入り/レイアウト16合格。
検証2: UI/両DBでPHP構文71・基盤39、両DBで同期API17合格。UIへ005も適用し再実行0件。最初に存在しないtests/unit.phpを指定して失敗、正しいtests/run.phpで再実行合格。
検証3: 開発画面の同期欄と未ログイン状態を確認。独立UI fixtureで初回3択/Later、Previous/Local/Cloud表示、未選択の保存拒否、Cloud選択とルール保存を確認。390pxでdocument390/dialog358/content356、warn/error0。画像.test-output/phase5-conflict.png。fixtureは実ログイン・DBを利用せず、実OAuth成功の代替にはしない。

ブラウザ検証で設定初期化により同期欄が消えるRegressionを発見、設定一覧と別の兄弟要素へ移し再確認。最終コードをUI/両DBへ反映。認証済み実ブラウザによる端末間往復、各CRUD API/既存DBとの整合、完全Validation、新規Installer/全Migrationは残る。Phase 5未完了、Version 1.0未完成。次は上記「次に実行すること」1から再開。

## Phase 5 同期文書とエンティティDBの整合（2026-10-02）

006_sync_entities: 既存5テーブルにclient_id/payloadを追加、既存IDをclient_idへ保持。内部IDは所有者/種類/client_idから導出し、同じプリセット・項目IDを異なるユーザーが持てる。sort_orderはBIGINTへ拡張（お気に入りの既存値はミリ秒）。設定はuser_settingsのキーごとに保存、sync_versionsには項目ごとの版と削除の版を保持。

SyncProjectionRepositoryは初回に既存テーブルを読んで文書化し、sync_states保存と同じトランザクションで既存テーブル・タグ関連・設定・版を反映する。正本はsync_statesで、payloadは追加フィールドを含む再構成用。フォルダcreated_atを保持。Validationに名称/型/長さ/URL認証情報拒否/時刻/タグ/重複prefix/フォルダ名/ショートカット/所有フォルダ参照を追加。端末専用設定は同期文書で拒否。

007_folder_owner_cascade: 元の複合FKがユーザー一括削除時にもRESTRICTを起こすことを実DB検証で発見し修正。フォルダを論理的に削除するAPIでは先にお気に入りのfolderIdを解除する（APIは次に実装）。所有者を含む複合FKは維持。006 downは追加列/テーブルを除去し、データ切捨てを避けるため拡張sort_orderと名称collationは縮小しない。

検証1: 両DB構文75・基盤39・同期API17・新規投影31成功。既存データの初回保存、他ユーザーの同一client_id、並び順/タグ/設定/履歴/AI追加フィールド、古い版の拒否、削除と版、所有者分離、Validation失敗を確認。
検証2: 両DBでMigration再実行0、認証42/HTTP12/検索API4/SSRF8成功。UIも006/007適用・再実行0、トップ/CSRF HTTP200。
検証3: JS同期23/32/18/12、store12、account-data12、検索23/設定18、お気に入り/レイアウト16回帰成功。

初回投影試験はMySQLのトリガー作成権限で失敗し、後片付け中に外部キー問題も発見。権限を拡大せず、専用テストDBの一時CHECK制約で保存途中のSQL失敗を起こす検証へ変更。失敗後に同期文書/版・設定・関連行が元に戻ることを確認し、制約をfinallyで除去。007修正後に両DBの全ケースを再実行成功。成功扱いにしていない失敗履歴を保持。

未完了: 個別CRUD/POST同期/競合解決API、新規Installer/全Migration往復、認証済み実ブラウザの端末間同期。Phase 5未完了、Phase 6未着手。次は冒頭「次に実行すること」1。

## Phase 5 個別CRUD・競合解決API（2026-10-02）

Settings、Favorites/open、Favorite Folders、Search History、Search Engines、AI Providersの§117 APIを追加。GETは所有者の項目と同期版、変更はJSON version/item（Settingsはsettings）とCSRFが必要。部分更新・id保持、生成UUID、初期値、404/422/重複id409、古い版409、所有者照合を実装。フォルダ削除はfavorite.folderIdを解除し、同じ同期トランザクションでDBへ反映。

POST /api/syncを追加しWebをPOSTへ変更。PUTも互換維持。POST /api/sync/resolve-conflictはPrevious/Localと現在CloudをPHPで項目マージ、未解決はPrevious/Local/Cloudを409で返し未保存。choices/rulesで解決しCAS保存。PHPの欠損/null/配列/数値等はJSと同じ扱い。ルールは応答しクライアント保存。docs/cloud-api.mdにプロトコルを記録。

検証1: 既存両DBで構文81/基盤39/マージ8/個別API50/同期17/投影31。API50は成功、CSRF、所有者、重複、版、部分更新、生成履歴、フォルダ保持、統計OFF、匿名拒否、同一項目/削除対編集の競合選択を含む。
検証2: 新規隔離search-test-20261002020651で全PHP検証成功。各DB: Installer35（7Migrationの初回/再実行/down/Installer再適用）、構文80（config生成前）、基盤39、検索4/SSRF8、OAuth11/認証42/HTTP12、同期17/投影31/マージ8/API50、制限7/同時24（許可5拒否19）/HTTP429+検索200。ログ.test-output/phase5-fresh-retry.log。
検証3: JS構文と同期23/32/18/通信12/store12/account-data12/検索23/設定18/お気に入り/レイアウト16成功。UIへ反映し構文81・トップ200。

最初の新規試験search-test-20261002020315ではInstaller最終HTTPが20秒でタイムアウト。サーバー303、installed=yes・Migration7を確認。試験のInstaller完了リクエストだけ120秒へ修正し、新規環境で再検証合格。初回ログ.test-output/phase5-fresh-docker.logは保持し成功扱いにしない。失敗環境の4コンテナは停止・ボリューム保持。旧search-test-20260929221803のアプリ2つは停止・DB/ボリューム保持。現在8080/8081は成功した新規環境。

Phase 5は未完了。次は冒頭の2端末エンジン+実HTTP統合、履歴同期ON/OFF/物理期限削除、オフライン復帰を確認する。実OAuth/認証済み実ブラウザの未確認は留保。Phase 6未着手。

## Phase 5 実HTTP2端末・履歴・保存ルール（2026-10-02）

tests/sync-http-fixture.php/ps1/test.mjsで通常AuthRepositoryの専用ユーザー・端末Cookieを使用し、Webと共通のSyncSession/data/apiを実HTTPへ接続。認証値を出力せずGit除外の一時ファイルで受渡し、finallyでファイルとユーザーを除去。プロジェクト名を専用テスト形式へ限定。実Discord/認証済みブラウザの代替とはしない。

2端末の設定/背景設定メタ/フォルダ/タグ/お気に入り/利用回数、一方だけの履歴同期OFF、異なる項目の並列マージ（実409再試行1回）、同一項目競合、保存ルール共有/エンジン再構成後の適用、オフライン編集をJSON再構成してオンライン復帰、別ユーザー分離/初回Laterを確認。両DBで3回繰返し成功、追加修正後も両DB再成功。端末専用の背景ファイルは送信しない。

不具合補修: 初回並列保存で一度HTTP失敗。INSERT IGNOREの共有→排他ロック競合を避け、INSERT ON DUPLICATE KEY UPDATEで初めから排他取得し実並列試験を繰返し成功。履歴OFF→ONで既存Cloud履歴が消える不具合を再現し、同じユーザーのcheckpointと切替フラグで初回だけ両方を保持するよう修正。その後の個別削除は正常同期。初回Local/Cloud選択や別ユーザーのcheckpointにはこのマージを適用しない。

競合ルールはsettings.syncRulesとして共有保存し、JSONパス/選択をサーバーValidation。古いローカル保存ルールは同じユーザーのcheckpointから移行。ACK後の所有権は履歴OFFでも以前同期したIDだけ保持し、ログアウトで同期済みを削除・新しい非同期履歴を保持する。

SyncRetentionは保存/取得時に既定300件/90日・ユーザー設定で削除し、文書・関係行・削除版を同一CASで更新。変更なしは版を進めない。古い版は削除済み履歴を復活できない。queryの上限を検索欄と同じ12,000文字に補修、長いAI入力10,000文字の実HTTPを追加。

検証1: 両DB構文84、基盤39、sync17、projection34、cloud API52、retention9、PHP merge8、認証42/HTTP12/検索4成功。Migration変更なし、前回の新規7本往復は維持。
検証2: 実HTTP2端末試験を両DBで3回成功＋追加後も再成功。一時ファイルなし・ユーザー0を確認。初回失敗は保持し成功扱いにしない。
検証3: JS構文、merge23/session32/data23/通信12/store12/account-data12、検索23/設定18/favorites/layout16成功。ブラウザの履歴同期チェックが保存イベントで元に戻る問題を発見し、設定と切替フラグを一括保存して修正。ON→再読込保持→OFF・同期ON/OFF表示を確認。390px document375/dialog375/content358、warn/error0。画像.test-output/phase5-history-sync.png。ゲスト画面の検証であり実OAuth成功とはしない。

残る容量不整合を生成データで確認: 既定300件×日本語12,000文字=文書10,823,525 bytes、現在の512KiB Validationでは422。検索・履歴の上限値を小さくして回避しない。API Body/文書/メモリ/ブラウザ本体+checkpointの永続保存を実装・検証してからPhase 5を判定する。Phase 5未完了、Phase 6未着手。再開手順は冒頭を優先する。

## Phase 5 サーバー容量・メモリ補修（2026-10-02）

3676e58に直前の実HTTP2端末/履歴/ルール共有実装を保存。今回はSyncDocumentの計測をDB同様Unicode非エスケープJSONへ統一しMEDIUMTEXT上限16MiB未満、同期/解決本文32MiB（他API1MiB維持）、Requestの二重decodeを一度へ変更。bodyはroot配列でnestedオブジェクトをjsonObjectと共有、全利用箇所を照合。Docker post_max_size=32M。本番Web/PHPも本文32MiB以上が必要とAPI手順に記載。

大文書の競合解決で128MiBメモリ不足を再現。RepositoryでSQL読込行/statementが保持するJSON/前回文書をACKの読込前に解放し、memory_limitを増やさず両DBで成功。初回試験の403は試験側のJSON/CSRF指定漏れ、修正後の500は上記メモリ不足。失敗を成功扱いにしない。

検証1: 実HTTPを両DBで2回成功。各回、通常2端末/CASと300件×12,000文字の4バイトUnicode（14,428,059 bytes）、読込/競合解決/古い版409/所有者分離。最後の回にはJSON root配列400、一般API1MiB超413、文書16MiB超422・版維持も追加成功。一時認証ファイル/専用ユーザーはfinallyで除去。
検証2: 両DB構文84/基盤39/sync17/projection34/merge8/retention9/cloud API52/auth42/auth HTTP12/search4成功。Migration変更なし、全7本の新規往復は既存証拠を使用。
検証3: JS merge23/session32/data23/API12/store12/account-data12回帰成功。UIへapp/php.iniを反映しApacheを設定再読込。今回新しいブラウザ操作検証は未実施。

残件: ブラウザ本体+checkpointの永続保存容量・書込失敗時のACK保持/オフライン再読込/複数タブ。次は冒頭1からIndexedDB移行を実装し実ブラウザで確認する。Phase 5未完了、Phase 6未着手、実OAuthはユーザーの進行前提に従い未確認を留保。
## Phase 5 IndexedDB・同期機能ゲート（2026-10-02）

store-database.jsでtop-levelキーごとのIndexedDB保存。モジュール初期化時にLocalStorageの全データを同じwrite transaction内のmarker照合で移行しreadback後に旧コピーだけ除去（ログアウト削除後の第二コピー残存を防止）。初期化失敗時のクラウド同期/削除は停止、ローカル検索は維持。LocalStorage-only fallbackも維持。

通常setは画面即時反映、キー別永続キューと失敗した変更のflush再試行。setManyはtransaction成功後にACK/所有権/文書を公開。保存中編集のrevision保護、生成関数で変更を再マージして保存し直し、queued setは最新のキー値を保存する。SyncSessionはasync accept完了を待つ。Webでは保存待ち中の編集も項目マージし次回即時同期を予定。BroadcastChannelで別タブをDBから再読込。検索/お気に入りの遷移はflushを待つ。accountも同じstoreで使用量/設定/ログアウト削除。

検証1: JS全構文、store12/IndexedDB21/session37/merge23/data23/API12/account12/search23/preferences18/favorites/layout16成功。IndexedDBモデルはquota transaction abortによるACK/文書保持、失敗したローカル編集のflush再試行、再構成、保存中の異なる設定項目のCloud+Local両方保持を確認（モデル試験であり実quota枯渇ではない）。
検証2: 実HTTPの2端末/CAS/オフラインJSON再構成/履歴/ルール共有/大文書を両DB再成功。PHP・DB・Migrationは今回変更なし、直前の両DB全PHP/新規全7本往復証拠を継続使用。
検証3: IAB fixture実IndexedDBで日本語12,000文字×300履歴とcheckpoint約21.6MB保存/再読込、invalid clone失敗でACK/データ不変、保存中編集/再読込、別タブ変更反映、旧favorite/local背景の移行、所有権対象のみ削除/再読込とローカルfavorite/背景保持を確認。通常UI8082でfavorite使用回数2→3を遷移後に確認、検索indexeddb-history-testをローカル先へ実行し遷移後履歴保持。warn/error0。画像.test-output/phase5-indexeddb.png。認証をバイパスしない独立storage fixture。

添付Phase 5ゲート照合: settings/favorites/history同期（実HTTP+投影）、conflict detection/resolver（JS/PHP/実HTTP+独立UI）、offline queue foundation（ローカル変更+ACK baseの永続化/復帰）、device consistency（実HTTP2端末/複数タブ）を確認。同期機能ゲート検証済みとしてPhase 6へ進む。実Discord/OAuth・実認証済みブラウザはユーザー指定の前提に従い最終監査へ未確認を留保。Background Settingsメタは同期し、実ファイル/ライブラリUIはPhase 7で接続。Version 1.0未完成。