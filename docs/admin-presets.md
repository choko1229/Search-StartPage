# 検索・AIプリセット管理

`/admin/presets` の通常管理者画面で検索・AIの初期一覧を編集する。名前、固定ID、URLテンプレート、Prefix、アイコン文字、有効化、並び順、コピー案内を編集でき、各分類の最後の空欄から1件ずつ追加できる。削除は明示した項目だけを初期一覧から除く。各分類50件まで、各分類に有効な項目を最低1件残す。

GET/POST `/api/admin/presets` は管理者権限が必須。POSTはCSRFと、GETで取得した`version`、`presets: {web: [...], ai: [...]}`を送る。管理APIの並び順は`sort_order`、公開・同期向けは既存形式の`sortOrder`へ変換する。競合する版は409、validationは422。HTMLフォームも同じ保存・検証・監査処理を通る。

全分類をまたぐID/Prefix重複、危険スキーム、URL内認証情報、無効URL、余分なフィールド、不正boolean/並び順を拒否する。検索URLは`{query}`必須。AIはコピー案内付きのURLも許可する。アイコンは文字として表示し、管理画面の値はEscape、ホームのbootstrapはJSON HEX Escapeを使う。

Migration015はsite_settingsのprovider_presets行を初期作成し、設定と監査contextの列をMEDIUMTEXTへ拡張する。再実行で管理者の編集を初期化しない。downは空の隔離DB検証用で、大きな設定・監査記録をTEXTへ切り詰める必要がある場合には停止する。UpdaterのRollbackからdownを実行しない。

変更前後とactor/versionを`PROVIDER_PRESETS_CHANGED`としてDB transaction内に記録し、監査fileへの配送を行う。通常POSTではfile配送失敗時もDBの監査を保持する。private storage/presetsの0600スナップショットは排他・原子的置換を使い、更新前の失効で古い初期値の再利用を防ぐ。スナップショットがあると通常ホームはDBへアクセスしない。読み取り不能時はDBから再取得し、取得もできない場合は同梱初期値で端末検索を維持する。無効・破損キャッシュや実DB停止の網羅検証は残る。

GET `/api/provider-presets`とホームbootstrapへ現在の公開初期値を供給する。独自の端末・クラウド一覧が保存されている場合は上書きしない。利用者の検索設定には、保存済み一覧に存在しないプリセットを明示的に追加する選択欄を追加した。追加時はID/Prefixの衝突を拒否し、既存項目を保持して有効な追加項目を末尾に置く。最新初期値の反映にはページ再読込が必要。新しく追加したプリセットのIDは匿名統計ではcustom分類となり、管理者が付けた名前やURLはイベントに送らない。

検証: 新規MySQL8/MariaDB10.11専用環境8097/8098で全15Migrationのup/repeat/down/Web Installer再up各40成功。管理権限/CSRF/validation/CAS/公開bootstrap/日英HTML/Escape/同期schema/監査/大きなカタログと監査/キャッシュのAPI・DB各36成功。Nodeで保存済み一覧の保持/明示追加/衝突拒否/同期互換、検索23/sync-data28成功。両DB回帰とPHP175成功。実管理者のブラウザ保存/追加/削除/mobile/Console、利用者のプリセット追加、実Extension反映は未確認。
