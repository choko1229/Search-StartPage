# 更新処理の永続記録

`UpdateJournal` は更新段階とバックアップの識別情報を記録する内部サービス。UpdateEngineの適用・清掃・復元へ接続した。CLI workerとDB監査履歴へ接続した。公開API/管理UIの実行操作はまだ未接続。

## 保存と並列実行

呼び出し側が用意したprivate directoryへ `journal.json` を保存する。共有lock下でrevisionとjob IDを照合し、古い操作は409 `UPDATE_STATE_CHANGED`、実行中の別更新は409 `UPDATE_IN_PROGRESS`。0600の一時ファイルへ書込み、flush/fsync後にrenameする。破損・上限超過・リンク・不正schemaは503 `UPDATE_JOURNAL_INVALID` とし、記録を初期化しない。ローカルfilesystemを前提とし、親directoryのfsyncによる停電時の耐久性までは保証しない。

## 段階と直前1世代

通常経路はqueued → verified → backing_up → backed_up → replacing → migrating → checking → complete。変更開始後の失敗はrolling_backへ進み、rolled_backまたはrollback_failedを記録する。rollback_failedからの再試行を許容する。変更前の失敗はfailedで終了する。

backed_upにはfile/DB snapshot descriptorが必須。completeで初めて直前1世代へのpointerを更新する。更新失敗の復元では以前の成功世代を維持し、現在の成功世代を手動復元した場合はpointerを消費する。履歴は20件までだが、唯一の復元世代を所有する記録は失敗の反復でも保持する。保存するerrorは固定codeだけで、任意例外文字列、URL、SQL、認証値、ユーザー行は受け付けない。

descriptorは形式・version・hash・件数の検証だけを行う。実archive/DB snapshotの存在とhash、Migration/healthの実行成功をこのサービスが証明するものではない。呼び出し側は実データを検証してから段階を進める。旧世代の物理ファイルの削除は更新engineの責任であり、本サービスは削除しない。

Engine接続時にformat2へ変更し、jobにnullableなmanifest_sha256を追加。Engineが作るjobでは必須の正規化manifest hashを固定する。beginRollbackはCASで既存成功ownerを履歴から再アクティブ化する。format1等の不一致は初期化・自動変換せず拒否する。現在はformat3でrequest_idとrollback_request_idを分けて保持する。format2の既存metadataを保持し、読取りでは書き換えず次の正常な原子更新でformat3へ保存する。format1や不正な旧metadataは拒否する。現在のjournal単体試験は両52項目。

## 検証

`SEARCH_TEST_MODE=1 php tests/update-journal.php`。生成/tmp領域だけを使い、通常DBや配置を変更しない。両PHP環境で40項目成功。実2プロセスのCAS、process終了後の記録保持、世代切替、復元失敗の再試行、25回失敗時の履歴上限と世代保持、時計の逆行、破損・リンク・不正metadataの拒否、private permissionsを含む。完全なUpdaterの中断回復試験とは区別する。
