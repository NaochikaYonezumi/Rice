# RAG Mail System

メール管理 + RAG（AI回答）システム。

## 機能

| 機能 | 説明 |
|------|------|
| メール受信 | POP3/IMAP でメールを取得・DB保存 |
| スレッド表示 | 返信チェーンを自動グルーピング |
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

> 検証環境: WSL2 (Ubuntu 20.04) + Docker Desktop (Linux engine). macOS / Linux も同じ手順で動きます。
>
> **必須セクション (★) を頭から順に実行すれば http://localhost で UI が立ち上がる** ところまでいけます。任意 (◯) はスキップ可。

### 必須セクション (★ ここまでで Web UI 起動 + ログイン可能)

#### ★ 0. 前提ツール

すでに揃っているなら飛ばして OK。

```bash
# Docker
docker --version           # 20.10 以上を推奨
docker compose version     # v2 必須 (出力に "v2.x" と出ること)

# git
git --version
```

`docker compose` (スペース区切り = v2) を使ってください。古い `docker-compose` (v1, Python 製) はビルド中に `/tmp/tmpxxxx not found` で稀に落ちます。

#### ★ 1. リポジトリを clone してブランチ切替

```bash
cd ~/projects                                            # 作業フォルダへ
git clone git@github.com:NaochikaYonezumi/Rice.git       # SSH 経由 (推奨)
# もしくは https:
# git clone https://github.com/NaochikaYonezumi/Rice.git
cd Rice

# ⚠ 最新の機能 (ゴミ箱 / AND-OR / 転送 等) は feature/phase6-tests にのみ存在.
#   main はまだ古いので必ず切り替える.
git checkout feature/phase6-tests
```

確認: `git branch --show-current` が `feature/phase6-tests` を返せば OK。

#### ★ 2. pre-commit hook を有効化

機密情報 (.env / DB ダンプ / SSH 鍵 / API キー / 顧客ドメイン) の漏洩を防ぐフックを有効化します。

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

確認: `git config core.hooksPath` が `.githooks` を返せば OK。

#### ★ 3. `.env` を作成

```bash
cp laravel/.env.example laravel/.env
```

(MAIL 設定や ANTHROPIC_API_KEY 等は後から `設定 → メール` 画面で変更可なので、最初はテンプレートのまま OK)

#### ★ 4. Docker コンテナを起動 (初回はイメージビルド)

```bash
docker compose up -d --build
```

- **初回所要時間**: 5〜10 分 (rag-api の Python 依存と Laravel イメージのビルド)
- バックグラウンド (-d) で 5 コンテナが起動: `laravel` / `mysql` / `postgres` / `ollama` / `rag-api`

確認:

```bash
docker compose ps
```

5 コンテナ全部が `Up` または `Up (healthy)` になっていれば OK。`mysql` が `starting` の場合は 30 秒ほど待ってから次に進む。

| サービス | ポート (host から) |
|---|---|
| laravel (Web UI) | http://localhost (port 80) |
| rag-api (FastAPI) | http://localhost:8000 |
| ollama (ローカル LLM) | localhost:11434 |
| postgres + pgvector | localhost:5432 |
| mysql | (internal only) |

#### ★ 5. Composer で PHP 依存をインストール

Laravel コンテナ内に `vendor/` がまだ無いので、 docker exec で composer を回す:

```bash
docker compose exec laravel composer install
```

完了サイン: 末尾に `Generating optimized autoload files` が出る (1〜3 分)。

確認: `docker compose exec laravel ls -la vendor/autoload.php` でファイルが見えれば OK。

#### ★ 6. Laravel 初期化 (鍵生成 + マイグレーション + 初期管理者投入)

以下 4 つを順に実行 (どれも数秒で終わる):

```bash
docker compose exec laravel php artisan key:generate        # APP_KEY を生成
docker compose exec laravel php artisan migrate             # DB スキーマ作成
docker compose exec laravel php artisan db:seed             # 初期管理者作成
docker compose exec laravel php artisan storage:link        # public/storage シンボリックリンク
```

確認: `migrate` の出力に `INFO  Running migrations.` と各テーブル名が並んで `DONE` で終われば OK。

#### ★ 7. フロントエンド (CSS/JS) をビルド

Vite 8 + Tailwind 4 を使うため **Node 20 以上** が必要。Laravel コンテナには npm が入っていないので、**使い捨ての Node 22 コンテナ** で実行します:

```bash
cd laravel
docker run --rm -v "$PWD":/app -w /app node:22-alpine npm install
docker run --rm -v "$PWD":/app -w /app node:22-alpine npm run build
cd ..
```

- 所要時間: install で 2〜5 分、build で 30 秒〜1 分
- 結果: `laravel/node_modules/` と `laravel/public/build/` が生成される

(host に Node 20+ を入れているなら `cd laravel && npm install && npm run build` でも可)

#### ★ 8. ブラウザで http://localhost にアクセス → 初回ログイン

| 項目 | 値 |
|---|---|
| Email | `admin@example.com` |
| Password | `password` |

ログインできれば**セットアップ完了**です 🎉

> **⚠ 必ずパスワードを変更**: 上記は誰でも知れる初期値です。ログイン後、画面右上のプロフィール
> アイコンから「パスワード変更」を実行してください。
>
> 別のメアド / パスワードで初期化したい場合は、★ 6 の `db:seed` の前に `laravel/.env` に
> `ADMIN_EMAIL=…` / `ADMIN_PASSWORD=…` を追記すれば、その値で作られます。

---

### 任意セクション (使う機能に応じて)

#### ◯ A. メール受信を動かす

POP3 / IMAP の接続情報を投入する必要があります。2 通りあります:

1. **画面から** (推奨): http://localhost にログイン後、`設定 → メール` で接続情報を入力 → 「接続テスト」で確認 → 保存
2. **`.env` で直接**: `laravel/.env` に `MAIL_POP_HOST=...` 等を書いて `docker compose restart laravel`

Gmail を使う場合の例は下の **POP3メール設定** セクション参照。

#### ◯ B. AI 回答 (RAG) を使う

LLM プロバイダを選択:

- **Ollama** (ローカル, 無料): 下記でモデル取得
  ```bash
  docker compose exec ollama ollama pull llama3.2     # 軽量 3B (8GB RAM 推奨)
  ```
- **Claude API** (クラウド, 有料): `laravel/.env` に `ANTHROPIC_API_KEY=sk-ant-...` を設定 →
  `docker compose restart laravel rag-api`

詳しくは下の **LLM切り替え** セクション参照。

#### ◯ C. ゴミ箱 / 迷惑メールの自動削除 (30 日) を動かす

スケジューラを動かすと、`mail:purge-trash` / `mail:purge-spam` / `mail:fetch` 等が自動実行されます。

```bash
# 開発時 (フォアグラウンド)
docker compose exec laravel php artisan schedule:work

# 本番: laravel コンテナの crontab に
# * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

止める時は Ctrl+C。スケジューラを動かしていなくても、メール手動操作 (削除 → 復元等) は全て機能します。

#### ◯ D. ドキュメント / Web をナレッジに登録

- http://localhost/documents で PDF / Word / Markdown をアップロード
- http://localhost/scrape で URL を登録

これらは RAG コーパスに追加され、AI 回答時の参照源になります。

---

### 起動状況の確認コマンド

```bash
docker compose ps                         # コンテナ稼働状況
docker compose logs -f laravel            # Laravel ログ (Ctrl+C で抜ける)
docker compose exec laravel php artisan migrate:status   # マイグレーション履歴
curl -I http://localhost                  # HTTP 200 が返れば Web UI 起動済み
```

### 停止 / 再起動 / 完全リセット

```bash
docker compose stop                       # 一時停止 (データは残る)
docker compose start                      # 再開
docker compose restart laravel            # 1 コンテナだけ再起動
docker compose down                       # コンテナ削除 (named volume は残る = DB データ保持)
docker compose down -v                    # named volume も削除 (DB / Ollama モデル含めて完全リセット)
```

---

## トラブルシューティング

| 症状 | 原因 | 対処 |
|---|---|---|
| `vendor/autoload.php` がない | composer install 未実行 | 手順 5 を実行 |
| `npm: not found` | Laravel コンテナに npm なし | 手順 7 の Node 22 docker 経由で実行 |
| `docker-compose` (v1) で `/tmp/tmpxxxxx not found` | v1 の tempfile race | `docker compose` (v2) を使う or 再実行 |
| migration で `Connection refused` | mysql がまだ accept してない | `docker compose ps` で `healthy` を待ってから再試行 |
| ログイン画面の CSS が崩れる | フロントビルド未実行 | 手順 7 (`npm run build`) を実行 |
| `403 Permission denied` (push 時) | 機密情報を pre-commit hook がブロック | エラー内容を確認し、`.env` 等をステージから外す |
| ポート 80 / 8000 が使用中 | 他のアプリ稼働中 | docker-compose.yml の `ports` を変更 (例: `8080:80`) |

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
