import os
import time
import logging
from typing import Optional
from llama_index.core import StorageContext, VectorStoreIndex, Document, Settings
from llama_index.vector_stores.postgres import PGVectorStore
import psycopg2

logger = logging.getLogger(__name__)

# ===============================================================
# 埋め込みモデル設定 (モジュール読み込み時に一度だけ実行)
#
# デフォルトは多言語対応の軽量モデル `intfloat/multilingual-e5-small`
# (384 次元、~120MB)。CPU で十分動作する。
# 環境変数 EMBED_MODEL で上書き可能。
# ===============================================================
_EMBED_MODEL_NAME = os.getenv("EMBED_MODEL", "intfloat/multilingual-e5-small")
_EMBED_DIM = int(os.getenv("EMBED_DIM", "384"))

try:
    from llama_index.embeddings.huggingface import HuggingFaceEmbedding
    Settings.embed_model = HuggingFaceEmbedding(model_name=_EMBED_MODEL_NAME)
    logger.info(f"embed_model: {_EMBED_MODEL_NAME} (dim={_EMBED_DIM})")
except Exception as e:
    logger.warning(f"HuggingFaceEmbedding init failed: {e}; falling back to llama-index default")


class RAGEngine:
    # llama_index PGVectorStore は内部で 'data_<table_name>' というテーブルを生成する
    # 旧 1536 次元テーブル (ext_knowledge_embeddings) とは別物
    TABLE_NAME = os.getenv("VECTOR_TABLE_NAME", "ext_knowledge_v2")
    DATA_TABLE = f"data_{TABLE_NAME}"
    EMBED_DIM = _EMBED_DIM

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
            table_name=self.TABLE_NAME,
            embed_dim=self.EMBED_DIM,
        )

    def _connect(self):
        return psycopg2.connect(
            host=self.host,
            port=self.port,
            user=self.user,
            password=self.password,
            dbname=self.db_name,
        )

    def add_documents(self, scraped_data):
        documents = [
            Document(
                text=d['content'],
                extra_info={
                    "url": d['url'],
                    "title": d['title'],
                    "base_url": d.get('base_url', d['url']),
                },
            )
            for d in scraped_data
        ]

        vector_store = self.get_vector_store()
        storage_context = StorageContext.from_defaults(vector_store=vector_store)

        index = VectorStoreIndex.from_documents(
            documents, storage_context=storage_context, show_progress=True
        )
        return True

    # ---------------------------------------------------------------
    # LLM 構築
    # ---------------------------------------------------------------
    def _build_llm(
        self,
        provider: Optional[str],
        model: Optional[str],
        anthropic_api_key: Optional[str],
        gemini_api_key: Optional[str],
    ):
        """provider 指定に応じて llama-index 用 LLM インスタンスを返す。

        provider 未指定の場合は環境変数 LLM_PROVIDER を見る。
        provider が解決できない / "none" の場合は None を返し、
        呼び出し側で「LLM なしフォールバック」を使う。
        """
        if not provider:
            provider = os.getenv("LLM_PROVIDER")
        if not provider:
            return None

        provider = provider.lower()
        if provider in ("none", "off", "disabled"):
            return None

        if provider == "ollama":
            from llama_index.llms.ollama import Ollama
            return Ollama(
                model=model or os.getenv("OLLAMA_MODEL", "llama3.1"),
                base_url=self.ollama_url,
                request_timeout=180.0,
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
                model=model or os.getenv("GEMINI_MODEL", "gemini-2.5-flash"),
                api_key=api_key,
            )

        raise ValueError(f"未対応の provider です: {provider}")

    # ---------------------------------------------------------------
    # クエリ
    # ---------------------------------------------------------------
    def query(
        self,
        query_text: str,
        top_k: int = 5,
        provider: Optional[str] = None,
        model: Optional[str] = None,
        anthropic_api_key: Optional[str] = None,
        gemini_api_key: Optional[str] = None,
    ):
        llm = self._build_llm(provider, model, anthropic_api_key, gemini_api_key)

        # LLM が未構築 (=None) で、かつ vector store もまだ作られていない場合に
        # llama-index のデフォルト LLM (OpenAI) に落ちて API キー不足で 500 に
        # なるのを避けるため、直接 LLM 呼び出しでフォールバックする。
        if llm is None:
            return self._direct_llm_only_query(query_text, top_k, provider, model, anthropic_api_key, gemini_api_key)

        try:
            vector_store = self.get_vector_store()
            index = VectorStoreIndex.from_vector_store(vector_store)
            query_engine = index.as_query_engine(similarity_top_k=top_k, llm=llm)
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
        except Exception:
            # vector store が空 / 取得に失敗した場合は LLM 単体で応答する
            return self._direct_llm_only_query(query_text, top_k, provider, model, anthropic_api_key, gemini_api_key, llm=llm)

    def _direct_llm_only_query(
        self,
        query_text: str,
        top_k: int,
        provider: Optional[str],
        model: Optional[str],
        anthropic_api_key: Optional[str],
        gemini_api_key: Optional[str],
        llm=None,
    ):
        """RAG を介さず、LLM 単体で query_text に応答する。
        要約など vector 検索を必要としないユースケース向け。
        """
        if llm is None:
            llm = self._build_llm(provider, model, anthropic_api_key, gemini_api_key)

        if llm is None:
            # LLM すら構築できない場合は、テキストをそのまま返すフォールバック
            return {
                "answer": "(LLM が構成されていないため、要約を生成できませんでした。)",
                "sources": [],
            }

        # llama-index の BaseLLM.complete() で 1 ターンの応答を取得
        # レート制限 (429) は短時間スリープで最大 2 回リトライする
        def _is_rate_limit(exc: Exception) -> bool:
            msg = str(exc).lower()
            return any(k in msg for k in (
                "rate_limit_error", "resource_exhausted", "rate limit",
                "quota", "too many requests", "429",
            ))

        last_err = None
        for attempt in range(3):
            try:
                response = llm.complete(query_text)
                text = getattr(response, "text", None) or str(response)
                return {"answer": text, "sources": []}
            except Exception as e:
                last_err = e
                if _is_rate_limit(e) and attempt < 2:
                    time.sleep(2 ** attempt * 2)  # 2s, 4s
                    continue
                # complete が無い LLM のフォールバック (chat)
                try:
                    from llama_index.core.llms import ChatMessage, MessageRole
                    response = llm.chat([
                        ChatMessage(role=MessageRole.USER, content=query_text)
                    ])
                    text = ""
                    try:
                        text = response.message.content or ""
                    except Exception:
                        text = str(response)
                    return {"answer": text, "sources": []}
                except Exception as chat_err:
                    last_err = chat_err
                    if _is_rate_limit(chat_err) and attempt < 2:
                        time.sleep(2 ** attempt * 2)
                        continue
                    raise
        raise last_err if last_err else RuntimeError("LLM 呼び出しに失敗しました")

    # ---------------------------------------------------------------
    # 管理系
    # ---------------------------------------------------------------
    def delete_by_url(self, base_url: str) -> int:
        """指定 URL またはその配下のドキュメントを vector DB から削除する。
        戻り値は削除された行数 (チャンク数)。
        """
        if not base_url:
            return 0

        prefix = base_url.rstrip('/') + '/'
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT to_regclass(%s)",
                    (f"public.{self.DATA_TABLE}",),
                )
                exists = cur.fetchone()[0]
                if not exists:
                    return 0

                cur.execute(
                    f"""
                    DELETE FROM {self.DATA_TABLE}
                    WHERE (metadata_->>'base_url') = %s
                       OR (metadata_->>'url') = %s
                       OR (metadata_->>'url') LIKE %s
                    """,
                    (base_url, base_url, prefix + '%'),
                )
                deleted = cur.rowcount
            conn.commit()
            return deleted
        finally:
            conn.close()

    def get_source_text(self, source_id: str) -> str:
        """指定 source_id (= url または base_url) に紐づくチャンクのテキストを結合して返す。"""
        if not source_id:
            return ""
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT to_regclass(%s)", (f"public.{self.DATA_TABLE}",))
                if not cur.fetchone()[0]:
                    return ""
                cur.execute(
                    f"""
                    SELECT text
                    FROM {self.DATA_TABLE}
                    WHERE (metadata_->>'url') = %s
                       OR (metadata_->>'base_url') = %s
                    ORDER BY id ASC
                    """,
                    (source_id, source_id),
                )
                rows = cur.fetchall()
                return "\n\n".join(r[0] for r in rows if r[0])
        finally:
            conn.close()

    def list_sources(self):
        """vector DB からベースURLごとのチャンク数を集計して返す。"""
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT to_regclass(%s)",
                    (f"public.{self.DATA_TABLE}",),
                )
                exists = cur.fetchone()[0]
                if not exists:
                    return []

                cur.execute(
                    f"""
                    SELECT
                        COALESCE(metadata_->>'base_url', metadata_->>'url') AS base_url,
                        COUNT(*)::int AS chunks
                    FROM {self.DATA_TABLE}
                    GROUP BY 1
                    ORDER BY 1
                    """
                )
                return [
                    {"base_url": row[0], "chunks_indexed": row[1]}
                    for row in cur.fetchall()
                ]
        finally:
            conn.close()
