# Phase 8 機能ゲート

2026-10-03開始。spec§48〜52と添付Phase8を根拠にする。Phase8未完了。

| 完了条件 | 現在の実装・証拠 | 状態 |
|---|---|---|
| command registry | CommandRegistry、カテゴリ/effect/ID/keywords検査、重複拒否、解除と再登録、callback保持 | 単体確認済み、製品UI接続は未実装 |
| search | NFKC/大小文字/空白正規化、title/keywords、部分一致・部分列、結果上限 | 単体確認済み、Ctrl+Kと入力UIは未実装 |
| ranking | exact/prefix/relevance、usage/recency/category、安定順序、履歴の上限500 | 単体確認済み、永続usage接続は未実装 |
| cross search | commands/favorites/search/AI/settings/tags/folders/historyの8カテゴリ登録、recent/frequent/favorites/search/AI初期グループ | 単体確認済み、製品データ登録は未実装 |
| actions | callback実行と非同期完了、二重実行防止、確認待ちで削除されたコマンド拒否 | 実操作接続は未実装 |
| confirmations | state操作は既定確認、操作キーごとのfalseのみ省略、取消/保存失敗では実行しない | 単体確認済み、確認画面・設定・実保存は未実装 |
| extensible architecture | DOM非依存registry/executor、registerの解除callback、検索では副作用を実行しない | 単体確認済み、製品登録窓口は未実装 |

新規tests/command-registry.test.mjsとcommand-executor.test.mjs、既存回帰を含む全JS30が成功。新規モジュールはまだsearch.jsにimportしていない。PHP/API/DB/Migration変更なし。Phase7の既存検証を維持。UI・レスポンシブ・console・実操作は今後検証する。

次の接続ではFavorites/Providers/History/Settingsの既存処理を利用する。テーマ/背景/検索先/AI変更、Favorite追加/削除、履歴消去、Login/Logout、Random Backgroundを実操作へ接続し、実行/取消/次回から確認しないと操作別設定を検証する。確認省略でサーバー権限/CSRFを省略してはいけない。Usageは実行成功後に記録し、検索/取消/失敗を使用として数えない。
