from fastapi import FastAPI, BackgroundTasks
from pydantic import BaseModel
from typing import Optional
from scraper import KnowledgeScraper
from rag_engine import RAGEngine
import os
import requests

app = FastAPI()
rag_engine = RAGEngine()

class ScrapeRequest(BaseModel):
    url: str
    max_depth: int = 2

class QueryRequest(BaseModel):
    query: str
    top_k: Optional[int] = 5
    provider: Optional[str] = None  # "ollama" | "claude" | "gemini"
    model: Optional[str] = None
    anthropic_api_key: Optional[str] = None
    gemini_api_key: Optional[str] = None

def process_scrape(url: str, max_depth: int):
    scraper = KnowledgeScraper(url)
    scraper.scrape(max_depth=max_depth)
    data = scraper.get_results()
    if data:
        rag_engine.add_documents(data)

@app.post("/scrape")
async def start_scrape(request: ScrapeRequest, background_tasks: BackgroundTasks):
    background_tasks.add_task(process_scrape, request.url, request.max_depth)
    return {"status": "accepted", "message": "Scraping started in background"}

@app.post("/query")
async def query_knowledge(request: QueryRequest):
    result = rag_engine.query(
        request.query,
        top_k=request.top_k or 5,
        provider=request.provider,
        model=request.model,
        anthropic_api_key=request.anthropic_api_key,
        gemini_api_key=request.gemini_api_key,
    )
    return result

@app.get("/models")
async def get_models():
    """利用可能なモデル一覧を返す。Ollama はサーバーから動的に取得。"""
    ollama_url = os.getenv("OLLAMA_URL", "http://ollama:11434")
    ollama_models = []
    try:
        res = requests.get(f"{ollama_url}/api/tags", timeout=3)
        if res.ok:
            data = res.json()
            ollama_models = [m.get("name") for m in data.get("models", []) if m.get("name")]
    except Exception:
        # Ollama が起動していない場合はフォールバック
        ollama_models = ["llama3.1", "mistral", "phi3"]

    return {
        "ollama": ollama_models,
        "claude": [
            {"id": "claude-sonnet-4-6", "name": "Claude Sonnet 4.6"},
            {"id": "claude-opus-4-7", "name": "Claude Opus 4.7"},
            {"id": "claude-haiku-4-5-20251001", "name": "Claude Haiku 4.5"},
        ],
        "gemini": [
            {"id": "gemini-1.5-pro", "name": "Gemini 1.5 Pro"},
            {"id": "gemini-1.5-flash", "name": "Gemini 1.5 Flash"},
        ],
    }

@app.get("/health")
async def health():
    return {"status": "ok"}
