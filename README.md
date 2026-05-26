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

## セットアップ手順 (新規 clone から初回ログインまで)

> 検証環境: WSL2 (Ubuntu 20.04) + Docker Desktop (Linux engine).
> macOS / Linux も同じ手順で動きます。

### 0. 必要なもの

| ツール | 役割 |
|---|---|
| git | clone |
| Docker + Docker Compose **v2** | 全コンテナ管理 (laravel / mysql / postgres / ollama / rag-api) |
| (オプション) ssh-agent / GitHub 用 SSH 鍵 | clone を SSH 経由でやる場合 |

`docker compose` (スペース区切り = v2) を推奨。古い `docker-compose` (v1, Python 製) はビルド中の tempfile race condition で稀に落ちます。

### 1. リポジトリを clone してブランチ切替

```bash
cd ~/projects
git clone git@github.com:NaochikaYonezumi/Rice.git
cd Rice

# 最新の機能 (ゴミ箱 / AND-OR ルール / 転送 等) が乗っているブランチへ
git checkout feature/phase6-tests
```

### 2. pre-commit hook を有効化 (機密情報の漏洩防止)

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

`.githooks/pre-commit` がステージング時に以下を検出して commit を止めます:

- `.env` / `.env.local` 等の env ファイル本体
- `*.sql` / `*.dump` / `*.sqlite*` (DB ダンプ)
- `laravel/storage/app/private/attachments/*` (添付実体)
- API キー / トークン (`sk-ant-…` / `sk-…` / `AKIA[0-9A-Z]{16}` / `gh[psour]_…`)
- `DB_PASSWORD=…` / `SMTP_PASSWORD=…` 等の hardcode された値
- `DENY_DOMAINS` 配列に登録された顧客企業ドメイン (既定は空)

緊急時は `git commit --no-verify` で迂回可能ですが、中身を必ず目視確認してください。

社内ドメインを block したい場合 (個人運用):

```bash
# 個人設定として .githooks/pre-commit を編集し、git の追跡から外す
git update-index --skip-worktree .githooks/pre-commit
# 編集して DENY_DOMAINS=("acme.co.jp") のように記載
```

### 3. `.env` を作成

```bash
cp laravel/.env.example laravel/.env
```

(必要なら APP_NAME, MAIL 設定等を編集。後から `設定 → メール` の管理画面でも変更可)

### 4. Docker コンテナを起動

```bash
docker compose up -d --build
```

初回はイメージビルド (rag-api の Python 依存 + Laravel) で **5〜10 分** ほどかかります。完了したら以下が稼働:

| サービス | ポート |
|---|---|
| laravel (Web UI) | http://localhost (port 80) |
| rag-api (FastAPI) | http://localhost:8000 |
| ollama (ローカル LLM) | localhost:11434 |
| mysql | (internal) |
| postgres + pgvector | localhost:5432 |

### 5. Composer 依存をインストール

Laravel コンテナの中に `vendor/` がまだ無いので:

```bash
docker compose exec laravel composer install
```

`Generating optimized autoload files` が出れば完了。

### 6. Laravel 初期化 (APP_KEY + マイグレーション + シーダー)

```bash
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate
docker compose exec laravel php artisan db:seed     # 初期管理者を作成
docker compose exec laravel php artisan storage:link
```

### 7. フロントエンドのビルド

`vite 8 / tailwind 4` を使うため **Node 20+** が必要。host の Node が古い場合は使い捨ての Node 22 コンテナで:

```bash
cd laravel
docker run --rm -v "$PWD":/app -w /app node:22-alpine npm install
docker run --rm -v "$PWD":/app -w /app node:22-alpine npm run build
cd ..
```

(host の Node を 20+ にアップデート済みなら `npm install && npm run build` でも可)

### 8. (オプション) Ollama モデルを取得

ローカル LLM (オフライン回答) を使う場合:

```bash
docker compose exec ollama ollama pull llama3.2     # 軽量 3B
# または
docker compose exec ollama ollama pull llama3.1     # 8B
```

Claude API を使う場合は不要。後述の「LLM切り替え」を参照。

### 9. 初回ログイン

ブラウザで http://localhost を開き、以下でログイン:

| 項目 | 値 |
|---|---|
| Email | `admin@example.com` |
| Password | `password` |

> **⚠️ ログイン後にパスワードを変更してください**: プロフィール画面で個人パスワードに差し替え推奨。
>
> 別のメアド / パスワードで初期化したい場合は、`db:seed` の前に `laravel/.env` へ
> `ADMIN_EMAIL=…` `ADMIN_PASSWORD=…` を追記すると、その値で作られます。

### 10. (オプション) スケジューラを動かす

ゴミ箱 / 迷惑メールの 30 日自動 purge + メール定期取得を動かすには:

```bash
# 開発時: フォアグラウンドで scheduler を回す
docker compose exec laravel php artisan schedule:work

# 本番: 1 分ごとに cron 実行 (laravel コンテナの crontab 等)
# * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

これで `mail:fetch` (1 分ごと), `mail:purge-trash` / `mail:purge-spam` (深夜帯) 等が動きます。

---

## トラブルシューティング

- **`vendor/autoload.php` がない** → 手順 5 (`composer install`) を実行
- **`npm: not found`** → Laravel コンテナに npm は無い。手順 7 の docker 経由 node:22 で実行
- **`docker-compose` (v1) で `/tmp/tmpxxxxx not found`** → v2 (`docker compose`) を使うか、再実行
- **マイグレーション失敗** → `docker compose ps mysql` で healthy になってから再試行 (起動直後は up でもまだ accept してないことあり)
- **ログイン画面に CSS が当たってない** → 手順 7 の build を忘れていないか確認

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
