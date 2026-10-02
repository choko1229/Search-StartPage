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

`BackgroundCompression`はImagick優先/GD fallback、FFmpegのPATHまたはSEARCH_FFMPEG_PATH検出を実装。画像は寸法・PNG透過を保持、JPEGのEXIF向きを実画素へ反映。ImagickはGIFフレーム/遅延/ループを保持。GDで保持できないアニメーションやメモリ不足見込みは元データを保持する。APNGは現エンジンで安全に再エンコードできることを証明していないため保持・警告。入力を削除せず、検証済みの小さい出力だけを返し、大きくなる場合は元データを返す。容量計測用bytesは採用ファイルの実サイズ。

FFmpegはshellを介さない引数配列、検査済み形式のdemuxer固定、ネットワークprotocol拒否、2threads/120秒/出力500MiB上限。失敗・機能不足は元ファイルと共に警告コードを返す。管理画面での警告表示はPhase 9へ接続が必要。Imagickのメモリ/ディスク等を制限し呼出後に元の上限へ復帰。圧縮はDB登録前サービスで、まだUpload APIへ接続していない。

実圧縮専用コンテナ`search-compression-20261002`でImagick/GD/FFmpeg17項目、GDのみ/FFmpeg16項目、proc_open禁止環境12項目、元の両DB環境で機能不足8項目を確認。画像/動画は試験コードがローカル生成したデータで外部媒体をダウンロードしていない。最初のImagick呼出メソッド誤りは試験で検出して修正し再成功。タイムアウト・巨大動画の実エンコード・全画像形式/実カメラのICCは未検証。

`docker/compression.Dockerfile`は省略可能な圧縮試験用。BASE_IMAGEは既定php:8.2-apache-bookworm、検証時は既存の隔離アプリイメージを指定した。GD/EXIF/Imagick3.8.1/FFmpegを含む。アプリ全体で必須にはしない。圧縮試験はSEARCH_TEST_MODE=1でtests/background-compression.phpを実行。実画像decoderやFFmpegで未対応の場合の警告を成功した圧縮と扱わない。

次は専用メタ情報Migration/Repository、所有権・CSRF付きAPI、容量制限の原子的適用と失敗時ファイル清掃、アップロードUI/背景ごとのCloud Syncへ接続する。500MiB multipartを許可するPHP/Webサーバー設定も未反映（現Docker post_max_size=32M）。条件編集全種・天気/地域・手動動画再生導線も未完了。

## 背景DB/APIの接続（最新・2026-10-02）

前記の「Upload API未接続」は今回の変更で解消。008_backgroundsのbackgrounds/background_rulesを追加、所有者row lockによる圧縮後容量と項目versionのCAS。config backgrounds.max_bytes=0は合計制限なし、個別上限は保持。永久削除/孤立再清掃は未実装。

| Method | Path | 入出力 |
|---|---|---|
| GET | /api/backgrounds | 所有者のitems/storage/compression |
| POST | /api/backgrounds/url | JSON item、201 |
| POST | /api/backgrounds/upload | multipart fileとJSON文字列item、201 |
| PUT | /api/backgrounds/{id} | JSON versionと部分item、409競合 |
| DELETE | /api/backgrounds/{id} | JSON version。復元可能な保管で容量保持 |
| GET/HEAD | /api/backgrounds/{id}/file | Auth所有者限定、64KiB stream/single Range/206/416 |

PUT deleted:falseで復元。変更系CSRF header必須。絶対パス/保存名をAPIへ返さない。新規両DBでAPI27、実並列quota6、Installer35/全8Migration往復、既存回帰/構文96成功。PHP post_max_size502M/upload_max_filesize500M/input600/execution180。JSON一般1MiB/同期32MiBのアプリ上限は維持。別PHP/Web環境も500MiB+overheadのmultipart設定が必要。実500MiB upload/実圧縮とDB容量の組合せは未検証。

次はLocal Upload UI/IndexedDB blob、背景ごとのCloud Syncと競合/offline、条件全種編集/天気・地域/手動動画再生。cloud_syncはDB項目で端末間同期は未接続。Phase 7未完了。

## Phase 7 同期ファイルの安全な置換（2026-10-03）

POST /api/backgrounds/{id}/uploadを追加。Auth/所有者/CSRF、multipartの厳密なversion、ID一致、実ファイル検査/圧縮を確認し、DB transactionで版と圧縮後容量を再検査して置換。成功後に旧ファイルを除去、失敗時は元データを保持して新候補を清掃。PUTでupload→URL/色へ切替も接続、画像/動画へのURL切替は明示URLが必要。実ファイルなしでURL→upload宣言は拒否。DB schema変更なし。

APIメタにfileRevision（非公開乱数保存名のSHA256、保存名/パスそのものは露出しない）を追加。私有file APIはETagとIf-Match/412に対応。メタ取得とファイル取得の間に置換が起きた場合、同容量でも異なる版のファイルを保存しない。JS transportはmultipart版付き置換とETag一致検査へ対応。まだ製品UIから同期transportを呼ばない。

検証1: 両DB実HTTP背景API40項目（CSRF/他所有者/古い版/不正multipart版/旧ファイル保持/置換成功後清掃/容量/ETag/URL切替）成功。検証2: 両DB並列quota6/基盤39/PHP lint成功（MySQL98:専用public/_test PHP込み、MariaDB97）。検証3: 全JS単体18ファイル成功、置換multipart/If-Match/ETag不一致をfetchモデルで確認。初回API試験の置換成功確認は失敗、別HTTPプロセス削除後のPHP stat cacheをclearstatcacheして再成功。アプリ失敗と混同しない。新規ブラウザ操作なし、変更UIなし。

最新両DBへapp/tests反映、8082は今回backend未反映。旧ファイルunlink失敗やプロセス強制終了時の耐久的孤立清掃は未実装。fileRevisionは内容hashではなく保存ファイルの版識別。巨大動画実通信/実圧縮+DB容量/置換同時競合の専用実HTTP試験は未確認（Repository CASと既存並列quotaを検証）。

次はCloud Sync ON/OFFの編集UIとbackground checkpoint/所有権・3-way競合・初回選択・offline復帰を接続。受信Blobとメタ/checkpointはIDB同一transaction、保存中のローカル編集を保持し、ローカル専用背景は送らない。既存同期文書へ背景ライブラリを不用意に追加しない。条件全種/天気/地域/手動再生、容量表示と所有権削除時Blob清掃も残る。Phase 7未完了、8〜12未着手、Version 1.0未完成。

## アップロードの再送識別（2026-10-03）

POST uploadおよびPOST {id}/uploadは任意のX-Background-Request（64桁小文字hex）を受け付ける。認証owner・送信番号を009_background_upload_receiptsに記録し、経路/期待version/item原文/原本bytesが同じなら元のitem/version/warningとreplayed:trueを返す。その後の現在の背景は変更しない。番号の別内容への流用は409、形式不正は422。owner hint/CSRF/アップロード検査は維持。応答記録と背景保存は同一transaction、並列要求はowner lock後に再照合。一時候補は再送時も清掃。番号なしの既存呼出は通常CASのまま。

JS createBackgroundの第5引数requestIdで指定可能。製品sessionでの送信前intent永続化・再読み込み後回復は次の作業であり、まだ番号を製品sessionから送っていない。記録の自動期限削除なし、ユーザー削除時cascade。全9Migrationの新規Installer全体確認は未実行。今回の両DB実HTTP50/009往復/基盤39/全JS21ファイル・PHP構文は成功。
