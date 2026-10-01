# 再開ポイント

最終更新: 2026-10-02（Asia/Tokyo）

## 現在の状態

Version 1.0未完成。Phase 1は基盤検証済み。仕様全文の監査で見つかったPhase 2/3の不足を補修・再検証済み。Phase 4の実Discord往復は未確認。ユーザーの「ログインできたていですすめて」を優先しPhase 5を実装中。Phase 6〜12未着手。過去の判定より本記録の最新追記とdocs/spec-audit.mdを優先する。

Phase 2のAI頻度/最近順、検索・履歴キー変更、URL方針、クリック候補、履歴件数/期間/エリアを実装し3種類の検証を実施。ヘッダー履歴導線（§33）も実装・ブラウザ検証済み。Command Palette導線はPhase 8、履歴同期はPhase 5に接続する。

## 再開ルール

最初に本ファイル、docs/phase-status.md、git status --shortを確認。spec.mdはユーザー提供で変更しない。Phase順、最低3回の検証、Phaseごとにコミットを守る。未確認を成功扱いにしない。本番DB・push・公開・Windows再起動は自動実行しない。秘密値を記録しない。

## 環境

- Docker: C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe
- 両DB検証: search-test-20260929221803。app-mysqlは8080、app-mariadbは8081。
- UI: search-phase1-ui、http://127.0.0.1:8082/ 。DBはsearch-test-20260927223223-mysql-1。005_sync適用済み。
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

1. Phase 5の§117 Settings/Favorites/Folders/History/Search Engines/AI ProvidersのCRUD APIと既存テーブルを同期文書へ整合させる。現在はsync_statesが同期APIの保存先で、既存テーブルへまだ投影していない。
2. エンティティの詳細Validation、順序・削除・所有者・同時更新、履歴同期ON/OFFのAPI/UI統合を検証する。現在の認証/CSRF/版競合/初回選択/競合選択は保持する。
3. 新規の専用Docker環境でInstaller/全Migration/全PHP回帰を両DB検証。8080/8081を使用中の現在のアプリだけ停止してから新規環境を起動し、ボリュームは削除しない。
4. Phase 5の全条件を照合し最低3回の検証とコミットを行ってからPhase 6へ。Phase 4実OAuthは未確認として最終監査に留保し、認証バイパスは追加しない。

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
