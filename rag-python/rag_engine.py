import os
from typing import Optional
from llama_index.core import StorageContext, VectorStoreIndex, Document
from llama_index.vector_stores.postgres import PGVectorStore
import psycopg2

class RAGEngine:
    def __init__(self):
        self.db_name = os.getenv("POSTGRES_DB", "rice_vector")
        self.host = os.getenv("POSTGRES_HOST", "postgres")
        self.password = os.getenv("POSTGRES_PASSWORD", "rice_secret")
        self.user = os.getenv("POSTGRES_USER", "rice")
        self.port = os.getenv("POSTGRES_PORT", "5432")
        self.ollama_url = os.getenv("OLLAMA_URL", "http://ollama:11434")

    def get_vector_store(self):
        return PGVectorStore.from_params(
            host=self.host,
            port=self.port,
            user=self.user,
            password=self.password,
            database=self.db_name,
            table_name="ext_knowledge_embeddings",
            embed_dim=1536 # OpenAI等の次元数に合わせて調整
        )

    def add_documents(self, scraped_data):
        documents = [Document(text=d['content'], extra_info={"url": d['url'], "title": d['title']}) for d in scraped_data]

        vector_store = self.get_vector_store()
        storage_context = StorageContext.from_defaults(vector_store=vector_store)

        # インデックスの作成と保存
        index = VectorStoreIndex.from_documents(
            documents, storage_context=storage_context, show_progress=True
        )
        return True

    def _build_llm(
        self,
        provider: Optional[str],
        model: Optional[str],
        anthropic_api_key: Optional[str],
        gemini_api_key: Optional[str],
    ):
        """provider 指定に応じて llama-index 用 LLM インスタンスを返す。
        provider が未指定の場合は環境変数 LLM_PROVIDER → 既定 (None) に従う。
        None を返した場合、呼び出し側で llama-index デフォルト LLM を利用する。
        """
        if not provider:
            provider = os.getenv("LLM_PROVIDER")
        if not provider:
            return None

        provider = provider.lower()

        if provider == "ollama":
            from llama_index.llms.ollama import Ollama
            return Ollama(
                model=model or os.getenv("OLLAMA_MODEL", "llama3.1"),
                base_url=self.ollama_url,
                request_timeout=120.0,
            )

        if provider == "claude":
            from llama_index.llms.anthropic import Anthropic
            api_key = anthropic_api_key or os.getenv("ANTHROPIC_API_KEY")
            if not api_key:
                raise ValueError("Anthropic API キーが指定されていません。")
            return Anthropic(
                model=model or os.getenv("CLAUDE_MODEL", "claude-sonnet-4-6"),
                api_key=api_key,
            )

        if provider == "gemini":
            from llama_index.llms.google_genai import GoogleGenAI
            api_key = gemini_api_key or os.getenv("GEMINI_API_KEY")
            if not api_key:
                raise ValueError("Gemini API キーが指定されていません。")
            return GoogleGenAI(
                model=model or os.getenv("GEMINI_MODEL", "gemini-1.5-flash"),
                api_key=api_key,
            )

        raise ValueError(f"未対応の provider です: {provider}")

    def query(
        self,
        query_text: str,
        top_k: int = 5,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        anthropic_api_key: Optional[str] = None,
        gemini_api_key: Optional[str] = None,
    ):
        vector_store = self.get_vector_store()
        index = VectorStoreIndex.from_vector_store(vector_store)

        llm = self._build_llm(provider, model, anthropic_api_key, gemini_api_key)

        engine_kwargs = {"similarity_top_k": top_k}
        if llm is not None:
            engine_kwargs["llm"] = llm

        query_engine = index.as_query_engine(**engine_kwargs)
        response = query_engine.query(query_text)

        return {
            "answer": str(response),
            "sources": [
                {
                    "text": n.node.get_content(),
                    "score": n.score,
                    "url": (n.node.extra_info or {}).get("url"),
                }
                for n in response.source_nodes
            ],
        }
