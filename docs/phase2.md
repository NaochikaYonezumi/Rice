# Phase 2: コアメール処理とスレッド管理

## 実施内容
- **MailClient モジュールの構築**:
  - `webklex/laravel-imap` を導入し、POP3/IMAP 取得の基盤を構築。
  - `EmailFetcher` サービスを実装。Message-ID による重複検出、スレッドの自動紐付け（In-Reply-To/References 考慮）、添付ファイルの `/storage/attachments/` 保存をサポート。
  - `EmailSender` サービスを実装。SMTP 送信、エージェント署名の付与、返信時のスレッドオーナーシップ（最終返信エージェントへの割り当て）を自動化。
- **スケジュールタスク**:
  - `mail:fetch` コマンドを作成。
- **ファイル管理**:
  - 添付ファイル最大サイズ 20MB に対応するためのバリデーション準備。

## 構成
- モジュール: `Modules/MailClient`
- コマンド: `php artisan mail:fetch`
- 保存先: `/storage/app/attachments/`

## 次のステップ
- Phase 3: ワークフローエンジンとレポート機能の実装
