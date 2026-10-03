# 匿名統計の収集

014_statisticsでstatistics_eventsを追加。アカウント・Discord ID・IP・URL・検索語との対応表や外部キーは持たない。端末のランダムIDとイベントのランダムID、許可した操作種別・分類、Web/Extension区分、日時だけを保存する。既定検索先のID以外はcustomへ分類し、独自名を送らない。statisticsの端末状態はクラウド同期文書へ含めない。

POST /api/statistics/eventはログイン不要、CSRF必須。JSONはevents配列（1〜100件）、各行はevent_id/anonymous_id（32桁hex）、event_type、event_data（object）、source、created_at（Unix秒）。visit/favorite_openのdataは空、search/ai_searchは許可したprovider分類だけ、featureはcommand_palette/background/settings/favorites/syncだけ。余分なフィールド・直接識別子・任意文字列は422。全件を検証後に同一transactionで保存し、anonymous_id/event_idの組合せで再送を重複排除する。時刻はオフライン時の発生日時を保持し、未来は5分まで。保存期限は設けず90日ログ整理から分離する。

製品の訪問、検索実行、AI実行、favorite open、設定を開く、背景保存、成功したPalette操作、成功した同期へ接続。favoriteStatsは個人用の並び替え頻度だけの設定で、匿名イベントをOFFにしない。端末の原子的保存へqueueを追加し、20件ずつ送信する。送信失敗・応答喪失・ACK保存失敗はqueueを保持し再試行。別タブとの更新は既存storeの条件付き書込と競合後再読込で処理する。統計の保存故障は検索や端末編集を停止させない。この場合の記録成功は保証できない。ブラウザのデータ消去・スクリプト/通信遮断による計測欠落もあり、実利用人数と端末統計ID数は同一ではない。

プライバシーポリシーは /privacy、全通常画面のfooterからアクセスできる。必要Cookie・ログインCookie・匿名統計・直接アカウント情報・外部検索/候補/天気/metadata・運用ログについてJA/ENで説明する。Cookieバナーや統計OFF設定は追加しない。Maintenance全面停止時はfooterを含め通常ページを隠す。

現在の証拠: 両DBの実HTTP/API各30、migration up/再実行、秘密field拒否、再送dedup、一括validation、90日整理からの分離。Nodeのqueue保存/再送/ACK失敗/再読込/20件batch/応答中編集/秘密非露出/同期対象外を確認。実Webの訪問2件＋設定操作1件を同じ匿名端末IDとしてDBで件数確認、実IDは出力しない。実JA/EN privacy、EN390px横溢れなし、Console0。これらは全統計機能の完成を意味しない。

未確認・残作業: Extensionの実送信、全14Migrationの新規Installer往復、実ブラウザで検索/favorite/background/Palette/同期イベントと故障回復の網羅。原子的queueの別タブ競合は製品storeの既存機構を使うが、統計専用の実複数タブ試験は未実施。
# 管理集計（Phase9）

`GET /admin/statistics` と `GET /api/admin/statistics` は通常のサーバー管理者権限を必須とする。`start=YYYY-MM-DD&end=YYYY-MM-DD` はUTCの両端を含み、既定は今日まで30日、最大366日。未来日・存在しない日・逆順・配列値は422。集計は読み取りのみでCSRF付き変更操作はない。

登録アカウント総数、期限内login_tokensを持つ異なるアカウント数、非削除背景のfile_size合計は現在値。新規アカウントはusers.created_atが選択期間にある件数。匿名イベントとアカウントは結合しない。

検索・AI検索・favorite_open、各provider/feature/sourceの件数は選択期間の実イベント。Web/Extension比率はイベント件数の比率で、端末・アカウント比率ではない。DAU/WAU/MAUは終了日まで1/7/30日間に任意イベントのあった異なる匿名ID数。新規匿名端末は全保存イベント中の最初の日が選択期間に含まれる端末数。端末データ消去・別ブラウザは別IDになる。

D7 Retentionは選択期間に初利用した匿名端末のうち、UTCの初利用日+7日目にもイベントがある割合。7日目の全日が期間終了日かつ今日までに終わったコホートだけを分母に含める。分母ゼロはnull/対象なし。遅れたオフラインイベント到着で過去値は変化する。

日別DAU/検索/AI検索/favorite_openのSVGグラフと、同じ値の開閉式数値表を提供。provider/feature/sourceはmeterで表示。匿名ID、Discord情報、token等は集計応答に出さない。クエリはRepositoryのprepared statement、複数集計は読み取りtransactionで行う。追加Migrationなし。大規模・長期蓄積時の集計速度は未検証。

`tests/admin-statistics.php` は専用testmode DBで実イベントを一時作成し、期間境界・D7・ゼロ日・各分類・current accounts/storage・権限・日英HTMLを検証して自分のfixtureのみ削除する。MySQL/MariaDB各48成功。実ブラウザでJA期間変更/表開閉、JA/EN390px幅とConsoleを確認。全browser・実Extensionの収集は未確認。
