# 再開ポイント

最終更新: 2026-10-03（Asia/Tokyo）

## 現在の状態

Version 1.0未完成。Phase 1は基盤検証済み。仕様全文の監査で見つかったPhase 2/3の不足を補修・再検証済み。Phase 4の実Discord往復は未確認。ユーザーの「ログインできたていですすめて」を優先。Phase 5の同期機能ゲートは検証済み（実OAuthを留保）。Phase 6機能ゲート検証済み（実OAuth留保）。Phase 7は背景ライブラリの実装中、Phase 8〜12未着手。過去の判定より本記録の最新追記とdocs/spec-audit.mdを優先する。

Phase 2のAI頻度/最近順、検索・履歴キー変更、URL方針、クリック候補、履歴件数/期間/エリアを実装し3種類の検証を実施。ヘッダー履歴導線（§33）も実装・ブラウザ検証済み。Command Palette導線はPhase 8、履歴同期はPhase 5に接続する。

## 再開ルール

最初に本ファイル、docs/phase-status.md、git status --shortを確認。spec.mdはユーザー提供で変更しない。Phase順、最低3回の検証、Phaseごとにコミットを守る。未確認を成功扱いにしない。本番DB・push・公開・Windows再起動は自動実行しない。秘密値を記録しない。

## 環境

- Docker: C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe
- 最新両DB検証: search-phase7-20261002。MySQL8083/MariaDB8084、新規Installerと全8Migration往復を検証済み。旧8080/8081も保持。
- UI: search-phase1-ui、http://127.0.0.1:8082/ 。DBはsearch-test-20260927223223-mysql-1。008_backgroundsまで適用済み。
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

1. Phase 7は末尾の端末内ファイル保存記録から再開。IndexedDB blobとメタデータの原子的保存、再読込/編集/保管/復元を検証済み。次は背景ごとのCloud Sync・ファイル/メタ同期・競合/オフライン復帰、全条件編集/天気・地域/動画の手動再生。背景APIは項目version、既存同期文書にはライブラリをまだ含めない。OSファイル選択と巨大ファイルの実操作は未確認。
2. Phase 6機能ゲートはdocs/phase6-gate.md。実OAuth/認証済み名前/Profileと実OS reduced motion切替は未確認を最終品質監査へ留保。ユーザーのログイン成功前提を維持、認証バイパスを追加しない。
3. Phase 5は下記最新のゲート記録を参照。IndexedDBへ移行済み、300件長文+checkpoint、原子的ACK/失敗/保存中編集、旧データ移行/複数タブ/検索遷移/所有権削除を確認。背景ファイル本体はPhase 7へ。
4. 各Phaseを3回以上検証してコミット。Phase 4実OAuth/実認証済みブラウザは未確認を最終監査へ留保し、認証バイパスを追加しない。Version 1.0は全DoDまで未完成。
## 保存履歴

6254f38: 既存Phase 1〜3とPhase 4途中の基準保存。
6ca6bd3: アカウント容量表示・同期済みデータ削除処理。同期所有権マニフェストの書込みはPhase 5に未接続。
29d5bdb: 仕様全文監査。今回のPhase 2補修は別コミットで保存する。

## Phase 6 最新の再開地点（2026-10-02）

テーマ（Light/Dark/OS/Presets/複数Custom/地域太陽時）、フォント（System/preset/Google/HTTPS Custom）とサイズ/太さ/行間/文字間、Animation4種、挨拶・時計・日付の基本表示、検索欄Glassと配置/幅/高さ/色/透過/ぼかし/枠線/角丸/影を実装。詳細はdocs/appearance.md、phase-status最新追記。時計・日付の追加style、header、初回案内が残る。Phase 6未完了。

検証1: 全JS構文・appearance37/history19と既存全単体回帰成功。実HTTP専用testを引数なし一括実行した失敗は記録し、適切な単体一覧で再成功。今回実HTTPの追加実行はしていない。
検証2: UI/両DB構文84、両DB基盤39/sync17成功。DB/Migration変更なし、最新ソース反映済み。
検証3: 旧標準confirm停止は新タブ9で解消。reset/Undo・重要Close取消/破棄・Mobile全画面・PCresize非永続を確認。Dark/複数Custom/Customfont読込/時計日付挨拶の保存、検索欄420px/72px/透過.4/ぼかし20px/角丸28px/影なしと再読込を確認。390pxで横はみ出しなし、console warn/error0。入力途中が戻る問題をdraft保持とblur保存で補修。標準高さ56pxを確認。

検証UIはGeneral/Appearance初期値に戻し、Custom A/B定義と既存favorites/history/providerは保持。画像は.test-output/phase6-appearance.png等（Git除外）。IABタブ9をhandoffで保持。ユーザー指定のログイン成功前提は継続、実OAuth/実認証ブラウザは未確認。冒頭「次に実行すること」から継続する。

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
## Phase 6 設定基盤（2026-10-02）

§71–76を実装開始。カテゴリ9種のsidebar modal、検索/AI/Privacy/Shortcutsに既存設定を分類、favorite設定とsyncを同じmodalへ移動、ヘッダー導線/#settings、desktop resize/mobile full screenのCSS、再表示時width/height解除。重要な未確定provider設定を閉じる際の確認、カテゴリ別fixed reset lists（ユーザーデータを含めない）。Appearance/Background/Generalの新機能はまだ未実装、空カテゴリを完了扱いにしない。

settings-history.jsは日時/設定/Previous/New・存在フラグを20件保持、Undo/Redo、Undo後の新変更でRedoを破棄、カテゴリresetのUndo。settingsHistoryはtop-level端末専用でsyncDocumentに含めない。通常setSetting、同期toggle、account設定を接続し、最後に使った検索先等の操作記録は除外。reset/UndoはIDB原子的setMany。検索画面/favorite toolbarにも設定変更を反映。

検証1: settings-history19、store12/IndexedDB21/session37/data24/API12/account12/search23/preferences18/favorites/layout16と全JS構文成功。sync-dataで履歴メタが送信されないことを追加確認。
検証2: 両DBPHP構文84/基盤39/sync17成功。Viewと翻訳の最終構文84も再成功。DB/Migration/APIルート変更なし。app/public/langはUIと両DBへ反映。
検証3: IABでカテゴリ表示、チェック変更→Undo→Redo、再読込して日時/Previous/Newと20件履歴のcursor保持を確認。カテゴリresetの標準confirmで操作が停止（Input.dispatchMouseEvent/Emulation focusタイムアウト）、getJsDialog/closeも同じ状態。新タブ8は描画できるがイベント無反応。reset成功とはしない。確認をアプリ内dialogへ変更、構文/単体は成功、変更後のブラウザ検証は未確認。Mobile/resize/Close重要設定も未確認。今回は検証画像未作成。

残件: 上記未確認UIとtheme/sunrise/glass/custom fonts/animation/greeting/clock/date/header/onboarding。挨拶はspec§69で既定ON（以前の進捗の既定OFF記述は誤り、時計/日付だけOFF）。Phase 6未完了、Phase 7へ進まない。

## Phase 6 時計・日付styleとヘッダー（2026-10-02）

§68の独立配置/サイズ/フォント/色/不透明度、§70のヘッダー上下/整列/サイズ/不透明度/背景/ぼかし/項目順序/表示を追加。既存の保存/同期/Undoへ接続。ログイン導線は既存認証APIの現在ユーザー有無でLogin/Profileを表示し、実OAuthは未確認。Migrationなし。

検証1: display-layout21、appearance37/history19と全JS構文・既存全単体回帰成功。
検証2: UI/両DB構文84、両DB基盤39/認証HTTP12/sync17成功。最新JS/CSS/翻訳/View反映済み。
検証3: 時計64px/Mono/検索欄下、header下/左/blur8/brand非表示/順序変更の保持、390px document/header375px、同じ右上の時計と日付が非重複、日英設定、Google Font loaded、架空座標によるLight/Darkを確認。warn/error0。画像.test-output/phase6-header-display.png、phase6-header-mobile.png（Git除外）。

不具合: 数値のblur保存による設定リスト再描画がcheckbox clickを消した。ヘッダー設定の内容が変わる場合だけ再描画し、再検証成功。地域の未確定入力保持とClose確認、カテゴリ初期化後の入力欄反映を補修。検証専用座標・General/Appearance選択を初期値へ戻し日本語へ復帰、entityとCustom定義を保持。

次は冒頭1の初回ウィザード。Phase 6未完了、Phase 7へまだ進まない。

## Phase 6 ウィザードと機能ゲート（2026-10-02）

初回Wizardを追加。ゲスト8steps、認証済みはDiscord案内なし7steps。各Skip/Back/Later、途中再開、完了後非表示、設定から再実行。設定と進捗を原子的saveSettingsで保存、進捗はtop-level端末専用・同期対象外。背景は実単色選択を実装、画像/動画等はPhase 7へ拡張。

検証1: 全JS構文、onboarding18/appearance42/display21/history19、既存store/IndexedDB/sync/search/favorites回帰成功。最終sync-dataはWizard進捗を送信しない25項目成功。
検証2: UI/両DBPHP構文84、両DB基盤39/認証HTTP12/sync17成功。Migrationなし、既存7Migrationの検証証拠維持。最新ソース反映。
検証3: 初回自動表示、Dark保存→再読込でBackgroundから再開、単色反映、SearchのBing未保存をSkipしてGoogle保持、重複キー拒否、Discord案内Skip、完成・再読込で非表示。再実行/Later/再読込Welcome、全Skip、Back、日英・390px（document375/dialog358/content356）、AnimationNone duration0s、設定Escape/外側Close、warn/error0。画像.test-output/phase6-onboarding.png/onboarding-mobile.png（Git除外）。

境界試験で日の出の小数msとDate整数msの誤差による比較不一致を発見。太陽時を整数msへ丸め、日の出Light/日の入りDarkの境界を追加して再成功。検証用背景・外観を初期値へ戻し日本語復帰、Wizard完了済み。既存entity/Custom定義保持。

添付Phase 6の11機能ゲートとspec§53〜56/68〜82をdocs/phase6-gate.mdで照合。Phase 6機能ゲート検証済みとしてPhase 7へ進む。実OAuth/実認証済み名前/Profile、実OS reduced motion切替は未確認を最終監査へ留保。7stepsは認証済み実ブラウザではなく単体確認。Version 1.0未完成。次は冒頭Phase 7手順。

## Phase 7 背景ライブラリ基盤・最新再開地点（2026-10-02）

Phase 7実装中。Solid/Gradient/Image/Video、HTTPS URL・サイト内パス、プリセット、端末内ライブラリ、編集・復元可能な保管を実装。ぼかし/明るさ/重ね色/位置/倍率/fit、動画の速度/ミュート/ループ/停止とモバイル代替画像を接続。手動/ランダム/条件切替、時間条件の編集を接続。11条件とAND/OR・詳細条件優先・同順位ランダムは判定コアのみ実装し、全条件の編集UIや天気データ接続は未実装。

検証1: background-core 41項目、sync-data 28項目と追加JS構文確認成功。ライブラリの端末専用選択を同期ACKで上書きしない回帰を追加。
検証2: UI/両DBでPHP構文84ファイル成功。基盤39/sync17はこのPhase初期の両DB回帰で成功。DB/API/Migrationの追加はまだない。
検証3: 実ブラウザでGradient保存/再読込、Image読み込み、動画速度1.5/ミュート/ループ/停止、390pxの代替画像と横はみ出しなし、時間条件切替、編集・保管/復元、背景カテゴリreset後のライブラリ保持を確認。console warn/error 0。画像.test-output/phase7-gradient.png、phase7-mobile-fallback.png、phase7-library-mobile.png（Git除外）。動画検証はMDN公式flower.webmのURLを使用、ファイルをダウンロードしていない。

上記コードは未コミット。次は既存変更の回帰と途中コミット、その後アップロード（画像25MB/動画500MB）・安全な所有者別保存・圧縮後容量・環境不足時の警告・背景ごとのCloud Syncを実装する。現在のライブラリは端末専用で、アップロードや背景ファイル同期を完了扱いにしない。条件編集UI/天気・地域同期、動画の手動再生導線も残る。Phase 7未完了、8〜12未着手。実OAuth未確認はユーザー指定に従い最終監査へ留保。今回の状態確認では新たな検証は実行していない。

### Phase 7 基盤の再検証（2026-10-02）

再開後、全JS構文と実HTTP専用を除く全JS単体が成功（背景41/同期データ28を含む）。UI/両DBへ最新ソース反映、構文84、両DB基盤39/認証HTTP12/同期17成功。背景切替間隔の入力を保存イベントで上書きしない補修と、不正なライブラリ型の描画防御を追加。直前の実ブラウザ証拠を維持、今回この2補修の追加ブラウザ操作は未実施。基盤を途中コミットとして保存し、次はサーバー側のファイル検査から続ける。Phase 7未完了。

## Phase 7 アップロード検査・私有保存（2026-10-02）

直前基盤をee76732へ途中保存。BackgroundUploadサービスを追加。拡張子/実MIME/画像寸法/実サイズ（25MiB/500MiB）、名前のパス/制御文字/危険な二重拡張子を検査。HTTP由来ファイルだけを認証済みIDの私有ディレクトリへ乱数名で保存し、0600/0700とsymlink拒否を適用。API/DBへの接続は未実装。サービスへ渡す所有者は今後Authから取得する。圧縮前ファイルの検査であり圧縮後容量を完成扱いにしない。

検証1: 両DB隔離コンテナで検査22単体成功。25MiB/500MiB超、偽装・危険名・HTTP由来でないローカルファイル拒否。
検証2: 独立loopback HTTP試験14項目を両環境www-dataで成功。実HTTP upload、申告MIMEを無視、非公開保存/直アクセス404、乱数/同名上書き防止、権限0600/0700、symlink保存先拒否、失敗時ファイルなし。一時専用サーバーと領域はfinally除去。アプリの認証API成功やCSRF検証とは区別する。
検証3: 両DBPHP構文87成功。直前JS全構文/全単体と両DB基盤39/auth HTTP12/sync17成功。今回新規UI操作なし、既存背景ブラウザ証拠を維持。DB/Migration変更なし。

docs/background.mdに仕様・現時点の範囲と残件を記録、Docker一括検証に2試験を追加。次は圧縮と環境不足検出、DBメタ/認証・CSRF API/容量競合・失敗時清掃、500MiB multipart設定、Upload UI/Cloud Syncへ接続する。現在post_max_size32Mのまま。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## Phase 7 実圧縮サービス（2026-10-02）

BackgroundCompressionを追加。Imagick優先→GD、FFmpegをPATH/SEARCH_FFMPEG_PATHで検出。寸法/PNG色・透過、JPEG EXIF向き、Imagick GIFフレーム・遅延・loopを保護。小さくなった検証済み出力だけ採用し、元ファイルはDB確定前に削除しない。最終ファイルbytesを計測。機能不足/encoder失敗は元ファイル+警告、GDアニメーションやAPNGは保持。Imagickリソース制限、FFmpeg引数配列/入力demuxer固定/ネットワークprotocol拒否/2threads/120秒/500MiB出力上限。InstallerのFFmpeg判定を同じ自動検出へ統一。Adminへの警告表示はPhase 9で接続が必要。

検証1: 元の両DB環境で機能不足8項目成功、両DB基盤39/auth HTTP12/sync17、構文89成功。Migrationなし。
検証2: Docker専用search-compression-test:20261002を作成しsearch-compression-20261002を稼働。www-dataでImagick/GD/FFmpeg17、GDのみ/FFmpeg16、proc_open禁止12成功。実PNG圧縮・色/透過・向き付きJPEG・GIFアニメーション・実動画圧縮・失敗時元データ維持・一時出力清掃を確認。画像/動画は試験内ローカル生成。
検証3: 上記各経路を補修後に再成功。最初のImagick試験はautoOrientImageという利用できないメソッドで失敗しGDへfallback、優先順試験で検出。autoOrientと互換手動回転へ修正し再合格。GDのみの写真向きも確認。今回UI操作なし、既存ライブラリUI証拠維持。

専用DockerfileはBASE_IMAGE引数（既定php:8.2-apache-bookworm）、GD/EXIF/Imagick3.8.1/FFmpegを任意検証用に追加。本アプリDockerfileは変更していない。専用コンテナは待機状態で次回試験に再利用可能、DBなし。既存2DB/UIは保持。全形式/巨大動画/120秒期限の実発動/ICCの実写真は未検証。

次はdatabase/migrationsの背景メタDBとRepository、認証/CSRF API、圧縮後bytesを使う容量制限の原子的適用、失敗時清掃、その後Upload UI/Cloud Sync。Requestはまだ$_FILESを持たず、PHP multipart上限32Mのため500MiB受付設定も必要。Phase 7未完了、8〜12未着手。

## Phase 7 背景DB・実アプリAPI（2026-10-02）

008_backgrounds: backgrounds/background_rulesを所有者+IDの複合キー/FKで追加。BackgroundInputはURL/数値/色/boolean/11条件・AND OR/実暦日・深さ12/100nodes/32KiBを検査。BackgroundRepositoryは所有者row lock/項目version CAS/圧縮後bytes。config backgrounds.max_bytes既定0=合計制限なし、各ファイルの仕様上限は常時有効。ControllerにSQLなし。

GET backgrounds、POST upload/url、PUT/DELETE背景ID、GET/HEAD file。Auth/所有者・CSRF、実multipart→私有保存→圧縮→DB、DB拒否時候補除去。64KiB stream/single Range/206/416。DELETEは復元可能な保管、容量保持。永久削除/孤立再清掃は未実装。cloud_syncはDB項目で端末間同期フローは未接続。ファイル名/絶対パスはAPIへ返さない。

検証1: 旧両DB API25、並列quota6を3回ずつ成功。初回stale試験は422で失敗、partial updateのruleをarrayにしてしまう問題をobject保持へ補修し再成功。
検証2: 新規search-phase7-20261002（MySQL8083/MariaDB8084）でInstaller35/全8Migration往復、背景25/quota6/基盤39/sync17/projection34/Cloud52/auth HTTP12、検査22/独立HTTP14/圧縮不足8、構文96成功。
検証3: 不正sync flag null/別認証所有者のfile404追加後、新規両DB API27/構文96成功。UI8082に008/app/lang/tests/php.ini反映しトップ200/PHP diagnosticsなし。UI HTTP初回はPowerShell予約変数誤用の試験側エラー、名称修正後成功。今回新規ブラウザ操作なし。通常Cookieの実HTTP認証で、実Discord往復は未確認を留保。

PHP multipart502M/file500M/input600s/execution180s。JSON一般1MiB/同期32MiBは維持。500MiB実HTTP、実圧縮+DB容量の組合せ、全条件UI、永久清掃、Admin警告は未確認/未実装。秘密値は記録せず、隔離DBの生成値だけ使用。

次はLocal Upload UI/IndexedDB blob、背景ごとのCloud Sync ON/OFF・ファイル/メタ同期・競合/オフライン、全条件編集/天気・地域/手動動画再生。最新両DBは8083/8084、UI8082も008済み。Phase 7未完了、8〜12未着手。途中コミットして続ける。

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
