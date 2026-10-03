# Phase 8 機能ゲート

2026-10-03開始・機能ゲート確定。spec§48〜52と添付Phase8の7完了条件を根拠にする。実OAuth等の未確認は下記の最終監査へ留保し、Version1.0完成とは扱わない。

| 完了条件 | 現在の実装・証拠 | 状態 |
|---|---|---|
| command registry | CommandRegistry、カテゴリ/effect/ID/keywords検査、重複拒否、解除と再登録、callback保持、製品登録接続 | 単体・標準コマンドUI確認済み |
| search | NFKC/大小文字/空白正規化、title/keywords、部分一致・部分列、結果上限、Ctrl+K | 単体・JA/EN入力UI確認済み |
| ranking | exact/prefix/relevance、usage/recency/category、安定順序、使用記録上限500、IndexedDB保存 | 単体・recent/frequentの再読込確認済み |
| cross search | commands/favorites/search/AI/settings/tags/folders/historyの製品登録、5初期グループ | 8カテゴリ単体と実UI、履歴再検索・JA/EN確認済み |
| actions | 実操作接続、非同期完了、二重実行/古い登録防御、原子削除、owner限定Logout回復 | 操作UI、専用storage、両DB実API確認済み。実OAuth留保 |
| confirmations | 実確認画面、操作別設定と原子保存、取消・保存失敗では実行しない | 確認省略/再ON/取消、reset再読込、両DB2端末共有、失敗保持確認済み |
| extensible architecture | DOM非依存registry/executor、export paletteCommands.register、解除callback、検索は副作用なし | 単体と追加登録/実行/例外/解除の実画面確認済み |

開始時はregistry/executorのみだったが、現在はsearch.jsから製品画面へ接続済み。下記の最新検証記録を優先する。DB/Migration追加なし、既存9Migration/Installer両DBの証拠を維持。

次の接続ではFavorites/Providers/History/Settingsの既存処理を利用する。テーマ/背景/検索先/AI変更、Favorite追加/削除、履歴消去、Login/Logout、Random Backgroundを実操作へ接続し、実行/取消/次回から確認しないと操作別設定を検証する。確認省略でサーバー権限/CSRFを省略してはいけない。Usageは実行成功後に記録し、検索/取消/失敗を使用として数えない。

## 製品接続（2026-10-03）

Palette画面とCtrl+K/キーボード、5初期groups、8カテゴリの製品登録、列挙操作、実確認画面、操作別確認設定/原子保存、成功usageを実装。JA/ENでテーマ変更（Cancel/確認なし/再ON）と設定移動、recent/frequentの再読込保持、検索先Cancel、390px/console0を確認。全JS30、両DB基盤39/認証42成功。お気に入り/tag/folder/history等のPalette実操作、background/provider実変更、logout/同期削除回復、拡張登録、保存失敗・ARIA監査は未確認。Phase8の完了判定はまだ行わない。

ログアウトの回復intentとowner照合を追加。新core単体と両DB認証43、全JS31成功。実HTTPと製品storageを組み合わせたLogout回復/故障UIは未確認で後続検証。Phase8未完了。

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
