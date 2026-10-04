# 管理画面からの更新・復元

`/admin/update` でリリースを確認し、「更新を適用」または「直前の版へ復元」を選ぶ。確認画面には処理内容と対象バージョンを表示し、取消へ初期フォーカスを置く。取消とEscでは受付しない。「確定して続ける」で既存Web POSTへCSRFと3つのrevisionを送り、サーバーが管理権限と候補/世代を再確認して303で画面へ戻す。対象版・actor・取得URLは本文に含めない。

操作はJavaScriptとdialog対応が必要。HTMLの操作ボタン自体はtype=buttonであり、JavaScriptが無いと直接POSTしない。二重確定を抑止し、文言・版番号はtextContentで表示する。保存状態の再確認は明示的なページ再取得。自動リロードで入力中のチャンネル/タグを破棄しない。

GETのexecutionにworker_readyを追加。固定配置のroot/app/publicが書込み可能で、privateな32桁instance IDの実行worker lockが実際に保持され、停止markerが無いときだけtrueとする。lockファイルの存在だけで稼働扱いにしない。worker停止/readonly/処理中/候補なし/保存世代なしでは関連ボタンを無効化する。これは準備状態の表示であり、実行時のEngineによる全ファイル権限/DB/backup検証の代替ではない。API/CLI経由の受付契約は保持し、UIの無効化だけでサーバー権限を保証しない。

検証: 一時ファイルの受付/準備状態28項目が両環境で成功。専用MySQL tmpfs DB、localhost限定8107の使い捨てApache、生成管理者/remember device、常駐実workerで、ブラウザ確認→取消→EN/390px確認→確定→実適用/新version→復元確認/Esc→確定→実復元/両履歴を確認した。日本語表示も保存/再取得して確認。Console warn/error0、390pxのpage375/dialog358で横はみ出しなし。workerの安全な停止後にUIボタンが無効になることも確認した。取得候補/archiveだけfixtureで、実Discord/GitHubの証明ではない。

追加の管理HTTP56項目（guest/user/管理権限失効/CSRF/Validation/日英/履歴escape/監査）とJS構文、両基盤40、最終配布物232files/3493376bytes/PHP160/独立tar/hash/config保持/private除外が成功。生成管理者・入口・configは専用app/DBコンテナごと除去し、通常8099/8100のDB/権限/workerを保持した。実ブラウザ証拠はMySQL/IABに限定し、MariaDB実ブラウザ/FPM/全browser/長時間運用へ拡張しない。

## MariaDBの実管理画面（2026-10-04）

専用tmpfs MariaDB10.11/loopback8108/生成管理者/実workerを使用。setup87781 exit0でHTTP23/基盤40を各3回成功。IAB日英で更新確認取消→EN確定→実2.0.0→復元Esc取消→確定→旧0.1.0-dev、JA再取得を確認。取消時は版/履歴が変わらず、最終config hash一致、job rolled_back、候補templateなし。履歴はrolled_back/complete/failedの3件。worker安全停止後は両ボタン無効、390px/page375/dialog358、Console warn/error0。画像.test-output/update-mariadb-restored-ja.pngを目視確認。

初期observerをroot実行してprivate lock所有者を誤りgeneric error、生成archive所有者の誤りで初回INVALID_UPDATE_PACKAGE。専用live/root storageをwww-dataへ修正し、observerもwww-data実行へ変更後に成功。失敗履歴も保持。準備harnessに修正済み、PHP構文/PowerShell parser確認。修正版harness全体3回は未実行、実UI一巡/再試行を3回のUI成功としない。

worker停止/viewport reset/新規tab close、専用appとtmpfs一致確認DBを削除し生成user/入口/configを清掃、prefix空。通常環境非変更。候補と取得だけfixtureであり、実GitHub/OAuth/全browser/本番配置の証明ではない。先行のMySQL限定記録に対し、今回MariaDBの限定実画面範囲を追加した。
