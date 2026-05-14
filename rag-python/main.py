from fastapi import FastAPI, BackgroundTasks, HTTPException, Query, UploadFile, File, Form
from pydantic import BaseModel, Field
from typing import Optional
from scraper import KnowledgeScraper
from rag_engine import RAGEngine
from document_parser import parse_file
import os
import logging
import requests

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("rag_api")

app = FastAPI()
rag_engine = RAGEngine()

OLLAMA_URL = os.getenv("OLLAMA_URL", "http://ollama:11434")


def translate_llm_error(exc: Exception) -> Optional[HTTPException]:
    """LLM プロバイダ由来の既知エラーを日本語メッセージ + 適切な HTTP ステータスに変換。
    未知のエラーは None を返す（呼び出し側で 500 にフォールバック）。"""
    msg = str(exc)
    low = msg.lower()

    if "credit balance is too low" in low or "insufficient_quota" in low:
        return HTTPException(status_code=402, detail={
            "error_code": "insufficient_credits", "provider": "claude",
            "message": "Claude API のクレジット残高が不足しています。Anthropic コンソール（Plans & Billing）でクレジットを購入してください。",
        })
    # Gemini 前払いクレジット枯渇: 429 で返ってくるがレート制限ではなく残高ゼロが原因
    if "prepayment credits are depleted" in low or "prepayment credits" in low:
        return HTTPException(status_code=402, detail={
            "error_code": "insufficient_credits", "provider": "gemini",
            "message": "Gemini API の前払いクレジット残高が枯渇しています。AI Studio (https://ai.studio/projects) で対象プロジェクトの請求・チャージ状況を確認してください。",
            "raw": str(msg)[:600],
        })
    if "invalid x-api-key" in low or "authentication_error" in low or "incorrect api key" in low:
        return HTTPException(status_code=401, detail={
            "error_code": "invalid_api_key",
            "message": "APIキーが無効です。AI設定画面でキーを確認してください。",
        })
    if "rate_limit_error" in low or "resource_exhausted" in low or "rate limit" in low or "quota" in low or "too many requests" in low:
        # Gemini Free Tier (15 RPM) を疑うヒントを含める
        is_gemini = "google" in low or "gemini" in low or "generativelanguage" in low
        hint = ""
        if is_gemini:
            hint = " Gemini Free Tier は 15 RPM の制限があります。AI Studio で課金を有効化したプロジェクトの API キーを AI設定画面で使用してください。"
        # 生エラーの先頭を一緒に返す (どのクォータ・どのプロジェクトかを特定するため)
        raw = str(msg)[:600]
        return HTTPException(status_code=429, detail={
            "error_code": "rate_limited",
            "message": "API のレート制限に達しました。しばらく時間をおいて再試行してください。" + hint,
            "raw": raw,
        })
    if "model" in low and "not found" in low:
        if "models/gemini" in low or "v1beta" in low:
            hint = "AI設定画面で最新の Gemini モデルを選び直してください。"
        elif "ollama" in low or "/api/" in low:
            hint = "Ollama に未インストールです。`ollama pull <model>` で取得してください。"
        else:
            hint = "AI設定画面で有効なモデルを選び直してください。"
        return HTTPException(status_code=404, detail={
            "error_code": "model_not_found",
            "message": f"指定されたモデルが見つかりませんでした。{hint}",
        })
    return None


def fetch_ollama_models() -> list:
    try:
        r = requests.get(f"{OLLAMA_URL}/api/tags", timeout=5)
        r.raise_for_status()
        return [m["name"] for m in r.json().get("models", [])]
    except Exception:
        return []


class ScrapeRequest(BaseModel):
    url: str
    max_depth: int = 2
    max_pages: int = 30
    collection: Optional[str] = None  # 受け付けるが現状は未使用 (将来用)


class QueryRequest(BaseModel):
    """/query エンドポイントへのリクエスト。

    互換性のため `query` または `question` のどちらか一方を受け付ける。
    Laravel 側 (RagApiService) は `query` を使う。
    """
    query: Optional[str] = None
    question: Optional[str] = None
    top_k: Optional[int] = 5
    provider: Optional[str] = None  # "ollama" | "claude" | "gemini"
    model: Optional[str] = None
    collection: Optional[str] = None
    anthropic_api_key: Optional[str] = None
    gemini_api_key: Optional[str] = None

    def text(self) -> str:
        return (self.query or self.question or "").strip()


def process_scrape(url: str, max_depth: int, max_pages: int = 30):
    scraper = KnowledgeScraper(url, max_pages=max_pages)
    scraper.scrape(max_depth=max_depth)
    data = scraper.get_results()
    if data:
        rag_engine.add_documents(data)


def run_scrape_sync(url: str, max_depth: int, max_pages: int = 30) -> int:
    """同期スクレイピング。インデックス化したドキュメント数を返す。"""
    scraper = KnowledgeScraper(url, max_pages=max_pages)
    scraper.scrape(max_depth=max_depth)
    data = scraper.get_results()
    if not data:
        return 0
    rag_engine.add_documents(data)
    return len(data)


@app.post("/scrape")
async def start_scrape(request: ScrapeRequest, background_tasks: BackgroundTasks):
    """非同期 (バックグラウンド) でクロールを開始する。"""
    background_tasks.add_task(process_scrape, request.url, request.max_depth, request.max_pages)
    return {"status": "accepted", "message": "Scraping started in background"}


@app.post("/scrape/sync")
async def scrape_sync(request: ScrapeRequest):
    """同期クロール。完了時にインデックス化件数を返す。"""
    try:
        chunks = run_scrape_sync(request.url, request.max_depth, request.max_pages)
        return {"status": "ok", "chunks_indexed": chunks}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.delete("/sources")
async def delete_source(url: str = Query(..., description="削除対象のベースURL")):
    """指定 URL 配下のドキュメントを vector DB から削除する。"""
    try:
        deleted = rag_engine.delete_by_url(url)
        return {"status": "ok", "deleted": deleted}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/sources/refresh")
async def refresh_source(request: ScrapeRequest):
    """既存ドキュメントを削除してから再クロールする。"""
    try:
        deleted = rag_engine.delete_by_url(request.url)
        chunks = run_scrape_sync(request.url, request.max_depth, request.max_pages)
        return {"status": "ok", "deleted": deleted, "chunks_indexed": chunks}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/sources")
async def list_sources():
    """インデックス済みの URL 一覧を返す (vector DB から集約)。"""
    try:
        rows = rag_engine.list_sources()
        return {"sources": rows}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/documents/upload")
async def upload_document(
    file: UploadFile = File(...),
    source_id: str = Form(...),
    title: Optional[str] = Form(None),
    collection: Optional[str] = Form(None),
):
    """ファイルをアップロードしてベクター DB にインデックスする。
    - file: 添付ファイル (PDF/DOCX/MD/TXT 等)
    - source_id: 一意な識別子。`url` フィールドとして DB に格納される (例: file://uploads/abc.pdf)
    - title: 表示名 (任意)
    - collection: コレクション名 (任意)

    レスポンス: { status, chunks_indexed }
    """
    try:
        content = await file.read()
        mime = file.content_type or "application/octet-stream"
        text = parse_file(content, file.filename, mime)
        if not text or not text.strip():
            raise HTTPException(status_code=422, detail={
                "error_code": "parse_empty",
                "message": f"ファイルからテキストを抽出できませんでした: {file.filename}",
            })
        # 既存ドキュメントを削除してから追加 (重複防止)
        try:
            rag_engine.delete_by_url(source_id)
        except Exception:
            pass
        rag_engine.add_documents([{
            "url": source_id,
            "content": text,
            "title": title or file.filename,
            "base_url": source_id,
        }])
        return {
            "status": "ok",
            "chunks_indexed": 1,
            "title": title or file.filename,
            "extracted_text": text,
        }
    except HTTPException:
        raise
    except Exception as e:
        logger.error("upload_document failed: %r", e, exc_info=True)
        raise HTTPException(status_code=500, detail={
            "error_code": "upload_failed",
            "message": f"ドキュメント取り込みに失敗しました: {e}",
        })


@app.get("/sources/text")
def get_source_text(source_id: str = Query(..., description="source_id (= url field)")):
    """指定 source_id に紐づくチャンク本文を結合して返す。"""
    try:
        text = rag_engine.get_source_text(source_id)
        return {"source_id": source_id, "text": text or ""}
    except Exception as e:
        logger.error("get_source_text failed: %r", e, exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


class TextDocumentRequest(BaseModel):
    source_id: str         # 一意な識別子 (`url` カラムに格納)
    title: Optional[str] = None
    content: str           # インデックスする本文
    collection: Optional[str] = None


@app.post("/documents/text")
def index_text_document(request: TextDocumentRequest):
    """テキスト本文を直接ベクター DB にインデックスする (メール本文等の用途向け)。"""
    if not (request.content or "").strip():
        raise HTTPException(status_code=422, detail={
            "error_code": "empty_content",
            "message": "本文が空です。",
        })
    try:
        try:
            rag_engine.delete_by_url(request.source_id)
        except Exception:
            pass
        rag_engine.add_documents([{
            "url": request.source_id,
            "content": request.content,
            "title": request.title or request.source_id,
            "base_url": request.source_id,
        }])
        return {"status": "ok", "chunks_indexed": 1, "title": request.title}
    except Exception as e:
        logger.error("index_text_document failed: %r", e, exc_info=True)
        raise HTTPException(status_code=500, detail={
            "error_code": "index_failed",
            "message": f"インデックス化に失敗しました: {e}",
        })


@app.post("/query")
def query_knowledge(request: QueryRequest):
    # 注: `def` (非 async) で定義することで FastAPI がスレッドプールで実行する。
    # llama-index の GoogleGenAI など、内部で asyncio.run() を呼ぶ LLM クライアントが
    # 「running event loop からは asyncio.run() を呼べない」エラーになるのを回避する。
    text = request.text()
    if not text:
        raise HTTPException(status_code=422, detail="'query' フィールドが必須です。")
    try:
        result = rag_engine.query(
            text,
            top_k=request.top_k or 5,
            provider=request.provider,
            model=request.model,
            anthropic_api_key=request.anthropic_api_key,
            gemini_api_key=request.gemini_api_key,
        )
        return result
    except HTTPException:
        raise
    except Exception as e:
        # 生エラーをログに残す (どのクォータ・どのプロジェクトかを特定するため)
        logger.error("query failed: provider=%s model=%s error=%r", request.provider, request.model, e, exc_info=True)
        translated = translate_llm_error(e)
        if translated is not None:
            raise translated
        raise HTTPException(status_code=500, detail={
            "error_code": "internal_error",
            "message": str(e),
        })


@app.get("/models")
async def get_models():
    return {
        "ollama": fetch_ollama_models(),
        "claude": [
            {"id": "claude-opus-4-7", "name": "Claude Opus 4.7"},
            {"id": "claude-sonnet-4-6", "name": "Claude Sonnet 4.6"},
            {"id": "claude-haiku-4-5-20251001", "name": "Claude Haiku 4.5"}
        ],
        "gemini": [
            {"id": "gemini-2.5-pro", "name": "Gemini 2.5 Pro"},
            {"id": "gemini-2.5-flash", "name": "Gemini 2.5 Flash"},
            {"id": "gemini-2.5-flash-lite", "name": "Gemini 2.5 Flash-Lite"},
            {"id": "gemini-2.0-flash", "name": "Gemini 2.0 Flash"},
            {"id": "gemini-2.0-flash-lite", "name": "Gemini 2.0 Flash-Lite"}
        ]
    }


@app.get("/health")
async def health():
    return {"status": "ok"}
