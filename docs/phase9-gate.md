# Phase 9 ゲート監査

2026-10-04。判定: **Phase9機能ゲート検証済み、Phase10へ移行**。仕様はユーザー提供の `spec.md` §90〜100/118/Phase9と添付Phase別仕様。Version1.0全体の完成判定とは別に、未確認を残して成功としない。

## 最終ゲート判定

添付仕様の「Phase 9完了条件」はadmin auth / users / storage / statistics / logs / audit / maintenance / feature flags / limits / presetsの10項目。以下の表に全項目の実装・両DB検証・実UI証拠がある。各機能の最低3回の検証は既存Phase9記録と専用harnessの結果を参照し、今回の文書照合を新たな機能テストとは数えない。

照合1: 添付仕様の完了条件10項目を表と対応付けた。照合2: spec §90〜100/118の管理権限、容量、匿名統計、ログ/監査、停止、制限、プリセットとAPI保護の記録を確認。照合3: 最新の権限実操作、DB障害復旧、更新中のMaintenance維持、独立2保存領域の同期停止/復帰の証拠と清掃記録を確認。Phase9内の既知機能不具合は補修後の検証済み。

残る未確認は消さない。実Discord OAuthはユーザーの「ログインできたていですすめて」により留保。全browser/実端末/OS chooser/大規模性能/アクセシビリティと最終Glass調整はPhase12、Extension実利用はPhase11〜12、実GitHub配布元・サービス運用・更新失敗時のMaintenance自動復元はPhase10で追跡する。本番配置は自動実行しない。これらをPhase9合格やVersion1.0完成の証拠に含めない。下表の未確認欄は引継ぎ一覧で、更新機能はPhase9中に接続・先行検証した実装をPhase10で再監査する。

| 条件 | 確認できた証拠 | 残る確認・実装 |
|---|---|---|
| Admin Auth | 両DB admin72/roles29。guest401、user403、現在のDB権限、CSRF。通常tokenの実画面 | 実Discord OAuthはユーザー指示で最終監査へ留保 |
| Dashboard | 実DB件数、圧縮可否、通常管理者の実画面。保存済み候補の新リリース通知、管理者homeの通知とguest/user非通知・権限解除で非通知を両DBで確認 | 対象GitHubの実候補取得は404のため未確認 |
| Users | 検索/ページング/escape/日英UI、roles29/競合13/016Migration6。両専用DB roles29/基盤40を各3回。ユーザーの操作時点承認後、IAB MySQL日本語/MariaDB英語で実付与・解除・最後の管理者拒否、390px、最終operator1/target0・監査2件/前後/版/file配送一致を確認（docs/admin-roles-ui.md） | 実Discord OAuth/全browserは未確認。最初の共有hostでのログイン失効（Cookie干渉疑い）が疑われる401は成功扱いにせず、専用host名で分離後に確認 |
| Storage | 両DB admin72/backgroundAPI56、全保存・active・archive容量、容量順、有効quota、超過時保持。日英390px、EN quota保存/再読込。実アップロード2394813bytes/1件を100制限に下げても保持・超過説明表示 | 一時・未参照ファイルは容量表示対象外。非ゼロ超過表示のEN/全browser確認は残る |
| Presets | 両DB36、日英実追加/編集/削除/reload/390px、ユーザー明示追加と独自一覧保持、全15/16Installer。専用両DBで破損JSON/無効schemaのcache修復・実DB stop/start時の公開API/home保持/初期値fallback/復旧/監査保持を各3回31項目、基盤40、fresh全17/repeat成功 | 障害中の実ブラウザ操作/大量同時編集、実Extensionとcloud共有は未確認。docs/admin-presets.md参照 |
| Feature Flags / Limits | 両DB policy29、権限/CSRF/CAS、日英管理UI、容量保存。実クラウド同期停止・再開、日英account停止理由/復旧。JAとENのupload停止中に端末保存→再開でcloud1件。EN専用MySQL/IABで68byte/0→1file、390px、weather停止理由/POST403→再開200/理由解除、認証済み公開地域の保存/reload/実cloud一致/警告なしを確認 | OS filechooser APIは今回反映なし、生成File選択fixtureで保存・同期を確認。独立2originの同期停止/復帰は下記追記で確認済み。実2台/全browser/過去警告因果はPhase12へ留保。docs/policy-ui-verification.md参照 |
| Maintenance | サーバー全面停止・管理者通常利用・API保護の既存検証、日英画面。両専用DB/FPMで実HTTP停止→実Engine更新→公開再開→実runner手動復元を各3回33項目/基盤40。停止中の匿名503/管理者200、更新後の停止維持、復元後の最新解除version/signal/監査2件保持を確認 | 停止中のMigration失敗による自動復元はPhase10で両DB各3回確認。全ブラウザはPhase12へ留保。docs/update-fpm-http.md参照 |
| Logs / Audit | DB/file、安全なエラー、フィルター、監査の同一transaction/outbox、90日整理CLI。専用Docker両DBの実2cycle/二重起動拒否。実PDO接続失敗→同じworkerの2秒後再試行/復旧を各3回、unsafe file lock失敗→修復/再試行を各3回確認。更新workerの失敗をupdate_errorへ同一transactionで分類し、結果再投影・file配送再試行・ApplicationLogger queue分離を専用試験で確認。実Engine復元後にも25件の失敗記録を保持 | 本番ホスト未配置。実1時間待機、DBサーバー停止、disk full/OS権限障害は別の未確認。file配送は管理ログ閲覧/整理時で、即時配送や中断時のexactly-onceではない |
| Statistics | 両DB collection30/admin48、期間/指標/日別表/SVG、匿名schema、日英mobile、JA/EN期間操作。実MySQL停止503→復旧・reload再送、再読込で検索重複なし、実2タブ同時検索 | 大規模性能、実Extension、実端末のACK保存失敗・多数タブstress・完全ネットワークofflineは未確認 |
| Statistics 実操作 | localhost専用DBへWeb/AI/favorite/Palette操作を保存。通常UIから生成背景2件保存に対応するfeature background2を観測 | 実Discord認証の同期、実外部preset分類、未配信イベントの端末上のACK確認は未確認 |
| Update Management | Release取得/4channel選択と管理者check/API/CSRF/監査/通知。両DBで実配布物/検査/backup/file+DB置換/Migration/health/自動・手動復元/ユーザー変更保持/中断rescue/安全停止、専用Apache変更PHP反映を確認。MySQL管理画面の実更新・復元、日英/390px/Console0。PHP8.2/8.3の独立2 FPM master・各2 childでcache刷新/復元を各3回22項目。PHP8.3/Nginxの実HTTP受付→worker/Engine/両DB更新→新PHP→復元/旧PHPも各3回24項目確認（docs/update-fpm-http.md） | 対象repo404/実GitHub取得、独立複数FPM masterでの実DB更新、サービスboot/restart/長時間運用、本番配置、全browserは未確認。MariaDB実ブラウザ更新/復元はPhase10で限定確認済み（docs/update-management-ui.md）。残件はPhase10で追跡 |
| DB / Regression | 両DB全17Migration fresh/repeat/往復、Installer各40、最新配布物PHP160構文。今回のログ障害試験は両専用DBでfresh17/repeatと基盤40・scheduleを各3回確認 | 新しいMigrationが増えたら新規環境で再確認。実ブラウザの未確認は上記各行を参照 |

2026-10-04サービス設定追記: 出荷unitを専用PHP8.3/Debian Bookwormのsystemd-analyzeで診断なしと確認。欠落実行ファイル/無効Type/不明設定名の負例を含む静的9項目と、既存CLI実プロセス25項目を各3回成功。Windowsコピーの実行権限警告を0644へ修正して全3回再検証。managerは起動せず、実enable/boot/restart/manager stop/長時間運用は上表の未確認を維持。docs/update-execution-service.md参照。

2026-10-04同期UI追記: navigation再試行のユーザー明示許可後、MySQL JA/MariaDB EN・同一IABの独立2originで初回Cloud選択/A→B→A地域同期、停止中の端末変更/reload保持/日英理由、再開後の送信/受信を確認。両cloud4/大阪保持→再開後cloud5/東京一致true、Console4tab0、JA/EN390px/375page。専用環境/生成権限/入口清掃済み。上表の「他端末認証済みUI」は独立origin限定で確認済み、実2台/全browser/OAuth/過去警告因果は残る。docs/admin-sync-ui.md参照。

## 匿名統計の実ブラウザ証拠

2026-10-04、browser2/tab28、127.0.0.1:8099の専用originで通常UIからWeb/AI各1件の生成検索先を追加し、URLは同じローカルサイトを指定。Web検索とAI検索で同一タブのURL遷移と履歴2件を確認。生成お気に入りを保存して開き、Paletteの「履歴を開く」を実行した。最終DB集計は上表の7集計区分・計11イベント。Web/AIのproviderはcustomで、query/URL/provider名を統計へ送らない。

DB観測はCLI/testmodeの一時スクリプトでevent_type/source/event_data/件数だけを返した。anonymous_id/event_idは出力せず、観測スクリプトはコンテナから除去した。生成された端末設定・検索履歴・お気に入り・匿名イベントは専用環境に保持する。通常ユーザーや別originのデータは変更していない。

画像 `.test-output/phase9-statistics-live-history.png` は生成履歴とPalette実行後の画面表示の証拠。Console warn/error0。最初の設定操作はWizardが阻止したため、画面で「あとで続ける」を閉じ再操作した。訪問成功だけで他イベントの成功とせず、DB件数で各操作を確認した。

統計API両30とNodeのprivacy/offline/reload/ACK失敗/batch/source/通信中編集試験も再成功。ただし単体の故障注入は実ブラウザの通信障害・ACK・複数タブ競合の証明ではない。

## 実DB障害・再送・2タブと履歴Regression

同じ専用環境でMySQLコンテナだけを停止し、通常のローカル検索が遷移できることを確認。アクセスログから統計POSTの503だけを抽出し、生ログや識別子は表示しなかった。復旧後reloadでsearch customが1→2、visitが4→6となり障害中の検索が届いた。さらにreloadと新tab29でvisit8になってもsearch2のまま。再送による検索の重複はなかった。

実際の2タブで検索ボタンを同時クリックし、異なる生成queryがそれぞれ遷移してsearch2→4/visit8→10を確認。ただし履歴はtab Aが欠落していた。原因はhistory.jsの古い配列の上書きと起動時の期限整理にも同じ上書きがあったこと。historyの追加/削除/全消去/期限整理を条件付きsetManyへ変更し、競合時に最新データへ再適用する。検索は履歴保存を待ってから遷移し、実保存失敗でも検索は続行する。削除画面もcommit後に更新する。

正常なstorage_conflict再試行でstorage-unavailableを表示する問題も修正。実際のquota失敗は警告を維持する。IndexedDBのstale-tab追加/削除・保存abort・clear再試行・警告分類試験を追加し成功。検索23/設定18/store14/sync-data28/session37/merge23/account/background ACK/session/統計queue回帰も成功。

最終コードで別の生成query C/Dを実2タブから同時検索し、履歴の両行を確認。前の修正試行のA/B行も残り、誤保存警告なし、両tabのConsole warn/error0。最終DBはsearch8/visit18とその他6件（7集計区分計32）。最初に欠落した旧tab A履歴は自動復元しておらず、修正後の行保持だけを証明する。画像 `.test-output/phase9-statistics-retry-history.png` を目視確認。DBはhealthyへ復旧し一時観測スクリプトは除去。実端末ACK保存失敗、完全network offline、多数タブstressは別の未確認条件として残す。

## Phase9 クラウド同期停止・アカウント復旧（2026-10-04）

直前ターンは状況報告のみで実装進捗なし。progress/status/gitから再開。中断した専用8099のfixtureを最初にcleanupして退避設定を復元し、新規通常prepareで再検証。Phase9進行中、Phase10〜12未着手、Version1.0未完成。管理者権限の実UI操作は承認待ちで再試行していない。

検証1: browser2/tab4 localhost8099の生成ユーザー、通常Auth/AdminMiddleware。基準の同期成功を確認後、tab5管理policyでcloud_syncだけ無効化・JA保存。手動同期で管理者停止理由を表示、JA accountでも同じ理由と従来の最終同期を確認。EN切替後のaccountにも停止理由。EN policyで同じflagを有効化・Save、手動同期Synced、EN account Synced/最終同期時刻更新を確認。Console warn/error0。端末の新規変更を停止中に作成する試験は今回未実行。実Discord OAuth成功の証拠にはしない。

画面: phase9-sync-disabled-en.pngを保存・目視確認。viewport390指定後もDOM innerWidth1280/document1265で、モバイル成功には数えない。phase9-sync-restored-en.pngは描画が崩れた画像で復旧表示の視覚証拠に採用しない。復旧状態はDOMのSynced/最終同期更新で確認。viewport reset済み。実モバイルの再確認を残す。

清掃: fixture cleanupでpolicy/presets復元、生成user/token/device/非公開fixture除去。手動previewとhost短期keyも除去。account reloadでDiscord設定を必要とする未ログイン画面へ戻ることを確認。ユーザーの通常データやspec.mdは変更なし。

検証2: 清掃後の隔離MySQL8099/MariaDB8100でadmin-policy各29、sync各17合格。CSRF/権限/Validation/CAS/停止復旧/所有者分離を回帰。Migrationのrepeat安全性もpolicy試験で合格。新規Migration/PHP変更なし、直前全16Installer40/PHP180の証拠を維持。

検証3: Node site-policy validation/disable/recovery/auth separation、sync-session37、sync-data28、account-data既存21+所有者別pending upload cleanup/capacity合格。製品ソース変更なし。docs/phase9-gate.mdのcloud/account証拠を更新。

次に実行すること: 専用環境でbackground_uploadsとweatherを個別に停止・復旧する実UI、背景操作匿名イベント、停止中の新規変更保持・再送、実モバイルを確認。LogRetention定期実行とUpdate管理/Phase10処理境界の残件を進める。管理者実権限操作は明示承認があれば新規fixtureで検証。Phase9ゲート確定前にPhase10へ進まない。

2026-10-04保存警告補修: 無関係な統計等の更新でsaveSettingsの再試行が枯渇する不具合を修正前Nodeで再現、依存collection限定後に保持/実quota警告回帰成功。実ブラウザ25回保存+更新12回/2tab存在下22回、警告0と別tab反映/reload保持を確認。以前の認証済み同期中の警告と同原因の断定や、異なる設定の同時tab編集の保持はまだ証明していない。

2026-10-04追加: 通知前の古いtabからsaveSettings/setSettingが別tabの異なる設定を消す不具合を修正前Nodeで再現し条件付き保存へ補修。実IndexedDBの2tabでtest-only通知受信抑止を使い地域10/20とfont44、逆方向30/40とfont45の両方保持/reload/警告0を確認。製品の通知抑止ではない。認証済みcloud同時操作や任意collection全般の競合まで証明した扱いにしない。

2026-10-04更新配布物基盤: 専用build CLI/USTAR manifest/管理対象allowlist/private stage検証を追加。両63項目、実207ファイル/3292160bytes・stagePHP137構文・独立tar一覧一致・実config保持。内部整合の準備であり、GitHub asset取得/外側検証/Backup/Replace/Migrate/Health/Rollbackは未達。Phase9ゲート未完了。docs/update-package.mdを参照。
