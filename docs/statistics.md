# 匿名統計の収集

014_statisticsでstatistics_eventsを追加。アカウント・Discord ID・IP・URL・検索語との対応表や外部キーは持たない。端末のランダムIDとイベントのランダムID、許可した操作種別・分類、Web/Extension区分、日時だけを保存する。既定検索先のID以外はcustomへ分類し、独自名を送らない。statisticsの端末状態はクラウド同期文書へ含めない。

POST /api/statistics/eventはログイン不要、CSRF必須。JSONはevents配列（1〜100件）、各行はevent_id/anonymous_id（32桁hex）、event_type、event_data（object）、source、created_at（Unix秒）。visit/favorite_openのdataは空、search/ai_searchは許可したprovider分類だけ、featureはcommand_palette/background/settings/favorites/syncだけ。余分なフィールド・直接識別子・任意文字列は422。全件を検証後に同一transactionで保存し、anonymous_id/event_idの組合せで再送を重複排除する。時刻はオフライン時の発生日時を保持し、未来は5分まで。保存期限は設けず90日ログ整理から分離する。

製品の訪問、検索実行、AI実行、favorite open、設定を開く、背景保存、成功したPalette操作、成功した同期へ接続。favoriteStatsは個人用の並び替え頻度だけの設定で、匿名イベントをOFFにしない。端末の原子的保存へqueueを追加し、20件ずつ送信する。送信失敗・応答喪失・ACK保存失敗はqueueを保持し再試行。別タブとの更新は既存storeの条件付き書込と競合後再読込で処理する。統計の保存故障は検索や端末編集を停止させない。この場合の記録成功は保証できない。ブラウザのデータ消去・スクリプト/通信遮断による計測欠落もあり、実利用人数と端末統計ID数は同一ではない。

プライバシーポリシーは /privacy、全通常画面のfooterからアクセスできる。必要Cookie・ログインCookie・匿名統計・直接アカウント情報・外部検索/候補/天気/metadata・運用ログについてJA/ENで説明する。Cookieバナーや統計OFF設定は追加しない。Maintenance全面停止時はfooterを含め通常ページを隠す。

現在の証拠: 両DBの実HTTP/API各30、migration up/再実行、秘密field拒否、再送dedup、一括validation、90日整理からの分離。Nodeのqueue保存/再送/ACK失敗/再読込/20件batch/応答中編集/秘密非露出/同期対象外を確認。実Webの訪問2件＋設定操作1件を同じ匿名端末IDとしてDBで件数確認、実IDは出力しない。実JA/EN privacy、EN390px横溢れなし、Console0。これらは全統計機能の完成を意味しない。

未完了: 管理者の集計API/UI・期間指定・グラフ・DAU/WAU/MAU/Retentionなど全指標、Extensionの実送信、全14Migrationの新規Installer往復、実ブラウザで検索/favorite/background/Palette/同期イベントと故障回復の網羅。原子的queueの別タブ競合は製品storeの既存機構を使うが、統計専用の実複数タブ試験は未実施。
