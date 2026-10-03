# 機能と制限の管理

管理者として `/admin/policy` を開く。変更には現在のDB管理者権限とCSRFが必要。古い画面からの更新は409となるので再読込する。

| 設定 | 対象 |
| --- | --- |
| cloud_sync | 設定・お気に入り・履歴・背景等のクラウドAPI |
| background_uploads | クラウドへの背景ファイルアップロード |
| weather | サーバー経由の天気取得 |
| external_suggestions | 外部検索候補の取得 |
| favorite_metadata | お気に入りURLのメタデータ取得 |

端末内の検索・保存・背景は継続する。同期停止でも既存の私有背景ファイル取得は認証・所有者検証付きで利用できる。同期画面は停止理由を表示し、端末の変更・同期記録・送信中の背景intentを保持して定期的に再試行する。サーバーはJSON解析やアップロード処理より前に停止を判定する。

背景容量はユーザー単位のbytes。0は容量総量の制限なし。空欄は既存configの設定を引き継ぐ。画像25MiB・動画500MiBの個別ファイル上限は継続する。容量を下げても既存ファイルを削除せず、容量が増えない編集・縮小・削除を許可する。同時アップロードはDBロックで最終合計を検証する。

ログイン回数と時間窓はログイン経路だけに適用する。通常APIに一律の回数制限は設けない（spec §97）。設定更新・監査記録は同一DB transactionで保存し、私有キャッシュの更新は排他制御する。公開 `/api/site-policy` は機能フラグだけを返し、管理上限やconfigを公開しない。

API: GET/POST `/api/admin/policy`。POSTは整数versionとpolicy（flags全5項目のbool、limits全3項目の整数またはnull）を送る。更新履歴は管理ログのSITE_POLICY_CHANGEDで確認できる。

外部候補・サイト情報・天気の停止理由を日英表示する。ローカル候補・お気に入り手入力は利用でき、天気条件は取得不能として扱う。天気は60秒後に再試行し、成功後に停止表示を消す。アカウント画面も保存済みの同期停止状態を表示する。

検証: 専用MySQL環境の実ゲスト画面でJA外部候補停止、JA/ENサイト情報停止、編集のCancel、Console警告/エラー0を確認。天気停止→復帰は模擬通信による製品WeatherContext試験。実管理ブラウザ・モバイル・認証済みaccount・天気の停止画面は未確認。全13Migrationの空DB Installer往復は未実行で、前回全12の結果とは区別する。

2026-10-04追加検証: 新規隔離MySQL8095/MariaDB8096で全13Migration up/down/upとWeb Installer各40項目成功。実MySQL管理画面のJA保存/再読込保持/復元、JA/EN表示、390px幅で横溢れなし、変更前後の監査表示、Console0を確認。上の未確認記録のうち全13Installerと管理policy画面のJA/EN・390px表示は解消。EN保存、他の管理画面全体、認証済みaccount・天気停止実UI、全browserは未確認。

専用テスト環境のみ `php tests/site-policy-preview-state.php stop` で候補・サイト情報の2フラグを停止し、検証後は必ず `restore` で元設定へ戻す。元設定は非公開storageへ0600で保存する。CLIかつSEARCH_TEST_MODE=1以外では使用不可、本番routeや認証の回避経路を追加しない。既存の退避がある場合は上書きせず復元を要求する。
