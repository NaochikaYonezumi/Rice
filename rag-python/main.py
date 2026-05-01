from fastapi import FastAPI, BackgroundTasks
from pydantic import BaseModel
from scraper import KnowledgeScraper
from rag_engine import RAGEngine
import os

app = FastAPI()
rag_engine = RAGEngine()

class ScrapeRequest(BaseModel):
    url: str
    max_depth: int = 2

class QueryRequest(BaseModel):
    query: str

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
    result = rag_engine.query(request.query)
    return result

@app.get("/models")
async def get_models():
    # Return some default models for now
    # In a real app, this would query Ollama or other providers
    return {
        "ollama": ["llama3.1", "mistral", "phi3"],
        "claude": [
            {"id": "claude-3-5-sonnet-20240620", "name": "Claude 3.5 Sonnet"},
            {"id": "claude-3-opus-20240229", "name": "Claude 3 Opus"}
        ],
        "gemini": [
            {"id": "gemini-1.5-pro", "name": "Gemini 1.5 Pro"},
            {"id": "gemini-1.5-flash", "name": "Gemini 1.5 Flash"}
        ]
    }

@app.get("/health")
async def health():
    return {"status": "ok"}
