# 管理画面からの更新・復元

`/admin/update` でリリースを確認し、「更新を適用」または「直前の版へ復元」を選ぶ。確認画面には処理内容と対象バージョンを表示し、取消へ初期フォーカスを置く。取消とEscでは受付しない。「確定して続ける」で既存Web POSTへCSRFと3つのrevisionを送り、サーバーが管理権限と候補/世代を再確認して303で画面へ戻す。対象版・actor・取得URLは本文に含めない。

操作はJavaScriptとdialog対応が必要。HTMLの操作ボタン自体はtype=buttonであり、JavaScriptが無いと直接POSTしない。二重確定を抑止し、文言・版番号はtextContentで表示する。保存状態の再確認は明示的なページ再取得。自動リロードで入力中のチャンネル/タグを破棄しない。

GETのexecutionにworker_readyを追加。固定配置のroot/app/publicが書込み可能で、privateな32桁instance IDの実行worker lockが実際に保持され、停止markerが無いときだけtrueとする。lockファイルの存在だけで稼働扱いにしない。worker停止/readonly/処理中/候補なし/保存世代なしでは関連ボタンを無効化する。これは準備状態の表示であり、実行時のEngineによる全ファイル権限/DB/backup検証の代替ではない。API/CLI経由の受付契約は保持し、UIの無効化だけでサーバー権限を保証しない。

検証: 一時ファイルの受付/準備状態28項目が両環境で成功。専用MySQL tmpfs DB、localhost限定8107の使い捨てApache、生成管理者/remember device、常駐実workerで、ブラウザ確認→取消→EN/390px確認→確定→実適用/新version→復元確認/Esc→確定→実復元/両履歴を確認した。日本語表示も保存/再取得して確認。Console warn/error0、390pxのpage375/dialog358で横はみ出しなし。workerの安全な停止後にUIボタンが無効になることも確認した。取得候補/archiveだけfixtureで、実Discord/GitHubの証明ではない。

追加の管理HTTP56項目（guest/user/管理権限失効/CSRF/Validation/日英/履歴escape/監査）とJS構文、両基盤40、最終配布物232files/3493376bytes/PHP160/独立tar/hash/config保持/private除外が成功。生成管理者・入口・configは専用app/DBコンテナごと除去し、通常8099/8100のDB/権限/workerを保持した。実ブラウザ証拠はMySQL/IABに限定し、MariaDB実ブラウザ/FPM/全browser/長時間運用へ拡張しない。
