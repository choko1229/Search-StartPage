# Phase 11 ゲート監査

判定: **実装・限定検証済みの項目あり、ユーザーのロード完了通知を受領、NewTab表示はユーザー確認済み、実拡張ログイン・同期・Offline確認待ち。Phase11未完了、Phase12へ進めない。**

根拠はspec.md §110〜114、Phase11のManifest/New Tab/Shared UI/Sync/Offline Mode、および現在のコードと検証記録。Nodeのextension context/Cookie jarをChromeの実Cookie成功として扱わない。

| 条件 | 現在の証拠 | 必須の残る確認 |
|---|---|---|
| Manifest / New Tab | ExtensionPackageBuilderのMV3/newtab宣言、static日英生成、PHP両版各3回190/基盤40 | 読込・NewTab表示はユーザー確認済み。実拡張のCSP/Consoleは未確認 |
| Shared UI / Settings | Webのhome/favoritesと同一JS/CSS、全設定modal、server namespace分離、locale routing | 拡張での設定操作、Webとの往復共有・日英 |
| Auth / Cloud Sync | 既存Auth/CSRF/ownerを維持、両DB実HTTP19各3回、生成通常user・Web1巡、twesの拡張→Web一例をユーザー追加元回答＋Web実DOMで確認 | Chrome host permission/SameSite/Cookie、実拡張での同期・競合・logout |
| Cloud Background | 同じbackground API/session、実multipart画像取得/byte一致/別owner拒否 | 実拡張のcloud背景、地域・条件・動画制御 |
| Offline Search | 同じproviders/search/core、local候補、外部suggest抑制 | 拡張offlineで入力/履歴候補/キー操作 |
| Offline Favorites / History | 同じIndexedDB store/CRUD/history、保存失敗/原子性の既存単体 | offline編集・再読込保持・復帰同期 |
| Offline Theme / Background | 共通appearance、保存設定維持/font fallback、Blob背景のWeb実保持、URL端末copyの型/size/CAS/匿名取得単体 | 実拡張offline描画・切替・video、optional host許可/拒否/解除 |
| Offline Palette / Shortcuts | 共通registry/action/検索・設定・テーマ等、JS回帰 | offline実操作・キー競合・復帰後の操作 |
| Offline Clock / Greeting | 同じappearance表示、端末の日時/設定で表示 | 実拡張offlineの時計更新・挨拶・再読込 |
| Online Recovery | online eventの共通Sync/BackgroundSync、無送信→復帰writeの実HTTPモデル | 実Chrome offline/onlineで未送信編集・所有権・ACK・競合 |
| Startup Guide | 日英のChrome起動設定手順、New Tabとstartup区別 | 拡張画面の表示・リンク・translation確認 |

最新証拠: docs/chrome-extension.md / progress.md / docs/phase-status.md。JS41suite各3回、PHP生成190/40両版各3回、実HTTP19両DB各3回（Cookie/extension contextはNode模擬）。Webのsame-origin端末copy保存/再読込は実ChromeのWebページであり、拡張としての確認ではない。全部の未確認を消さず、Version1.0最終監査へ残す。

Chrome内部管理URLへのナビゲーションはBrowser UseのURL policyで拒否された範囲なので、CLI/CDP/native等の別経路で迂回しない。2026-10-07に旧candidate `.test-output/extension-mysql-20261006` のロード完了通知を受領。操作可能なChrome一覧には対象tabがなく、ユーザーへ新しいタブを開いて表示を知らせる確認を提示。新media candidateは別folderでoptional host宣言を追加しており、旧質問を新しいgrantとして扱わない。ロード完了後、表示された対象tab/URLから検証する。

検証専用4containers/networkは現在維持。通常環境/本番/config/Secret/specは変更しない。実検証終了後にcleanup-extension-http.ps1で所有marker/network/tmpfsを確認して清掃し、その結果も記録する。

2026-10-07: 手動検証手順は [phase11-manual-check.md](phase11-manual-check.md)。結果欄は未実行。ユーザーのNewTab表示確認とWebプロフィール成功を、実拡張の認証/同期/Offline完了へ広げない。

2026-10-08補修: 初回案内のaccountUrl、共通ヘッダーdata-header-item、認証eventで名前/ヘッダー更新、初回案内close後のsync dialog表示。JS41suite各3回、Webヘッダー3回、Web認証切替の限定確認、Web初回案内/同期表示順序3回を確認。実拡張/Offline合格へ拡大しない。重複modal修正de298c0以降はGitHub送信がserver errorで失敗し、remote main45553acのまま。実拡張接続または手動結果待ちでblocked。

2026-10-08送信復旧: main pushが成功し72d5a74まで反映。GitHub server errorの送信ブロックは解消。実拡張の操作接続/手動検証結果待ちは継続。

2026-10-08ユーザー回答: twesはChromeの＋で開いた新しいタブから追加。Web実表示と合わせ拡張→Webのお気に入り同期一例を確認。逆向き/3round/全設定/Offlineを合格扱いしない。逆向きの前に専用生成ユーザーの初回データ選択を具体化し、端末データ採用の実行直前確認を提示。

2026-10-08逆方向準備: 許可済みの専用primary端末データ採用後、Web実UIでtwes→twes-webへ名前変更し、今すぐ同期/同期しましたを確認。拡張受信回答待ち。背景の初回選択は別途あとでとし置換・uploadなし。

2026-10-08受信回答: 拡張でtwes-webへ変更されていたとユーザー確認。お気に入りの拡張→Web→拡張1回目を限定確認。Round2/3、全設定/背景/Offline/両DBは未確認。非公開情報を含まない実H264動画素材を生成し、保存/再生の実UIはまだ未完。
