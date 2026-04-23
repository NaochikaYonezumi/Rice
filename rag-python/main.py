import os
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from contextlib import asynccontextmanager

from rag_engine import RAGEngine
from scraper import scrape_url
from document_parser import parse_file

rag_engine: RAGEngine = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    global rag_engine
    rag_engine = RAGEngine()
    yield


app = FastAPI(title="RAG API", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class QueryRequest(BaseModel):
    question: str
    top_k: int = 5
    provider: str | None = None  # "ollama", "claude", "gemini"
    model: str | None = None
    anthropic_api_key: str | None = None
    gemini_api_key: str | None = None


class QueryResponse(BaseModel):
    answer: str
    sources: list[str] = []


class ScrapeRequest(BaseModel):
    url: str
    collection: str = "default"


class ScrapeResponse(BaseModel):
    status: str
    chunks_added: int
    url: str


@app.get("/health")
async def health():
    return {"status": "ok", "provider": os.getenv("LLM_PROVIDER", "ollama")}


@app.get("/models")
async def list_models():
    import requests as req
    ollama_models = []
    try:
        r = req.get(f"{os.getenv('OLLAMA_URL', 'http://ollama:11434')}/api/tags", timeout=5)
        if r.ok:
            ollama_models = [m["name"] for m in r.json().get("models", [])]
    except Exception:
        pass

    claude_models = [
        {"id": "claude-haiku-4-5-20251001", "name": "Claude Haiku (速い・安価)"},
        {"id": "claude-sonnet-4-6", "name": "Claude Sonnet (バランス)"},
        {"id": "claude-opus-4-7", "name": "Claude Opus (高精度)"},
    ]

    gemini_models = [
        {"id": "gemini-2.0-flash", "name": "Gemini 2.0 Flash (速い)"},
        {"id": "gemini-1.5-flash", "name": "Gemini 1.5 Flash (バランス)"},
        {"id": "gemini-1.5-pro", "name": "Gemini 1.5 Pro (高精度)"},
    ]

    return {
        "ollama": ollama_models,
        "claude": claude_models,
        "gemini": gemini_models,
        "has_claude_key": bool(os.getenv("ANTHROPIC_API_KEY")),
        "has_gemini_key": bool(os.getenv("GOOGLE_API_KEY")),
    }


@app.post("/query", response_model=QueryResponse)
async def query(request: QueryRequest):
    try:
        answer, sources = rag_engine.query(
            request.question,
            top_k=request.top_k,
            provider=request.provider,
            model=request.model,
            anthropic_api_key=request.anthropic_api_key,
            gemini_api_key=request.gemini_api_key,
        )
        return QueryResponse(answer=answer, sources=sources)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/scrape", response_model=ScrapeResponse)
async def scrape(request: ScrapeRequest):
    try:
        chunks = await scrape_url(request.url)
        if not chunks:
            raise HTTPException(status_code=422, detail="No content extracted from URL")
        count = rag_engine.add_documents(chunks, source_url=request.url, collection=request.collection)
        return ScrapeResponse(status="ok", chunks_added=count, url=request.url)
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/ingest-file")
async def ingest_file(
    file: UploadFile = File(...),
    collection: str = Form("documents"),
    document_id: str = Form(""),
):
    content = await file.read()
    mime = file.content_type or "application/octet-stream"
    filename = file.filename or "unknown"

    text = parse_file(content, filename, mime)
    if not text:
        raise HTTPException(status_code=422, detail=f"Could not extract text from {filename}")

    source_id = f"doc:{document_id}:{filename}" if document_id else f"doc:{filename}"
    chunks = _split_text(text)
    count = rag_engine.add_documents(chunks, source_url=source_id, collection=collection)

    return {
        "status": "ok",
        "chunks_added": count,
        "filename": filename,
        "extracted_text": text[:500] + "..." if len(text) > 500 else text,
    }


def _split_text(text: str, size: int = 800, overlap: int = 100) -> list[str]:
    words = text.split()
    chunks = []
    i = 0
    while i < len(words):
        chunk = " ".join(words[i: i + size])
        if chunk.strip():
            chunks.append(chunk.strip())
        i += size - overlap
    return chunks


@app.delete("/collection/{collection_name}")
async def delete_collection(collection_name: str):
    try:
        rag_engine.delete_collection(collection_name)
        return {"status": "deleted", "collection": collection_name}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


class DeleteSourceRequest(BaseModel):
    source_url: str
    collection: str = "default"


@app.post("/delete-source")
async def delete_source(request: DeleteSourceRequest):
    try:
        deleted = rag_engine.delete_source(request.source_url, request.collection)
        return {"status": "deleted", "deleted": deleted}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
