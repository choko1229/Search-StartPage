# Background System — Phase 7実装中

## 現在使える機能

設定のBackgroundから、Solid/Gradient/Image/Videoの端末内ライブラリを作成・編集・保管・復元できる。画像・動画はHTTPS URLまたはサイト内パス。プリセット、手動/ランダム/時間条件、描画調整、動画速度/ミュート/ループ/停止、モバイル代替画像を実装。カテゴリresetはライブラリを削除しない。

11条件とAND/OR、詳細条件優先・同順位ランダムの判定コアは実装済み。現時点の条件編集画面は時間のみ。天気・気温情報はまだ取得しない。端末内選択を通常設定のクラウド同期ACKで上書きしない。

## サーバーアップロード基盤

`BackgroundUpload`はアプリへ接続するためのサービスで、公開API/アップロード画面はまだない。

- JPEG/PNG/GIF/WebP/AVIF、MP4/WebMの拡張子と実データMIMEを照合。画像の実寸法を確認。SVG/HTML/スクリプト等は受付対象外。
- 画像25MiB、動画500MiB。クライアントの申告サイズ/MIMEを信用せず、一時ファイルの実サイズを計測。
- 名前のパス区切り/制御文字/実行形式の二重拡張子を拒否。保存名は暗号学的乱数と正規化した拡張子。
- 実HTTPアップロードだけを`move_uploaded_file`で移動。通常のサーバーファイルをアップロードとして扱わない。
- 呼び出し側の認証済みユーザーIDで`storage/uploads/backgrounds/{user_id}/`へ保存。ユーザー申告の所有者IDを使用してはならない。
- 公開ルートの外、ファイル0600/ディレクトリ0700。保存経路のsymlinkを拒否。保存後のパスは内部処理用で、APIへ返してはならない。

PHPのHTTPアップロード由来検証については[PHP公式move_uploaded_file](https://www.php.net/manual/en/function.move-uploaded-file.php)、実内容判定は[PHP公式Fileinfo](https://www.php.net/manual/en/function.finfo-file.php)を参照。

## 検証と残件

background-core41、sync-data28単体と既存JS回帰、両DB環境のPHP既存回帰を確認。ファイル検査22単体と独立ループバックHTTP14項目を両DBの隔離コンテナで実行。HTTP試験はwww-dataで非公開保存/実MIME/危険形式拒否/同名上書き防止/symlink拒否を検証し、一時領域を終了時に除去する。このHTTP試験は保存サービスの証拠であり、本アプリの認証/CSRF/容量競合/圧縮/ファイル同期の証拠ではない。

次はImagick→GD/FFmpegの環境検出・圧縮・圧縮後サイズ計測、専用メタ情報Migration/Repository、所有権・CSRF付きAPI、容量制限の原子的適用と失敗時ファイル清掃、アップロードUI/背景ごとのCloud Syncへ接続する。500MiB multipartを許可するPHP/Webサーバー設定も未反映（現Docker post_max_size=32M）。不足環境の警告はシステム全体を停止させない。条件編集全種・天気/地域・手動動画再生導線も未完了。
