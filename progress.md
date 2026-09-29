# 再開ポイント

最終更新: 2026-09-29（Asia/Tokyo）

## 現在の状態

Phase 1〜3完了。Phase 4 Discord Authは実装・検証中。Phase 5〜12は未着手。Version 1.0未完成。未コミット・未push。
ブラウザ操作の停止は解消済み。Dockerも正常。再起動は不要。

## 仕様と再開ルール

- 最初に本ファイル、`docs/phase-status.md`、`git status --short` を確認。
- `spec.md` はユーザー提供、変更しない。
- Phase別原指示: `C:\Users\choko\.codex\attachments\3c6889c5-0af0-4e04-9501-b9a06fd74664\貼り付けたテキスト.txt`
- Phase順を守り、未確認を成功扱いにしない。本番DB・push・公開・Windows再起動は実行しない。

## 実行環境

- Docker: `C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe`
- 最新合格Compose: `search-test-20260929221803`。PHP 8.2、MySQL 8 (8080)、MariaDB 10.11 (8081)。
- UI: `search-phase1-ui` http://127.0.0.1:8082/ 。旧 `search-test-20260927223223` のMySQLへ接続。004_auth適用済み。
- 旧Phase 3環境の8080/8081アプリは停止済み。ボリュームは削除していない。
- UIのconfigはコンテナ内 `/var/www/app/config/config.php`。ホストと共有していない。
- 秘密値はファイルやチャットに記録しない。

## 確認済み

- Phase 1〜2: 基盤/Installer/検索。両DBの単体39・統合35、検索API4、JS23。
- Phase 3: CRUD、フォルダ、タグ、あいまい検索、メニュー、統計、Alt+1、実Drag&Drop。390px幅ではみ出しなし。削除HTMLダイアログも確認。
- Phase 4: OAuth state（10分・使い捨て）、identifyスコープ、長期トークンのハッシュ保存、90日rolling失効、端末管理、ログアウト、初期管理者付与、アカウントViewを実装。
- 最新の新規Composeで構文、単体39、Installer統合35、検索API4、SSRF拒否8、認証20が両DBで合格。
- その後認証テストを24項目へ拡張し両DB合格。HTTPログアウトCSRF、初期管理者の一度だけの付与を含む。
- Discord未設定のアカウント画面をブラウザ確認。

## 次に実行すること

1. Phase 4の残作業を原仕様と照合。アカウントの保存容量/同期状態表示、同期済みローカルデータのログアウト時削除とlocal-only保持の設計・実装・テストが残る。同期本体はPhase 5。
2. 実Discordアプリで認証往復を確認。開発用Client ID/Secretは未設定。ユーザーにアプリの有無を質問済み、回答待ち。Secretをチャットに求めない。
3. 設定手順は `docs/discord-development.md`。configのsite.urlと登録callbackのホスト/ポートを一致させる。
4. 実アカウント画面で端末名変更、複数端末、個別ログアウト、現在端末、再ログインを確認。
5. Phase 4完了条件を満たしてからPhase 5。現時点でPhase 4は完了扱いにしない。

## 主要追加ファイル

`app/Auth/Auth.php`, `OAuthState.php`, `DeviceAgent.php`、`app/Services/DiscordOAuth.php`、`app/Repositories/AuthRepository.php`、`app/Controllers/AccountController.php`、account/logged-out Views、`004_auth.php`、`tests/auth.php`。

ブラウザはIABのタブ2を利用。旧標準confirmの代わりにHTML dialogで削除確認。画面保存は `.test-output/` （Git除外）。
