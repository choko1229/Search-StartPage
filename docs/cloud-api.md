# クラウドデータAPI

Cookieログインが必要。所有者はサーバー側の認証で決定する。変更には `/api/csrf` の `data.csrf_token` を `X-CSRF-Token` ヘッダーへ設定し、JSONを送る。

## 更新版

GETの `data.version` はユーザー全体の同期文書の版。変更にはその `version` が必要で、成功時に1増える。古い版はHTTP409、`error.code=SYNC_CONFLICT`、`data.version/document` に最新状態を返す。自動再送で上書きせず、最新状態から変更を再計算する。

任意の `user_id` はログインユーザーIDの文字列を指定する照合用。不一致は403で拒否し、所有者を指定・変更する機能は持たない。開いていたタブのアカウントが変わる場合に使用する。

## 個別API

| パス | 操作 | 同期文書キー |
|---|---|---|
| `/api/settings` | GET / PUT | settings |
| `/api/favorites` | GET / POST | favorites |
| `/api/favorite-folders` | GET / POST | favorite-folders |
| `/api/search/history` | GET / POST | history |
| `/api/search-engines` | GET / POST | providers-web |
| `/api/ai-providers` | GET / POST | providers-ai |

履歴以外は `PUT <パス>/{id}` で更新、すべて `DELETE <パス>/{id}` で削除可能。`POST /api/favorites/{id}/open` は利用回数・最終アクセスを記録する（設定 `favoriteStats=false` では統計値を変更しない）。

GETは `data.version` と `data.items` の配列を返す。Settingsだけは `data.settings` のオブジェクトを返す。すべて認証済み所有者の項目のみ返す。

POST/PUTの項目は `{"version": 3, "item": {...}}`。追加のid省略時はサーバーでUUIDを生成する。更新は既存項目にフィールドを上書きし、id変更は拒否する。追加201・更新/削除200で `data.version/document/updated_at` の同期スナップショットを返す。既存idの追加409、不明または別所有者のid404、不正項目422。DELETE/openは `{"version": 3}`。

Settings PUTは `{"version": 3, "settings": {...}}` で設定全体を置換する。他のコレクションは維持する。端末専用のsyncEnabled/syncHistory/clearSyncedOnLogoutは送らない。

お気に入りはname/url必須、folderId/tags/icon/color/description/shortcut/pinned/visible/sortOrder等を指定できる。フォルダはname必須。履歴はquery/provider/mode必須でat省略時はサーバー時刻。検索先はname/url/prefix必須、icon/enabled/sortOrder/copyを指定できる。URLはhttp/https限定、認証情報付きURLを拒否する。

フォルダ削除はお気に入りのfolderIdを解除して保持する。同期文書、既存のエンティティ/タグ/設定テーブル、項目版は同じトランザクションで保存する。途中で失敗しても更新版を進めない。

## 同期

GET `/api/sync` は `version/document/updated_at` を返す。文書の各コレクションは、項目idをキーにしたオブジェクト。配列や空配列ではなく、空コレクションは `{}`。

POST `/api/sync` は `{"version": 3, "document": {...}}`。既存クライアント向けPUTも同じ処理を使用する。Web版はPOSTを使用する。初回の端末/クラウド選択は利用者が行い、両方にデータがある状態を自動マージしない。

POST `/api/sync/resolve-conflict` は `version/previous/local` と任意の `choices/rules` オブジェクトを送る。cloudはサーバーの現在文書を使用し、要求で指定できない。異なるフィールドを自動マージし、同じフィールドの未解決競合は409で `data.conflicts` にPrevious/Local/Cloud・存在フラグを返す。まだ保存しない。

選択のキーはJSON配列を文字列化したパス（例: `["settings","theme"]`）、値は `local` または `cloud`。choicesは今回の選択、rulesは保存済みの選択ルール。choicesを優先する。成功時は同期スナップショットと `data.rules` を返し、クライアントはルールを保存する。保存前に版が変われば409で再確認が必要。

PHPとJavaScriptのマージは同じ存在/削除/null/配列の扱いを使用する。認証済みのWeb画面・端末間往復と全DoDの最終検証はPhase記録を参照する。本書だけでPhase完了とはしない。
