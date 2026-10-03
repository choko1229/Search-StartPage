# Phase 9 ゲート監査

2026-10-04。判定: **進行中**。Phase10へまだ進まない。仕様はユーザー提供の `spec.md` §90〜99/118/Phase9と添付Phase別仕様。Version1.0全体の完成判定とは別に、未確認を残して成功としない。

| 条件 | 確認できた証拠 | 残る確認・実装 |
|---|---|---|
| Admin Auth | 両DB admin72/roles29。guest401、user403、現在のDB権限、CSRF。通常tokenの実画面 | 実Discord OAuthはユーザー指示で最終監査へ留保 |
| Dashboard | 実DB件数、圧縮可否、通常管理者の実画面 | 更新通知はUpdater未実装 |
| Users | 検索/ページング/escape/日英UI、roles29/競合13/016Migration6 | 実画面の権限付与・解除・最後の管理者拒否は明示承認待ち。レビュー拒否後は未実行 |
| Storage | 両DB admin72/backgroundAPI56、全保存・active・archive容量、容量順、有効quota、超過時保持。日英390px、EN quota保存/再読込 | 実ブラウザで非ゼロ背景の超過表示は未確認。一時・未参照ファイルは容量表示対象外と明示 |
| Presets | 両DB36、日英実追加/編集/削除/reload/390px、ユーザー明示追加と独自一覧保持、全15/16Installer | cache破損/実DB障害/大量同時編集、実Extensionとcloud共有は未確認 |
| Feature Flags / Limits | 両DB policy29、サーバー停止/権限/CSRF/CAS、日英管理UI、JA保存とEN容量保存。実クラウド同期停止・再開、JA/EN account停止理由、EN account復旧 | weather/upload停止・復旧、停止中に追加した端末変更の再送、今回のモバイル幅は未確認 |
| Maintenance | サーバー全面停止・管理者通常利用・API保護の既存検証、日英画面 | 全ブラウザ検証とUpdaterとの連携は未確認 |
| Logs / Audit | DB/file、安全なエラー、フィルター、監査の同一transaction/outbox、90日整理CLIの既存検証 | OS定期実行未設定、実Updater categoryの発生経路は未実装 |
| Statistics | 両DB collection30/admin48、期間/指標/日別表/SVG、匿名schema、日英mobile、JA/EN期間操作。実MySQL停止503→復旧・reload再送、再読込で検索重複なし、実2タブ同時検索 | 大規模性能、実Extension、実端末のACK保存失敗・多数タブstress・完全ネットワークofflineは未確認 |
| Statistics 実操作 | localhost専用DBへvisit4、Web検索1/AI検索1/favorite_open1/settings2/favorites1/command_palette1を実ブラウザ操作から保存 | 背景操作、実認証の同期、実外部preset分類、未配信イベントの端末上のACK確認は未確認 |
| Update Management | VERSIONは0.1.0-dev、仕様§104〜109の処理順と保護対象を確認 | 管理画面導線、GitHub Releases確認/各channel/通知/Download/Verify/Backup/Maintenance/Replace/Migrate/Verify/Rollbackは未実装。表示だけで完了にしない |
| DB / Regression | 独立MySQL8099/MariaDB8100で全16up/repeat/down/Installer各40、PHP180、管理者/認証/背景/同期等の回帰 | 新しいMigrationが増えたら新規環境で再確認 |

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
