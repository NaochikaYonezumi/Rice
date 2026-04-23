#!/bin/bash
set -e

echo "=== RAG Mail System Setup ==="

# .env 作成
if [ ! -f laravel/.env ]; then
    cp laravel/.env.example laravel/.env
    echo "[OK] laravel/.env created"
fi

# Docker Compose ビルド & 起動
docker compose build
docker compose up -d

echo "Waiting for services to start..."
sleep 15

# Laravel セットアップ
docker compose exec laravel php artisan key:generate --ansi
docker compose exec laravel php artisan migrate --force
docker compose exec laravel php artisan storage:link

# Ollama モデルダウンロード（軽量モデル）
echo ""
echo "Pulling Ollama model (llama3.2)..."
docker compose exec ollama ollama pull llama3.2

echo ""
echo "=== Setup complete ==="
echo ""
echo "  メール一覧:       http://localhost"
echo "  ドキュメント:     http://localhost/documents"
echo "  RAG Chat:        http://localhost/chat"
echo "  スクレイピング:   http://localhost/scrape"
echo "  RAG API:         http://localhost:8000"
echo "  ChromaDB:        http://localhost:8001"
echo ""
echo "=== POP3設定 ==="
echo "  laravel/.env に以下を設定してください:"
echo "    MAIL_POP_HOST=pop.gmail.com"
echo "    MAIL_POP_USERNAME=your@gmail.com"
echo "    MAIL_POP_PASSWORD=your_app_password"
echo ""
echo "=== Claude API に切り替える場合 ==="
echo "  docker-compose.yml の rag-api 環境変数:"
echo "    LLM_PROVIDER=claude"
echo "    ANTHROPIC_API_KEY=sk-ant-..."
echo "  docker compose restart rag-api"
