# RAG Mail System

Notion風UIのメール管理 + RAG（AI回答）システム。

## 機能

| 機能 | 説明 |
|------|------|
| メール受信 | POP3/IMAP でメールを取得・DB保存 |
| スレッド表示 | 返信チェーンを自動グルーピング |
| Notion風UI | 左：スレッド一覧 / 右：本文表示 |
| AI回答 | メール本文 + スクレイピングデータ + アップロードドキュメントを参照してAIが回答 |
| ドキュメント管理 | PDF / Word / Markdown をアップロードしてRAGコーパスに追加 |
| Webスクレイピング | URLを登録してRAGコーパスに追加 |

## アーキテクチャ

```
ブラウザ
  └── Laravel (UI/メール管理/APIゲートウェイ) :80
        └── Python FastAPI (RAG処理) :8000
              ├── Ollama (ローカルLLM) :11434  ← または Claude API
              └── ChromaDB (ベクターDB) :8001
                    ├── メール履歴コーパス（手動インデックス化可）
                    ├── スクレイピングデータ
                    └── アップロードドキュメント（PDF/Word/MD）
```

## 起動

```bash
bash scripts/setup.sh
```

## Git Hooks (個人情報 / シークレット流出防止)

`.githooks/pre-commit` で「DB ダンプ / `.env` / 顧客企業ドメイン / API キー
っぽい文字列 / 添付実体」をステージング時に検出して commit を止めます。

**新しく clone した直後に 1 度だけ実行:**

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

検出されるとエラーメッセージが出てコミットが拒否されます。
緊急時は `git commit --no-verify` で迂回できますが、中身を目視確認してください。

顧客企業ドメインを追加したい場合は `.githooks/pre-commit` 内の
`DENY_DOMAINS=( ... )` 配列に足してください。

## POP3メール設定（Gmail例）

`laravel/.env` に設定：

```env
MAIL_POP_HOST=pop.gmail.com
MAIL_POP_PORT=995
MAIL_POP_USERNAME=your@gmail.com
MAIL_POP_PASSWORD=xxxx xxxx xxxx xxxx   # Googleアプリパスワード
MAIL_POP_SSL=true
```

> GmailはGoogleアカウント設定 → セキュリティ → アプリパスワードで生成

## LLM切り替え

### Ollama（デフォルト・完全ローカル）
`docker-compose.yml` の `rag-api` → `LLM_PROVIDER=ollama`

### Claude API
```yaml
LLM_PROVIDER=claude
ANTHROPIC_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-sonnet-4-6
```
```bash
docker compose restart rag-api
```

## AIの参照データ

AIボタンを押すと、以下を横断して回答：

1. **アップロードドキュメント** (`/documents` でPDF/Word/MD登録)
2. **スクレイピングデータ** (`/scrape` でURL登録)
3. **メール本文のコンテキスト**（完了したメール）

## 対応ドキュメント形式

- PDF (`.pdf`)
- Word (`.docx`, `.doc`)
- Markdown (`.md`)
- テキスト (`.txt`)
- 最大サイズ: 20MB

## RAM要件

| モデル | 必要RAM |
|--------|---------|
| llama3.2 (3B) | 8GB |
| llama3.1 (8B) | 16GB |
