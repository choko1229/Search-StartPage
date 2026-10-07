# SPEC準拠監査

最優先仕様: ルートspec.md（WindowsではSPEC.mdと同一ファイル）。2026-09-29に全体構造と仕様を再確認。

## Phaseゲート

| Phase | 要件と根拠 | 現在の証拠・不足 | 判定 |
|---|---|---|---|
| 1 | Router/DB/Config/Installer/View/API/Session/Error/Language、§101–103 | 両DB Installer/Migration/単体とブラウザ実績あり。後続変更も回帰が必要 | 基盤検証済み |
| 2 | Search §11–33 | 検索/Prefix/AI/履歴は実装。§21/25/26/29/30/32/33を補修しJS・両DB・ブラウザ検証済み。同期はPhase 5、Palette接続はPhase 8で検証 | Phase 2補修済み |
| 3 | Favorites §34–47 | CRUD/タグ/フォルダ/Drag検証済み。§36件数/幅Auto、§37配置/寸法/件数/列数、§38自動件数/展開保持を補修し3種類検証済み。Phase 6設定接続は後続で検証 | Phase 3補修済み |
| 4 | Auth §7–10/89/97/117 | 認証42/HTTP12/OAuth応答11/制限7と同時24プロセス・HTTP制限・両DB停止を検証。同期所有権接続済み。実Discord/認証済みブラウザは未確認でユーザー指定により留保 | 実認証の未確認を留保 |
| 5 | Sync §31/46/60/84–88/117 | 版管理/原子的DB投影/JS・PHP項目マージ/初回3択/競合UI/適応同期/各CRUD・POST同期/競合APIを実装。新規両DBInstaller35、API52/同期17/投影34/期限削除9を検証。実HTTP2端末・履歴ON切替/削除・オフライン復帰・競合ルール共有も両DB検証済み。長文履歴14.4MBの保存/読込/競合解決を両DB実HTTPで確認。IndexedDBへ旧データを移行し長文300件+checkpoint/再読込/失敗時ACK保持/保存中編集/別タブ/所有権削除を検証。背景設定はPhase 7へ接続 | 同期機能ゲート検証済み（実OAuth留保） |
| 6 | Appearance §37/53–56/68–82 | カテゴリmodal/Undo20/reset、テーマ/複数Custom/太陽時近似/フォント/Animation、検索欄Glass、時計/日付/挨拶基本表示を実装。JS appearance37/history19と既存回帰、両DB構文84/基盤39/sync17、ブラウザreset/Undo/Close/resize/mobile、Dark/Custom/font実読込、表示の保存、検索欄固定幅/高さ/透過/ぼかしを確認。時計/日付style・ヘッダーも実装し日英/再読込/390pxで確認。Google Font読込・架空地域の昼夜も確認。初回ウィザード18単体と日英/Skip/Back/Later/再開/完了/390px実UIも確認。docs/phase6-gate.mdで照合、実OS motion切替は最終監査へ留保 | 機能ゲート検証済み（実OAuth留保） |
| 7 | Background §57–67/120–121 | 全11条件/複合編集・実天気/地域UI・動画手動操作・単位背景同期/原子的Blob・中断receipt回復・競合ルール共有・孤立清掃を実装。両DB API55/2端末8group/500MiB実HTTP12/実圧縮quota8/圧縮HTTP15/孤立回収15。新規全9Migration/Installer各40、全PHP114/JS27。初回案内Preset/libraryも日英/mobile実UI確認。docs/phase7-gate.mdで証拠と留保を追跡 | 機能ゲート検証済み（未確認はPhase12で追跡） |
| 8 | Palette §48–52 | 全8カテゴリ/5初期groups/ランキング/実操作/操作別確認と同期/reset/拡張登録/原子削除/Logout回復を実装。JS31、両DB認証43/Logout実HTTP6/確認同期4、日英/mobile/履歴再検索/拡張UI/専用実IndexedDB失敗回復を検証。docs/phase8-gate.mdで7完了条件を照合 | 機能ゲート検証済み（実OAuth等はPhase12へ留保） |
| 9 | Admin §90–100/118 | 添付の完了条件10項目をdocs/phase9-gate.mdで照合。権限実付与/解除、容量、匿名統計、DB/fileログ/監査、停止、flags/limits、presetsの両DB/API/日英UI証拠あり。独立2originの同期停止/復帰も確認。実OAuth/全browser等の未確認は保持 | 機能ゲート検証済み（未確認は後続Phaseで追跡） |
| 10 | Update §104–109 | Phase9中の管理接続でチェック/4channel/配布物検査/backup/file+DB更新/Migration/自動・手動Rollback/history/UIを実装・先行検証済み。実公開88554ee/v0.1.2-devの両DB両PHP各3回487項目で取得/適用/復元と保持を検証。実Actions両PHP成功、既存FPM/自動復元/実managerも確認。docs/phase10-gate.md参照 | 機能ゲート検証済み（本番配置等は未確認） |
| 11 | Extension §110–114 | Web home/favorites/JS/CSS/背景/日英の同一資産からMV3 static packageを生成。両PHP各3回190/基盤40、JS41suite各3回（Offline表示/locale/URL端末コピー）。共通API輸送・Offline通信抑制/復帰・server別保存・public presets共有を実装し単体検証。実HTTP19項目を両DB各3回成功（extension context/CookieはNode模擬）、Web1巡確認。実Chrome認証同期/Offline操作・復帰/実ロードは未確認 | 進行中 |
| 12 | Quality §6/83/120–128 | 4ブラウザ、Mobile、a11y、セキュリティ、翻訳、性能、全回帰が必要 | 未着手 |

## 最終DoDの証拠方針

§128のCore/Search/Favorites/Auth/Sync/Appearance/Background/Admin/Update/Extension/Qualityを各項目単位で確認する。現在Version 1.0は不合格。単体テストのみでUIや外部サービス成功を推定しない。最終監査は各機能のHTTP/DB/実ブラウザ、両DB Migration/Installer、更新失敗復旧と保護ファイル比較、Extensionの実ロード/Offline復帰の証拠を必要とする。

## リスク

- 既存完了記録は添付Phase要約の確認が中心で、spec.mdの細部を満たしていなかった。以後本監査の不足を解消する。
- 認証DB接続を遅延化済み。両DBの実停止でゲスト画面200・認証API503を確認。
- Sync所有権マニフェストは同期ACK後に書き込む構成へ接続。認証済みブラウザ・実端末の統合とIndexedDB背景削除は未確認/未実装として追跡。
- Firefox/Safariの実環境は未確認。存在しない環境の成功を推定しない。
