# Phase 6 機能ゲート

2026-10-02。ユーザー指定の実Discordログイン成功前提で判定し、実OAuth/認証済みブラウザの未確認は最終監査へ留保。Version 1.0全体は未完成。

| 仕様・完了条件 | 実装と検証の証拠 | 判定 |
|---|---|---|
| Theme / Presets / 複数Custom (§77) | appearance-core/appearance.js。Dark、Custom A/B保存・再読込・選択を実UI確認。危険色を拒否する単体 | 機能確認済み |
| Sunrise / sunset (§78) | NOAA近似、極昼/極夜・日付変更線・日の出/日の入り境界の単体。架空地域入力でLight/Dark切替を実UI確認。未設定はOS fallback | 機能確認済み |
| Transition (§79) | 既定.75秒、ユーザー設定、None/reduced motionでは停止。単体・実UI Noneのcomputed duration 0s | 確認済み |
| Font (§80) | System/Serif/Mono/Google/Custom、size/weight/line height/spacing。公式HTTPS CustomfontとGoogle Font loadedを実UI確認。URL資格情報/非HTTPSを拒否 | 確認済み |
| Animation (§81) | None/Low/Standard/Rich、初期Rich、CSSの軽量transition/hover。OS reduced motion優先を単体で確認。実OS設定を切り替えた検証はPhase 12へ留保 | 機能確認済み |
| Search box / Glass (§53–56) | responsive/fixed320〜900、高さ48/56/72、配置3種、背景/透過/blur/枠線/角丸/影/文字/placeholder。420px/72px/透過.4/blur20/角丸28と再読込、初期56px、390pxで横はみ出しなし | 確認済み |
| Clock / Date (§68) | 初期OFF、表示/独立配置/size/font/color/opacity、12/24h/秒/日付形式/曜日。実UI64px/Mono/検索欄下・再読込。同じ隅で非重複 | 確認済み |
| Greeting (§69) | 初期ON、時間帯・カスタム文を実UIと単体確認。名前は既存user APIからtextContent。実認証後の名前表示は未確認 | 機能確認済み・実認証留保 |
| Header (§70) | 上右寄せ、設定/履歴/Login・Profile、上下/整列/size/opacity/背景/blur/順序/visibility。再読込・390px・日英実UI確認 | 機能確認済み・実Profile留保 |
| Settings modal / save / Close (§71–74) | 9カテゴリsidebar、desktop center/resize非永続、mobile全画面、autosave、重要draft確認、X/Escape/外側。既存provider取消/破棄とCustom重要draft、実UIで確認 | 確認済み |
| Undo / Redo / category reset (§75–76) | 原子的変更、20件/日時/項目/prev/new、端末専用、固定リストreset・entity保持。単体19、実Undo/Redo/reset→Undo/再読込。sync-dataで履歴除外 | 確認済み |
| 初回Wizard (§82) | 8steps、認証済みはDiscord案内を省く7steps。各Skip、Back、Later、完成・再実行、途中再開をIndexedDB保存。設定は原子的に保存。Backgroundは実単色選択、画像/動画等はPhase 7で追加 | 基本機能確認済み（7stepsは単体） |

共通検証: 全JS構文・onboarding18/appearance42/display21/history19と既存回帰。UI/両DBPHP構文84、両DB基盤39/認証HTTP12/sync17。Migration/API/認証方式の変更なし。日英・390px実UI、console warn/error0。

添付Phase 6の11完了条件を機能ゲートとして確認済み。Phase 7へ進む。実Discord往復/名前/Profile、OS reduced motion切替、Firefox/Safari/実拡張機能の検証は最終品質監査で未確認を解消する。未確認を成功としない。
