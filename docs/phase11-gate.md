# Phase 11 ゲート監査

判定: **実装・限定検証済みの項目あり、実Chromeロード待ち。Phase11未完了、Phase12へ進めない。**

根拠はspec.md §110〜114、Phase11のManifest/New Tab/Shared UI/Sync/Offline Mode、および現在のコードと検証記録。Nodeのextension context/Cookie jarをChromeの実Cookie成功として扱わない。

| 条件 | 現在の証拠 | 必須の残る確認 |
|---|---|---|
| Manifest / New Tab | ExtensionPackageBuilderのMV3/newtab宣言、static日英生成、PHP両版各3回190/基盤40 | 実Chrome読込・新しいタブ置換・CSP/Console |
| Shared UI / Settings | Webのhome/favoritesと同一JS/CSS、全設定modal、server namespace分離、locale routing | 拡張での設定操作、Webとの往復共有・日英 |
| Auth / Cloud Sync | 既存Auth/CSRF/ownerを維持、両DB実HTTP19各3回、生成通常user・Web1巡 | Chrome host permission/SameSite/Cookie、実拡張での同期・競合・logout |
| Cloud Background | 同じbackground API/session、実multipart画像取得/byte一致/別owner拒否 | 実拡張のcloud背景、地域・条件・動画制御 |
| Offline Search | 同じproviders/search/core、local候補、外部suggest抑制 | 拡張offlineで入力/履歴候補/キー操作 |
| Offline Favorites / History | 同じIndexedDB store/CRUD/history、保存失敗/原子性の既存単体 | offline編集・再読込保持・復帰同期 |
| Offline Theme / Background | 共通appearance、保存設定維持/font fallback、Blob背景のWeb実保持、URL端末copyの型/size/CAS/匿名取得単体 | 実拡張offline描画・切替・video、optional host許可/拒否/解除 |
| Offline Palette / Shortcuts | 共通registry/action/検索・設定・テーマ等、JS回帰 | offline実操作・キー競合・復帰後の操作 |
| Offline Clock / Greeting | 同じappearance表示、端末の日時/設定で表示 | 実拡張offlineの時計更新・挨拶・再読込 |
| Online Recovery | online eventの共通Sync/BackgroundSync、無送信→復帰writeの実HTTPモデル | 実Chrome offline/onlineで未送信編集・所有権・ACK・競合 |
| Startup Guide | 日英のChrome起動設定手順、New Tabとstartup区別 | 拡張画面の表示・リンク・translation確認 |

最新証拠: docs/chrome-extension.md / progress.md / docs/phase-status.md。JS41suite各3回、PHP生成190/40両版各3回、実HTTP19両DB各3回（Cookie/extension contextはNode模擬）。Webのsame-origin端末copy保存/再読込は実ChromeのWebページであり、拡張としての確認ではない。全部の未確認を消さず、Version1.0最終監査へ残す。

Chrome内部管理URLへのナビゲーションはBrowser UseのURL policyで拒否された範囲なので、CLI/CDP/native等の別経路で迂回しない。旧candidate `.test-output/extension-mysql-20261006` の手動Load質問は未回答。新media candidateは別folderでoptional host宣言を追加しており、旧質問を新しいgrantとして扱わない。ロード完了後、表示された対象tab/URLから検証する。

検証専用4containers/networkは現在維持。通常環境/本番/config/Secret/specは変更しない。実検証終了後にcleanup-extension-http.ps1で所有marker/network/tmpfsを確認して清掃し、その結果も記録する。
