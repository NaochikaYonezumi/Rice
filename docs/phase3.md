# Phase 3: ワークフローエンジンとレポート

## 実施内容
- **Workflow モジュールの構築**:
  - `Modules/Workflow` ディレクトリを作成し、Service, Controller, View を配置。
  - **ワークフローエンジン**: `WorkflowEngine` を実装。件名や送信元アドレスに基づく自動タグ付け、担当者割り当てをサポート。
  - **ラウンドロビン**: 担当者が未割り当てのスレッドに対し、メンバー間で均等に割り振るロジックを実装。履歴管理用に `ext_workflow_round_robin` テーブルを導入。
  - **レポート機能**: `ReportService` により、総スレッド数、未対応数、エージェント別返信数、ステータス分布、タグ統計を集計。
- **UI 統合**:
  - AdminLTE スタイルを採用したレポートダッシュボード (`/reports`) を作成。
  - サイドバーに「レポート」メニューを追加。

## 構成
- データベース: `ext_workflow_rules`, `ext_workflow_round_robin`
- モジュール: `Modules/Workflow`
- エンドポイント: `GET /reports`

## 次のステップ
- Phase 4: ナレッジベースとベクターDB統合 (RAG 連携)
