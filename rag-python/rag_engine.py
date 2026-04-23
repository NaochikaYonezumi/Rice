import os
import logging
from typing import Optional
import chromadb
from chromadb.config import Settings

logger = logging.getLogger(__name__)

CHROMA_HOST = os.getenv("CHROMA_HOST", "localhost")
CHROMA_PORT = int(os.getenv("CHROMA_PORT", "8001"))
LLM_PROVIDER = os.getenv("LLM_PROVIDER", "ollama")


def _get_embed_model():
    from llama_index.embeddings.huggingface import HuggingFaceEmbedding
    return HuggingFaceEmbedding(model_name="BAAI/bge-small-en-v1.5")


def _get_llm():
    if LLM_PROVIDER == "claude":
        from llama_index.llms.anthropic import Anthropic
        api_key = os.getenv("ANTHROPIC_API_KEY")
        if not api_key:
            raise ValueError("ANTHROPIC_API_KEY is not set")
        model = os.getenv("CLAUDE_MODEL", "claude-sonnet-4-6")
        return Anthropic(model=model, api_key=api_key)
    else:
        from llama_index.llms.ollama import Ollama
        ollama_url = os.getenv("OLLAMA_URL", "http://localhost:11434")
        return Ollama(model="llama3.1", base_url=ollama_url, request_timeout=120.0)


class RAGEngine:
    def __init__(self):
        self.chroma_client = chromadb.HttpClient(
            host=CHROMA_HOST,
            port=CHROMA_PORT,
            settings=Settings(anonymized_telemetry=False),
        )
        self.embed_model = _get_embed_model()
        self.llm = _get_llm()
        logger.info(f"RAGEngine initialized with provider={LLM_PROVIDER}")

    def _get_or_create_collection(self, name: str):
        return self.chroma_client.get_or_create_collection(
            name=name,
            metadata={"hnsw:space": "cosine"},
        )

    def add_documents(self, chunks: list[str], source_url: str, collection: str = "default") -> int:
        col = self._get_or_create_collection(collection)
        embeddings = [self.embed_model.get_text_embedding(c) for c in chunks]
        ids = [f"{source_url}::{i}" for i in range(len(chunks))]
        col.upsert(
            ids=ids,
            documents=chunks,
            embeddings=embeddings,
            metadatas=[{"source": source_url} for _ in chunks],
        )
        return len(chunks)

    def query(self, question: str, top_k: int = 5, collection: str = "default",
              provider: str | None = None, model: str | None = None,
              anthropic_api_key: str | None = None,
              gemini_api_key: str | None = None) -> tuple[str, list[str]]:
        col = self._get_or_create_collection(collection)
        q_embedding = self.embed_model.get_text_embedding(question)
        results = col.query(query_embeddings=[q_embedding], n_results=top_k)

        docs = results.get("documents", [[]])[0]
        metas = results.get("metadatas", [[]])[0]
        sources = list({m.get("source", "") for m in metas if m.get("source")})

        if not docs:
            return "関連するドキュメントが見つかりませんでした。", []

        context = "\n\n---\n\n".join(docs)
        prompt = (
            "以下のコンテキストを参照して質問に答えてください。\n\n"
            f"コンテキスト:\n{context}\n\n"
            f"質問: {question}\n\n"
            "回答:"
        )

        effective_provider = provider or LLM_PROVIDER
        if effective_provider == "claude":
            answer = self._call_claude(prompt, model=model, api_key=anthropic_api_key)
        elif effective_provider == "gemini":
            answer = self._call_gemini(prompt, model=model, api_key=gemini_api_key)
        else:
            answer = self._call_ollama(prompt, model=model)

        return answer, sources

    def _call_ollama(self, prompt: str, model: str | None = None) -> str:
        import requests
        ollama_url = os.getenv("OLLAMA_URL", "http://localhost:11434")
        effective_model = model or os.getenv("OLLAMA_MODEL", "llama3.1")
        response = requests.post(
            f"{ollama_url}/api/generate",
            json={"model": effective_model, "prompt": prompt, "stream": False},
            timeout=120,
        )
        response.raise_for_status()
        return response.json().get("response", "")

    def _call_claude(self, prompt: str, model: str | None = None, api_key: str | None = None) -> str:
        import anthropic
        api_key = api_key or os.getenv("ANTHROPIC_API_KEY")
        if not api_key:
            raise ValueError("ANTHROPIC_API_KEY が設定されていません。AI設定画面で設定してください。")
        effective_model = model or os.getenv("CLAUDE_MODEL", "claude-sonnet-4-6")
        client = anthropic.Anthropic(api_key=api_key)
        message = client.messages.create(
            model=effective_model,
            max_tokens=1024,
            messages=[{"role": "user", "content": prompt}],
        )
        return message.content[0].text

    def _call_gemini(self, prompt: str, model: str | None = None, api_key: str | None = None) -> str:
        import google.generativeai as genai
        api_key = api_key or os.getenv("GOOGLE_API_KEY")
        if not api_key:
            raise ValueError("GOOGLE_API_KEY が設定されていません。AI設定画面で設定してください。")
        effective_model = model or os.getenv("GEMINI_MODEL", "gemini-2.0-flash")
        genai.configure(api_key=api_key)
        gemini = genai.GenerativeModel(effective_model)
        response = gemini.generate_content(prompt)
        return response.text

    def delete_source(self, source_url: str, collection: str = "default") -> int:
        col = self._get_or_create_collection(collection)
        results = col.get(where={"source": source_url})
        ids = results.get("ids", [])
        if ids:
            col.delete(ids=ids)
        return len(ids)

    def delete_collection(self, name: str):
        self.chroma_client.delete_collection(name)
