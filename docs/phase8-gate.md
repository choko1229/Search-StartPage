# Phase 8 機能ゲート

2026-10-03開始。spec§48〜52と添付Phase8を根拠にする。Phase8未完了。

| 完了条件 | 現在の実装・証拠 | 状態 |
|---|---|---|
| command registry | CommandRegistry、カテゴリ/effect/ID/keywords検査、重複拒否、解除と再登録、callback保持、製品登録接続 | 単体・標準コマンドUI確認済み |
| search | NFKC/大小文字/空白正規化、title/keywords、部分一致・部分列、結果上限、Ctrl+K | 単体・JA/EN入力UI確認済み |
| ranking | exact/prefix/relevance、usage/recency/category、安定順序、使用記録上限500、IndexedDB保存 | 単体・recent/frequentの再読込確認済み |
| cross search | commands/favorites/search/AI/settings/tags/folders/historyの製品登録、5初期グループ | favorite/folder/tag横断検索と操作確認済み、全カテゴリ操作は検証中 |
| actions | callback実行と非同期完了、二重実行防止、確認待ちで削除されたコマンド拒否 | theme/settings/folder/tag/favorite/provider確認済み、残る操作を検証中 |
| confirmations | 実確認画面、操作別設定と原子保存、取消・保存失敗では実行しない | themeの省略/再ON、削除取消、provider確認済み、共有/reset/故障UIは未確認 |
| extensible architecture | DOM非依存registry/executor、export paletteCommands.register、解除callback、検索は副作用なし | 単体確認済み、追加登録の実UI検証は未確認 |

開始時はregistry/executorのみだったが、現在はsearch.jsから製品画面へ接続済み。下記の最新検証記録を優先する。Phase8未完了、DB/Migration追加なし。

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
