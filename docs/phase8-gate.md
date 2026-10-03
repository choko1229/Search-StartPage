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
