# Phase 4: ナレッジベースとベクターDB統合

## 実施内容
- **Python 側の RAG エンジン更新**:
  - `scraper.py`: 再帰的クローリングロジックを実装。内部リンクのみを追跡し、外部ドメインを無視。
  - `rag_engine.py`: LlamaIndex と PostgreSQL + pgvector の統合を実装。埋め込みデータの保存と検索（Top-5）をサポート。
  - `main.py`: クロール実行 (`/scrape`) とクエリ検索 (`/query`) のエンドポイントを公開。
- **PHP 側の Knowledge モジュール構築**:
  - `Modules/Knowledge` ディレクトリを作成。
  - `NeuronService`: Python API と通信するクライアントを実装。
  - **管理 UI**: クロール対象 URL を登録できる AdminLTE 画面を作成。

## 構成
- ベクターDB: PostgreSQL + pgvector ( rice_vector DB )
- RAG フレームワーク: LlamaIndex
- モジュール: `Modules/Knowledge`

## 次のステップ
- Phase 5: AI 返信アシスタント (UI ボタンと生成フローの統合)
