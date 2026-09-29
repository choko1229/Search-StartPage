# このリポジトリでの作業ルール

## 再開手順（必須）

1. 作業開始時・コンテキスト復元時は、まずルートの `progress.md` を読む。
2. `docs/phase-status.md` の該当Phase記録と `git status --short` を確認する。
3. 実装の根拠は `spec.md` とユーザーが添付したPhase別仕様。最新のユーザー指示を優先する。
4. `progress.md` の「次に実行すること」から再開する。既存ファイルを最初から作り直さない。
5. 「未実行」「未確認」は成功として扱わない。Phaseの完了条件を確認してから次Phaseへ進む。
6. 再起動・中断・Phase終了・ブロック条件の変化時に `progress.md` と `docs/phase-status.md` を更新する。

## 実装・検証

- Phase 1〜12を順番に進め、各Phaseの不具合を修正してから次へ進む。
- PHP 8.2+、MySQL 8+とMariaDB 10.11+、Vanilla JavaScript。PHPフレームワークやnpm buildを必須にしない。
- ControllerにSQLを書かず、ViewにDB処理を書かない。DB変更はMigration化する。
- CSRF、Escape、prepared statements、サーバー側の権限検証を維持する。
- 設定値・パスワード・OAuth Secret・セットアップキーを進捗ファイルやGitへ記録しない。
- 本番DBをテストに使わない。Dockerのテストは隔離された専用DB・ボリュームを使う。
- Git未追跡の `spec.md` はユーザー提供の仕様書。勝手に変更・削除しない。
- 本番公開、push、Windows再起動をこの再開ルールから自動実行しない。

## 記録

- `progress.md`: 最新の再開地点、環境の状態、検証結果、次の具体的な手順。
- `docs/phase-status.md`: Phaseごとの実装・ファイル・DB・API・UI・Tests・Issues・Security・次Phaseへの影響。
- Version 1.0は全DoDを満たすまで完成扱いにしない。
