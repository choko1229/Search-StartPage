# 再開ポイント

最終更新: 2026-09-30（Asia/Tokyo）

## 現在の状態

Version 1.0未完成。Phase 1は基盤検証済み。仕様全文の監査によりPhase 2/3を再確認中。Phase 4は途中の実装を保持、実Discord往復未確認。Phase 5〜12未着手。過去のPhase 2/3完了表記より本記録とdocs/spec-audit.mdを優先する。

Phase 2のAI頻度/最近順、検索・履歴キー変更、URL方針、クリック候補、履歴件数/期間/エリアを実装し3種類の検証を実施。ヘッダー履歴導線（§33）は残る。Command Palette導線はPhase 8、履歴同期はPhase 5に接続する。

## 再開ルール

最初に本ファイル、docs/phase-status.md、git status --shortを確認。spec.mdはユーザー提供で変更しない。Phase順、最低3回の検証、Phaseごとにコミットを守る。未確認を成功扱いにしない。本番DB・push・公開・Windows再起動は自動実行しない。秘密値を記録しない。

## 環境

- Docker: C:\Users\choko\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe
- 両DB検証: search-test-20260929221803。app-mysqlは8080、app-mariadbは8081。
- UI: search-phase1-ui、http://127.0.0.1:8082/ 。DBはsearch-test-20260927223223-mysql-1。004_auth適用済み。
- 9/30に停止していた上記コンテナのみ再起動。旧環境のボリュームを保持。他の旧アプリは停止したまま。
- UI configはコンテナ内/var/www/app/config/config.php。ホストとは共有しない。
- Docker cpで反映する構成。直近public/lang/appは両DB検証環境へ反映済み。
- IABタブ2、検証画面。画像.test-output/phase2-settings.png（Git除外）。

## 今回の検証

1. JS: search-preferences 18項目、既存search 23項目合格。
2. MySQL 8 / MariaDB 10.11それぞれPHP構文59ファイル、単体39、検索API4、認証24合格。
3. Browser: Control+Enterの同一タブ検索、履歴エリア、空欄フォーカスの履歴候補、Control+Shift+H、件数25/期間7の遷移後保持を確認。390px幅でdocument375/dialog375/内容358、横はみ出しなし。console warn/error 0。

数値・キー設定は入力時にも妥当な値を保存するよう修正。検証用ローカル検索先と履歴は127.0.0.1のブラウザ内だけに保存。既存データを消していない。

## 次に実行すること

1. Phase 2の§33ヘッダー履歴導線を補修・検証し、Phase 2の残り仕様を照合する。
2. Phase 3の§36件数によるAuto、§37位置/幅/高さ/件数/列数、§38自動表示数を補修。3回検証・コミットする。
3. Phase 4の/api認証・端末ルート、ログインRate Limit、DB停止時のゲスト継続を補修。ユーザーはDiscordアプリを用意・設定可能と回答済み。設定済みか未確認。docs/discord-development.mdとbin/configure-discord.ps1を利用しSecretをチャットに求めない。
4. 実OAuthとログイン済みUIを検証しPhase 4を判定。その後Phase 5〜12を順番に進める。

## 保存履歴

6254f38: 既存Phase 1〜3とPhase 4途中の基準保存。
6ca6bd3: アカウント容量表示・同期済みデータ削除処理。同期所有権マニフェストの書込みはPhase 5に未接続。
29d5bdb: 仕様全文監査。今回のPhase 2補修は別コミットで保存する。
