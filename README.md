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

---

## `.env` の編集方法

### ファイルの場所と編集方法

`.env` は **`laravel/.env`** に1つだけ存在します (リポジトリには `laravel/.env.example` がテンプレートとしてコミットされていて、`.env` 自体は `.gitignore` で除外されています)。

```bash
# 編集 (WSL 内 / コンテナ外で OK)
vim laravel/.env       # または nano / code 等

# 反映 (Laravel 設定キャッシュをクリア)
docker exec -u www-data rice-laravel-1 php artisan config:clear
docker exec -u www-data rice-laravel-1 php artisan config:cache

# 大きく変えた場合 (DB接続, port等) はコンテナ再起動
docker compose restart laravel
```

> **重要**: `docker exec` は `-u www-data` を付けてください。root で artisan を実行するとキャッシュファイルが root 所有になり、後で PHP-FPM (www-data) から書き込めなくなって 500 になります。

### 反映タイミング早見表

| 編集箇所 | 反映に必要な操作 |
|---|---|
| `APP_*` (URL/NAME/DEBUG等) | `config:clear` |
| `DB_*` | `docker compose restart laravel` |
| `MAIL_*` (env側) | `config:clear` (DBの mail_settings が優先されるので注意) |
| `SESSION_*` | `config:clear` + ブラウザCookie削除 (drift防止) |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | `db:seed` 実行時のみ参照 |

### 主要キーリファレンス

#### アプリ基本

| キー | 例 | 説明 |
|---|---|---|
| `APP_NAME` | `RAG Web` | サイト名 (ブラウザタブ等に表示) |
| `APP_ENV` | `local` / `production` | local だと詳細エラーが出る |
| `APP_DEBUG` | `true` / `false` | 本番では必ず `false` |
| `APP_KEY` | `base64:...` | `php artisan key:generate` で生成 |
| `APP_URL` | `http://localhost` / `https://rice.cosy.co.jp` | 招待メール・パスワード再設定メールのリンクのベースURL。**ここを localhost のままにすると、メールで送られるログインURLも localhost になり、別PCから開けません** |
| `APP_LOCALE` | `ja` | 言語 |
| `APP_TIMEZONE` | `Asia/Tokyo` | タイムゾーン |

#### セッション / 認証

| キー | 例 | 説明 |
|---|---|---|
| `SESSION_DRIVER` | `file` / `database` / `redis` | デフォルト file |
| `SESSION_LIFETIME` | `120` | 分単位 |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS 運用時は必ず `true` (Cookie に Secure 属性が付き HTTP 経由で漏れない) |
| `SESSION_DOMAIN` | `rice.cosy.co.jp` | サブドメイン横断で Cookie を使いたい場合は `.cosy.co.jp` |

#### 二段階認証 (2FA)

| キー | デフォルト | 説明 |
|---|---|---|
| `TWO_FACTOR_CODE_LIFETIME` | `10` (分) | OTP コードの有効期限 |
| `TWO_FACTOR_TRUSTED_DEVICE_DAYS` | `30` | 「このブラウザを信頼する」の有効日数 |

#### データベース

| キー | 例 |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `mysql` (compose 内ホスト名) |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `laravel` |
| `DB_USERNAME` | `laravel` |
| `DB_PASSWORD` | `secret` |

> **本番**で運用する場合は `DB_PASSWORD` を必ず変更してください (docker-compose.yml の `MYSQL_PASSWORD` も同時に変更が必要)。

#### メール送受信 (システム全体のデフォルト)

##### SMTP (送信)

| キー | 例 |
|---|---|
| `MAIL_MAILER` | `smtp` / `log` (開発時は log で storage/logs/laravel.log に内容が出力される) |
| `MAIL_HOST` | `smtp.example.com` |
| `MAIL_PORT` | `587` (STARTTLS) / `465` (SSL/TLS) |
| `MAIL_ENCRYPTION` | `tls` / `ssl` / `null` |
| `MAIL_USERNAME` | `user@example.com` |
| `MAIL_PASSWORD` | `***` |
| `MAIL_FROM_ADDRESS` | `noreply@example.com` |
| `MAIL_FROM_NAME` | `Rice Mail System` |

##### IMAP / POP3 (受信)

| キー | 例 |
|---|---|
| `MAIL_INBOX_PROTOCOL` | `imap` / `pop` |
| `MAIL_IMAP_HOST` | `imap.example.com` |
| `MAIL_IMAP_PORT` | `993` (IMAPS) |
| `MAIL_IMAP_ENCRYPTION` | `ssl` / `tls` / `null` |
| `MAIL_IMAP_USERNAME` | `user@example.com` |
| `MAIL_IMAP_PASSWORD` | `***` |
| `MAIL_IMAP_FOLDER` | `INBOX` |
| `MAIL_POP_HOST` | `pop.example.com` |
| `MAIL_POP_PORT` | `995` (POP3S) |
| `MAIL_POP_ENCRYPTION` | `ssl` / `null` |
| `MAIL_POP_USERNAME` | `user@example.com` |
| `MAIL_POP_PASSWORD` | `***` |

> **個別ユーザーが自分の IMAP/POP3/SMTP を設定** したい場合は `.env` ではなく、ログイン後に `/mail-accounts` (個人メールアカウント) で設定してください。 `.env` のメール設定は **システム全体のデフォルト**として使われます (個別設定が無いユーザー向け)。

#### AI (RAG)

| キー | 例 |
|---|---|
| `ANTHROPIC_API_KEY` | `sk-ant-api03-...` (Claude API を使う場合のみ) |
| `RAG_API_URL` | `http://rag-api:8000` (compose 内のデフォルト、通常変更不要) |

#### 初期管理者 (db:seed 時のみ参照)

| キー | デフォルト |
|---|---|
| `ADMIN_EMAIL` | `admin@example.com` |
| `ADMIN_PASSWORD` | `password` |

`db:seed` の実行前にここを書き換えれば、希望のメアド/パスワードで初期管理者が作られます。一度 seed した後の変更は反映されません (画面の「パスワード変更」を使うこと)。

#### Microsoft 365 OAuth2 (オプション — 個人メールアカウントで M365 を使いたい場合)

| キー | 説明 |
|---|---|
| `MICROSOFT_MAIL_CLIENT_ID` | Azure App Registration の Application (client) ID |
| `MICROSOFT_MAIL_CLIENT_SECRET` | クライアントシークレット |
| `MICROSOFT_MAIL_TENANT_ID` | テナントID (single tenant の場合) / `common` (multi tenant) |
| `MICROSOFT_MAIL_REDIRECT_URI` | `${APP_URL}/mail-accounts/oauth/microsoft/callback` |

未設定でも他機能は動作します。

### 機密情報の取り扱い

- **`.env` を絶対に git commit しないこと** (pre-commit hook でブロックされる、★2参照)
- パスワード変更時は `docker compose restart laravel` でセッション情報も完全リセットすると安心
- 本番運用前に `APP_KEY` を必ず `php artisan key:generate` で再生成

---

## 社内 LAN 公開 (HTTPS + 社内DNS)

開発機の localhost ではなく、社内 LAN の別 PC からも `https://rice.cosy.co.jp` でアクセスできるようにする手順です。WSL2 + Docker + Windows ホストの構成を想定しています。

### 構成図

```
[LAN PC] → 社内DNS (rice.cosy.co.jp → 192.168.x.x)
       ↓ HTTPS:443
[Windows ホスト] (192.168.11.74 / 192.168.100.2)
       ↓ netsh portproxy : 443 → WSL:443
[WSL2 (172.x.x.x)]
       ↓ docker (443:443)
[rice-laravel-1: nginx (TLS終端) + php-fpm]
```

### ① 社内DNS にレコード追加 (Windows Server AD DNS の例)

ドメインコントローラーで管理者 PowerShell を開いて:

```powershell
# cosy.co.jp ゾーンを社内DNSに作成 (split-horizon)
Add-DnsServerPrimaryZone -Name "cosy.co.jp" -ZoneFile "cosy.co.jp.dns" -DynamicUpdate None -ErrorAction SilentlyContinue

# Rice ホストの A レコード (両ネットワーク分)
Add-DnsServerResourceRecordA -Name "rice" -ZoneName "cosy.co.jp" -IPv4Address "192.168.11.74"  -CreatePtr $false
Add-DnsServerResourceRecordA -Name "rice" -ZoneName "cosy.co.jp" -IPv4Address "192.168.100.2" -CreatePtr $false

# 確認
Get-DnsServerResourceRecord -ZoneName "cosy.co.jp" -Name "rice"
```

> **split-horizon の注意**: `cosy.co.jp` を社内 DNS で primary zone として保持する場合、社内では外部公開している www や MX が引けなくなります。外部レコードも転記するか、`rice.cosy.co.jp` だけスタブゾーンで持つか、運用方針に合わせてください。

LAN PC 側の確認:
```cmd
nslookup rice.cosy.co.jp
# → 192.168.11.74 (or 192.168.100.2) が返る
```

### ② Windows ホストの portproxy + Firewall (1回だけ管理者で実行)

WSL2 は NAT 配下のため、外部から WSL に直接届きません。Windows でポート転送 + Firewall 解放が必要です。WSL の IP は再起動で変わるので、 **スケジューラタスクで自動同期** します。

PowerShell を**管理者として実行**で開いて:

```powershell
cd \\wsl.localhost\ubuntu-20.04\home\nakaochi\projects\Rice\scripts\windows
powershell -ExecutionPolicy Bypass -File setup-wsl-portproxy.ps1
```

これで以下が完了します:
- Windows Firewall に **Inbound TCP 80/443** 許可ルールを作成 (`Rice HTTP (80)` / `Rice HTTPS (443)`)
- スケジューラタスク **`Rice-WSL-PortProxy-Refresh`** を登録 (起動時 / ログオン時 / ネットワーク変更時に WSL IP を再検出して portproxy を更新)
- 即時 1 回 portproxy を反映

確認:
```powershell
netsh interface portproxy show v4tov4
# → 0.0.0.0:80 / 0.0.0.0:443 が 172.x.x.x:80/443 に転送されている
```

### ③ TLS 証明書取得 (Let's Encrypt DNS-01 / Route 53)

公開DNS が AWS Route 53 にある前提です。

**1. AWS IAM ユーザー作成**: acme.sh 専用 IAM ユーザーを1つ作り、以下のポリシーをアタッチ:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    { "Effect": "Allow", "Action": ["route53:GetChange"],
      "Resource": "arn:aws:route53:::change/*" },
    { "Effect": "Allow", "Action": ["route53:ChangeResourceRecordSets","route53:ListResourceRecordSets"],
      "Resource": "arn:aws:route53:::hostedzone/<HOSTED_ZONE_ID_OF_cosy.co.jp>" },
    { "Effect": "Allow", "Action": ["route53:ListHostedZones"],
      "Resource": "*" }
  ]
}
```

`<HOSTED_ZONE_ID_OF_cosy.co.jp>` は Route 53 コンソールで対象 Hosted Zone を開いて控えてください。アクセスキーを発行し AKIA... と Secret を控える。

**2. WSL で証明書発行**:

```bash
cd /home/nakaochi/projects/Rice
export AWS_ACCESS_KEY_ID=AKIA...
export AWS_SECRET_ACCESS_KEY=...
bash scripts/issue-cert.sh
```

成功すると:
- `./certs/fullchain.pem` (サーバー証明書 + 中間CA)
- `./certs/privkey.pem` (秘密鍵, mode 600)
- `./certs/.enabled` (nginx の HTTP→HTTPS リダイレクトを有効化するフラグ)

acme.sh は `~/.acme.sh` に自動更新 cron を仕込むので、60日サイクルで自動更新されます。

> **DNS API トークンを使いたくない場合** (`scripts/issue-cert.sh` を使わない場合) は、手動 DNS-01 で acme.sh を回すか、内部 PKI / 自己署名証明書を `certs/fullchain.pem` と `certs/privkey.pem` に置けば動作します (mode 600 にすること)。

### ④ コンテナ再ビルド + .env 切り替え

```bash
# .env を HTTPS URL に切り替え (この変更は既に反映済みかも)
sed -i 's|^APP_URL=.*|APP_URL=https://rice.cosy.co.jp|' laravel/.env
grep -q '^SESSION_SECURE_COOKIE=' laravel/.env || echo 'SESSION_SECURE_COOKIE=true' >> laravel/.env
grep -q '^SESSION_DOMAIN='        laravel/.env || echo 'SESSION_DOMAIN=rice.cosy.co.jp' >> laravel/.env

# コンテナ再ビルド (nginx.conf / Dockerfile / docker-compose.yml の変更を反映)
docker compose up -d --build

# Laravel 側のキャッシュクリア
docker exec -u www-data rice-laravel-1 php artisan config:clear
docker exec -u www-data rice-laravel-1 php artisan config:cache
```

### ⑤ 動作確認

```bash
# Windows ホストから
curl -v https://rice.cosy.co.jp 2>&1 | head -30
# 別 LAN PC から
# ブラウザで https://rice.cosy.co.jp → ログイン画面が表示されれば成功
```

メール送信テスト: ログイン後、パスワードを忘れた → メール送信 → リンクが `https://rice.cosy.co.jp/reset-password/...` になっていれば OK。

### トラブルシューティング (LAN公開)

| 症状 | 原因 | 対処 |
|---|---|---|
| `nslookup rice.cosy.co.jp` が返らない | 社内DNS未設定 / クライアントPCのDNSが社内DNSを向いていない | DNS Server 設定の見直し、または `hosts` ファイルで一時凌ぎ |
| `curl` で接続不可 | Windows Firewall / portproxy | `Test-NetConnection rice.cosy.co.jp -Port 443` で疎通確認 |
| WSL 再起動後にアクセス不可 | portproxy がスケジューラで更新されない | `Get-ScheduledTask Rice-WSL-PortProxy-Refresh` で確認、手動で `refresh-wsl-portproxy.ps1` を実行 |
| ブラウザで「証明書が無効」 | self-signed フォールバックが当たっている | `./certs/fullchain.pem` が LE 由来か確認 (`openssl x509 -in certs/fullchain.pem -noout -issuer` → `Let's Encrypt` なら OK) |
| HTTPS でログイン後に redirect ループ | `SESSION_SECURE_COOKIE=true` なのにアクセスが http | URL を https に統一、Cookieも削除して再ログイン |
| メールリンクが `http://localhost` | `APP_URL` がまだ localhost | `.env` を `https://rice.cosy.co.jp` に変更 + `config:clear` |
| 招待リンクで CSRF / 419 | `SESSION_DOMAIN` 不一致 | `SESSION_DOMAIN` を実際にアクセスするドメインに合わせる |

### 元に戻す (localhost に戻す)

```bash
sed -i 's|^APP_URL=.*|APP_URL=http://localhost|' laravel/.env
sed -i 's|^SESSION_SECURE_COOKIE=true|SESSION_SECURE_COOKIE=false|' laravel/.env
sed -i 's|^SESSION_DOMAIN=rice.cosy.co.jp||' laravel/.env
docker exec -u www-data rice-laravel-1 php artisan config:clear

# portproxy / Firewall も削除したい場合
# (管理者 PowerShell で)
# Unregister-ScheduledTask -TaskName 'Rice-WSL-PortProxy-Refresh' -Confirm:$false
# netsh interface portproxy delete v4tov4 listenport=80 listenaddress=0.0.0.0
# netsh interface portproxy delete v4tov4 listenport=443 listenaddress=0.0.0.0
# Remove-NetFirewallRule -DisplayName 'Rice HTTP (80)','Rice HTTPS (443)'
```
