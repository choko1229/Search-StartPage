# Phase 7 機能ゲート監査

2026-10-03。Phase 7の機能ゲート判定は末尾の完了条件表を参照。実Discord成功はユーザー指定の開発前提とし、実OAuthの未確認を成功扱いにしない。Version 1.0全体は未完成。

| 仕様 | 実装・証拠 | 状態 |
|---|---|---|
| Solid/Gradient/Image/Video (§57) | background-core/background.js、プリセットと保存済みライブラリ、実画像/動画描画 | 機能確認済み |
| Blur/Brightness/Overlay/Color/Position/Scale/Fit/Fixed (§58) | editor/core/PHP Validation、単体41、編集・再読込・390px実画面 | 機能確認済み |
| Auto Play/Loop/Mute/Speed/Pause/Mobile Fallback (§58) | playback、実動画Play/Pause/再開、Auto Play OFF再読込、Loop OFF終端、英語ボタン、390px代替画像 | 機能確認済み、実非表示復帰は最終監査へ留保 |
| URL/Local Upload/Preset/複数保存 (§59) | ファイル入力→検査→Blob/metadata原子保存、ライブラリ編集/保管/復元。IDBと実画像UI、実動画HTTP | 実filechooser→動画Blob保存→再生→reload→ファイル再選択なし編集を確認 |
| 背景単位Cloud Sync・URL/metadataとfile (§60) | owner限定APIと背景session/3-way/CAS/初回3択。実HTTP2端末各8グループ、原子的ACK、durable upload intentとreceipt回復 | 機能確認済み、認証済み実UIは留保 |
| 画像25MiB/動画500MiB・最終size課金 (§61) | 検査/JS/サーバー上限、実500MiB multipart各12、圧縮quota各8、圧縮付きHTTP各15 | 入力境界/実圧縮/課金確認済み、巨大入力のFFmpeg変換と実ブラウザquota枯渇は未確認 |
| Imagick→GD/FFmpeg・不足時継続 (§62) | 実codec 17/16/12項目、失敗/codecなしwarningと元file保持、API capability | 機能確認済み、Admin警告画面はPhase 9 |
| 非公開owner保存・DB metadata (§63/121) | canonical random filename、0600/0700、MIME/拡張子/実byte検査、Auth限定Range/ETag download、API55 | 確認済み |
| Manual/Random/Time/Schedule/Weather (§64) | manual/random/rules選択、time/period/day等の複合条件、10秒再判定、条件の具体性 | 機能確認済み |
| 全11条件、AND/OR、具体性優先・同率random (§65/66) | editor/core/PHP検証、depth/nodes/32KiB制限、入れ子保存・取消・再編集・JA/EN/390px実UI | 確認済み |
| Login Stateの経時変化 (§65) | syncUserの確定結果通知、背景が追従、表示中60秒確認/表示復帰、エラー時旧確認値保持。専用単体と同期回帰 | 修正済み、実認証済みUIは留保 |
| Weather・気温、Geolocation/手動地域・共有 (§67) | 公開都市の実provider→製品画面の色切替、地域UI原子的保存/失敗/遅延拒否、地域設定の同期投影 | 機能確認済み、実OS位置許可/認証済み地域UI同期は留保 |
| 競合ルール共有 (§87) | general settings.syncRules、owner照合、旧ルール昇格・削除後非復活、通信中保存保持、実2端末適用 | 機能確認済み |
| 中断/削除失敗の孤立回収 | CLI dry-run/apply、24h grace、DB参照保持、uploadと共通lock、実別プロセス停止後回復、両DB各15 | 確認済み、定期実行は運用ホストで設定 |
| Installer/Migration/前Phase Regression | 新規8089/8090で全9 up/0/down/Installer up/0各40、weather初期値、実weather各14、PHP114/基盤39、全JS27、同期HTTP各8 | 確認済み |
| 初回Wizardの背景選択 (§82、Phase 6からの持越し) | Theme/Solid/library、Presetと有効な保存背景の選択。JAで森→保存→Back/Skip保持、保存済み画像→再読込再開、ENで動画選択とmobile代替。390px横はみ出しなし、全JS27/オンボーディング26単体 | 確認済み |

仕様は永久削除ボタンを背景の必須操作として明記していない。保管は復元可能でfile/容量を保持する。孤立回収は保管背景のfileを消す代替操作にしない。

生成MP4の実ブラウザfile選択からIndexedDB保存・再読込まで確認済み。次は添付Phase別完了条件を照合し、Phase 7の実装と検証範囲を確定する。環境依存の未確認事項はPhase 12監査で追跡し、主要未処理を残したままPhase 7を完了扱いにしない。

## 添付Phase7の追加照合

添付Library要件のthumbnail/favorite/sortは2026-10-03監査で不足を発見し補修。色・gradient・実画像/動画（autoplayなし）のサムネイル、背景favorite boolean、保存順/名前/お気に入り優先を実装。DB既存settings_json、background sync appearanceへfavoriteを追加。両DBAPI56/実2端末8group（favorite登録・解除の往復を追加）と全JS28、新library8、JA/EN/reload/390px実画面を確認。並び順はUndo/resetカテゴリ対象。既存の実OAuth等の留保は継続。添付完了条件9項目の最終判定を次回行う。

## Phase7完了条件の判定（2026-10-03）

| 添付完了条件 | 根拠 | 判定 |
|---|---|---|
| image upload | 実file入力/Blob保存/再読込、両DB multipart・MIME・private download/API56 | 機能確認済み |
| video upload | 実filechooser MP4→Blob→readyState4/再生/reload/編集、500MiB実HTTP両DB12 | 機能確認済み |
| compression | 実Imagick/GD/FFmpegと失敗fallback、両DB実圧縮HTTP15/最終容量quota8 | 機能確認済み |
| background library | 複数背景/保管復元/サムネイル/favorite/sort、JA/EN/再読込/390px、新library8 | 機能確認済み |
| cloud sync | 製品session実HTTP2端末各8、URL/file/選択共有/owner/receipt回復/お気に入り登録解除 | 機能確認済み、実OAuth前提の実UIは留保 |
| rules | 全11条件、入れ子AND/OR編集/保存/再編集、JS/PHP制限 | 機能確認済み |
| weather | 公開都市の実providerと製品条件切替、両DBweather HTTP14、手動/ブラウザ地域入力 | 機能確認済み、OS位置許可は留保 |
| priority | background-core.testのAND条件数・OR具体性・同率random、実条件編集 | 機能確認済み |
| fallback behavior | 実動画mobile代替、codecなし原本保存warning、provider失敗/オフライン再試行、地域遅延応答破棄 | 機能確認済み |

Phase7の機能ゲートは検証済みと判定し、Phase8へ進む。直近3種類の検証はライブラリ補修記録に記載（全JS28とPHP/翻訳基盤39、両DB API56/実2端末8、JA/EN/mobile実UI）。DB変更は全9Migrationの既存Installer往復40で確認済み、補修では追加Migration不要。

これはVersion1.0全DoDの合格判定ではない。実OAuth/認証済み実UI、実OS位置許可、実非表示動画復帰、500MiBのFFmpeg変換、ブラウザ実quota枯渇は未確認でPhase12に追跡する。codec不足の管理画面警告は仕様のPhase9で実装する。モデル試験で実ブラウザや外部サービス成功を推定しない。
