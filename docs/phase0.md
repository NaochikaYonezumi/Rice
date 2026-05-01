# Phase 0: 環境セットアップ

## 実施内容
- **モジュールディレクトリの準備**: `laravel/Modules` ディレクトリを作成しました。すべてのカスタム機能はこの配下に実装されます。
- **インフラ構成の更新**:
  - `docker-compose.yml` に **PostgreSQL + pgvector** (`ankane/pgvector`) を追加しました。
  - サービス名: `postgres`
  - ポート: `5432`
  - データベース名: `rice_vector`
  - 不要となった `chromadb` サービスおよびボリュームを削除しました。
- **FreeScout コンテキストの確認**: 現状、ディレクトリ直下に FreeScout のコアは存在しませんが、今後 `/Modules` 配下でモジュール開発を行うための構造を構築します。

## 技術スタックの確定
- Web: Laravel (PHP 8.3)
- DB: MySQL (メール用), PostgreSQL + pgvector (AI用)
- LLM: Ollama
- アーキテクチャ: モジュラーアーキテクチャ

## 次のステップ
- Phase 1: 認証基盤 (Microsoft Entra ID) と AdminLTE/Bootstrap UI の統合
