# SPEC準拠監査

最優先仕様: ルートspec.md（WindowsではSPEC.mdと同一ファイル）。2026-09-29に全体構造と仕様を再確認。

## Phaseゲート

| Phase | 要件と根拠 | 現在の証拠・不足 | 判定 |
|---|---|---|---|
| 1 | Router/DB/Config/Installer/View/API/Session/Error/Language、§101–103 | 両DB Installer/Migration/単体とブラウザ実績あり。後続変更も回帰が必要 | 基盤検証済み |
| 2 | Search §11–33 | 検索/Prefix/AI/履歴は実装。§21/25/26/29/30/32/33を補修しJS・両DB・ブラウザ検証済み。同期はPhase 5、Palette接続はPhase 8で検証 | Phase 2補修済み |
| 3 | Favorites §34–47 | CRUD/タグ/フォルダ/Drag検証済み。§36件数/幅Auto、§37配置/寸法/件数/列数、§38自動件数/展開保持を補修し3種類検証済み。Phase 6設定接続は後続で検証 | Phase 3補修済み |
| 4 | Auth §7–10/89/97/117 | 認証42/HTTP12/OAuth応答11/制限7と同時24プロセス・HTTP制限・両DB停止を検証。同期所有権接続済み。実Discord/認証済みブラウザは未確認でユーザー指定により留保 | 実認証の未確認を留保 |
| 5 | Sync §31/46/60/84–88/117 | 版管理/原子的DB投影/JS・PHP項目マージ/初回3択/競合UI/適応同期/各CRUD・POST同期/競合APIを実装。新規両DBInstaller35/API50/同期17/投影31検証。実HTTPの2端末エンジン、履歴ON切替/物理期限削除、オフライン復帰の統合、背景設定の後続接続が残る | 実装・検証中 |
| 6 | Appearance §37/53–56/68–82 | 設定基盤、Undo20、テーマ/日の出/フォント/時計/挨拶/オンボーディングが必要 | 未着手 |
| 7 | Background §57–67/120–121 | Upload/圧縮/容量/ライブラリ/条件AND OR/優先順位/天気/地域同期が必要 | 未着手 |
| 8 | Palette §48–52 | Registry/全カテゴリ/ランキング/確認・確認省略設定が必要 | 未着手 |
| 9 | Admin §90–100/118 | 管理権限、各管理機能、統計、90日ログ、監査、メンテが必要 | 未着手 |
| 10 | Update §104–109 | GitHub/チャンネル/24h確認/検証/バックアップ/安全置換/DB/失敗復旧・手動Rollbackが必要 | 未着手 |
| 11 | Extension §110–114 | New Tab、共有UI/設定、認証同期、列挙されたOffline機能、復帰同期が必要 | 未着手 |
| 12 | Quality §6/83/120–128 | 4ブラウザ、Mobile、a11y、セキュリティ、翻訳、性能、全回帰が必要 | 未着手 |

## 最終DoDの証拠方針

§128のCore/Search/Favorites/Auth/Sync/Appearance/Background/Admin/Update/Extension/Qualityを各項目単位で確認する。現在Version 1.0は不合格。単体テストのみでUIや外部サービス成功を推定しない。最終監査は各機能のHTTP/DB/実ブラウザ、両DB Migration/Installer、更新失敗復旧と保護ファイル比較、Extensionの実ロード/Offline復帰の証拠を必要とする。

## リスク

- 既存完了記録は添付Phase要約の確認が中心で、spec.mdの細部を満たしていなかった。以後本監査の不足を解消する。
- 認証DB接続を遅延化済み。両DBの実停止でゲスト画面200・認証API503を確認。
- Sync所有権マニフェストは同期ACK後に書き込む構成へ接続。認証済みブラウザ・実端末の統合とIndexedDB背景削除は未確認/未実装として追跡。
- Firefox/Safariの実環境は未確認。存在しない環境の成功を推定しない。
