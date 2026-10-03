# 背景を生かす画面デザイン

2026-10-04のユーザー指定「背景を生かした透明感のあるデザイン」と添付参考画像を反映。参考画像の中央のアプリ画面を対象とし、周囲の説明・ブラウザ枠は製品画面に含めない。

`public/assets/css/glass.css` が現在のテーマpaletteから半透明surfaceを生成する。検索バー内にWeb/AI切替を配置し、挨拶を主役にしてサイト名はヘッダーとアクセシブルな見出しへ集約。検索の明示設定（色・不透明度・ぼかし・幅・高さ・角丸・影）と時計/日付/ヘッダーの配置設定を維持。ヘッダー背景色未設定の場合だけ透明化する。設定・account等にも共通surfaceを適用。backdrop-filter非対応時のpanel/dialog/tileは不透明fallback。低/無animationの既存設定を維持。

背景プリセット「夕暮れのテラス / Terrace at dusk」を追加。既存の背景やsettingsを移行・削除せず、通常設定から選択する。専用localhost8099のプレビューはDark/中央/Large/角丸36を通常UIで選択した状態で、全ユーザーへの設定強制はしていない。プレビューの4お気に入りは専用originに通常UIで追加した検証データであり、新規ユーザーの標準favoritesではない。

背景asset: `public/assets/backgrounds/terrace-dusk.png`。組込みimage_genツールで新規生成、元画像はCodex生成領域に保持してworkspaceへコピー。参考画像を背景として転載していない。生成prompt:

> Wide 16:9 wallpaper for a personal search start page. Serene highly detailed cinematic illustrated evening panorama seen from a Japanese hillside terrace: lavender and deep navy clouds, thin crescent moon, orange sunset near the horizon, distant bay and city lights, dark wooden terrace with a small warm lantern and foliage at lower left. Open central sky for UI overlay. Dreamy polished anime landscape, understated contrast and atmospheric depth. No people, text, UI, browser frame, logos or watermark.

検証: JA/EN通常UI、背景選択/reload保持、Web/AI切替、4favorite追加、設定開閉、Console warn/error0。初回案内は遅れて開くため閉じた後で再確認。実測390px/document375/search343、設定dialog390/content373で横はみ出しなし。幅変更直後のDOMは1280だったが、後続の実DOM・画像で390を確認して区別。viewport reset後の新規desktop1280/document1265も確認。画像は`.test-output/glass-home-ja-desktop-final.png`と`glass-home-ja-final.png`（mobile）、`glass-settings-mobile-ja.png`。

回帰: appearance42/background41/library8/search23/preferences18/onboarding成功。新プリセットによる期待リスト・最初のtype変更にonboarding試験を追従。存在しない試験名を使った初回起動は未実行で、実在ファイルから再成功。PHP両DBで変更View/lang構文成功、基盤40（検索入力案内とcolor labelの重複keyRegressionを追加）成功。Migration変更なし。

残る仕上げ: ブランドアイコンの見た目、操作の密度/明暗別コントラスト、認証済みaccount・管理画面全体の実表示、各browser。完成デザイン・Version1.0完成にはしない。Phase9のweather/upload停止・復旧検証など元の機能残件も継続する。

2026-10-04追加: 検索アイコン/矢印、補助設定のFavoriteカテゴリ集約、下線tab、半透明tileとhover/focus menuを反映。JA/ENとcard/filter動作、mobile390/scroll375、Console0を確認。最新画像はglass-refined-desktop-ja.pngとglass-refined-mobile-ja.png。ブランドアイコンなど残る仕上げは未完。
