import os
import time
import json
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

    @staticmethod
    def _normalize_collections(value) -> list:
        """文字列・配列・カンマ区切り文字列を受け取り、コレクション名の配列に正規化する。

        - 空白除去、空文字除去、重複除去 (順序維持)
        - スペース/区切り記号を含む不正トークンは破棄
        - 何も無い場合は ['default']
        """
        if value is None:
            return ['default']
        if isinstance(value, str):
            parts = [p.strip() for p in value.replace('\r', '\n').replace('，', ',').split(',')]
        elif isinstance(value, (list, tuple)):
            parts = [str(p).strip() for p in value]
        else:
            parts = [str(value).strip()]
        out = []
        for p in parts:
            if not p:
                continue
            # スペース / / \ # ? & を含むトークンは破棄
            if any(ch in p for ch in (' ', '\t', '\n', '/', '\\', '#', '?', '&')):
                continue
            if len(p) > 64:
                p = p[:64]
            if p not in out:
                out.append(p)
        return out if out else ['default']

    def add_documents(self, scraped_data):
        """scraped_data の各要素 dict に対し:
        - 'collections' (配列) があればそれを採用
        - なければ 'collection' (文字列) を採用
        - どちらも無ければ ['default']
        メタデータには `collection` (主, 互換用) と `collections` (配列, 新) を両方入れる。
        """
        documents = []
        for d in scraped_data:
            cols = self._normalize_collections(d.get('collections', d.get('collection')))
            documents.append(Document(
                text=d['content'],
                extra_info={
                    "url": d['url'],
                    "title": d['title'],
                    "base_url": d.get('base_url', d['url']),
                    "collection":  cols[0],   # 互換 (主)
                    "collections": cols,      # 配列 (新)
                },
            ))

        vector_store = self.get_vector_store()
        storage_context = StorageContext.from_defaults(vector_store=vector_store)

        VectorStoreIndex.from_documents(
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
        collections: Optional[list] = None,
    ):
        """RAG クエリ。`collections` が指定された場合は、ドキュメントの
        metadata.collections (配列) のいずれかに含まれるものだけを採用する。

        実装方針:
        - llama-index のフィルタは JSON 配列の overlap マッチを直接サポートしないため、
          retriever 段階で広めに上位 N (= top_k * 5, 最大 50) を取得し、結果を
          Python 側で metadata.collections (配列) 包含チェック → 上位 top_k に絞る。
        - フィルタ後にコンテキストが空ならフィルタなしで再試行 (no-hit を避ける)。
        """
        llm = self._build_llm(provider, model, anthropic_api_key, gemini_api_key)
        wanted = self._normalize_collections(collections) if collections else None
        # ['default'] のみが指定されている場合はフィルタ無し (= 全件) と等価扱い
        # (UI 側で「フィルタなし」を表すために [] を送ることもできるが、安全側で default を緩和)
        if wanted == ['default']:
            wanted = None

        if llm is None:
            return self._direct_llm_only_query(query_text, top_k, provider, model, anthropic_api_key, gemini_api_key)

        try:
            vector_store = self.get_vector_store()
            index = VectorStoreIndex.from_vector_store(vector_store)
            # コレクションフィルタを掛ける場合は retriever 段階で広めに取得
            retrieve_k = top_k if not wanted else min(50, max(top_k * 5, 20))
            retriever = index.as_retriever(similarity_top_k=retrieve_k)
            nodes = retriever.retrieve(query_text)

            if wanted:
                filtered = [n for n in nodes if self._node_matches_collections(n, wanted)]
                if filtered:
                    nodes = filtered[:top_k]
                else:
                    # コレクション一致なし: フォールバックとして全体上位 top_k で答える
                    nodes = nodes[:top_k]
            else:
                nodes = nodes[:top_k]

            # query_engine に渡す代わりに、選んだノードからコンテキストを組んで LLM へ
            context_text = "\n\n---\n\n".join((n.node.get_content() or "") for n in nodes)
            prompt = (
                "以下は社内ナレッジから抽出した参考文書です。これらに基づいて簡潔に質問へ回答してください。\n\n"
                f"=== 参考文書 ===\n{context_text}\n=== 参考文書ここまで ===\n\n"
                f"質問: {query_text}\n\n回答:"
            )
            answer = ""
            try:
                resp = llm.complete(prompt)
                answer = getattr(resp, "text", None) or str(resp)
            except Exception:
                # complete が無いプロバイダは chat にフォールバック
                from llama_index.core.llms import ChatMessage, MessageRole
                resp = llm.chat([ChatMessage(role=MessageRole.USER, content=prompt)])
                try:
                    answer = resp.message.content or ""
                except Exception:
                    answer = str(resp)

            return {
                "answer": answer,
                "sources": [
                    {
                        "text": n.node.get_content(),
                        "score": getattr(n, 'score', None),
                        "url":   (n.node.extra_info or {}).get("url"),
                        "title": (n.node.extra_info or {}).get("title"),
                        "collections": (n.node.extra_info or {}).get("collections")
                                       or [(n.node.extra_info or {}).get("collection")] if (n.node.extra_info or {}).get("collection") else [],
                    }
                    for n in nodes
                ],
                "matched_collections": wanted,
            }
        except Exception:
            # vector store が空 / 取得に失敗した場合は LLM 単体で応答する
            return self._direct_llm_only_query(query_text, top_k, provider, model, anthropic_api_key, gemini_api_key, llm=llm)

    @staticmethod
    def _node_matches_collections(node, wanted: list) -> bool:
        """ノードの metadata から collections 配列を取り出し、`wanted` と共通要素があれば True。

        ノード側の格納形式は次のいずれもありえる:
        - extra_info['collections'] が list[str]    (新)
        - extra_info['collection']  が str          (互換)
        - 何も無い場合は 'default' とみなす
        """
        meta = getattr(node.node, 'extra_info', None) or getattr(node, 'extra_info', None) or {}
        cols = meta.get('collections')
        if not cols:
            single = meta.get('collection')
            cols = [single] if single else ['default']
        if not isinstance(cols, list):
            cols = [str(cols)]
        wanted_set = set(wanted)
        return any(c in wanted_set for c in cols)

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

    def update_collections_for_source(self, source_id: str, collections) -> int:
        """指定 source_id (= url または base_url) のベクター DB レコードの
        metadata_->'collections' と metadata_->>'collection' を上書きする。

        ファイル再インデックスなしでタグ変更だけ vector DB に反映させたい場合に使う。
        戻り値: 更新行数 (チャンク数)。
        """
        if not source_id:
            return 0
        cols = self._normalize_collections(collections)
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT to_regclass(%s)", (f"public.{self.DATA_TABLE}",))
                exists = cur.fetchone()[0]
                if not exists:
                    return 0
                # JSON 配列で collections、文字列で collection を埋め直す
                cur.execute(
                    f"""
                    UPDATE {self.DATA_TABLE}
                    SET metadata_ = jsonb_set(
                                       jsonb_set(metadata_, '{{collections}}', %s::jsonb, true),
                                       '{{collection}}', to_jsonb(%s::text), true
                                    )
                    WHERE (metadata_->>'url') = %s
                       OR (metadata_->>'base_url') = %s
                    """,
                    (
                        json.dumps(cols, ensure_ascii=False),
                        cols[0],
                        source_id, source_id,
                    ),
                )
                updated = cur.rowcount
            conn.commit()
            return updated
        finally:
            conn.close()

    def list_collection_names(self):
        """ベクター DB の metadata_ から使われているコレクション名と件数を集計する。

        - `metadata_->'collections'` が JSON 配列で入っているレコード:
            jsonb_array_elements_text() で要素を展開して集計
        - `metadata_->>'collection'` の単一値しか持たないレコード:
            そのまま集計
        - どちらも無いレコード:
            'default' として集計

        戻り値: [(name, count), ...] (件数の多い順)
        """
        conn = self._connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT to_regclass(%s)", (f"public.{self.DATA_TABLE}",))
                exists = cur.fetchone()[0]
                if not exists:
                    return []

                # 配列形式 (新) と 単一文字列形式 (互換) の両方を UNION ALL で集計
                cur.execute(
                    f"""
                    WITH expanded AS (
                        -- 配列形式: metadata_->'collections' を要素展開
                        SELECT jsonb_array_elements_text(metadata_->'collections') AS name
                        FROM {self.DATA_TABLE}
                        WHERE jsonb_typeof(metadata_->'collections') = 'array'
                        UNION ALL
                        -- 互換形式: 配列が無く collection 文字列のみ
                        SELECT metadata_->>'collection' AS name
                        FROM {self.DATA_TABLE}
                        WHERE (metadata_->'collections') IS NULL
                          AND metadata_->>'collection' IS NOT NULL
                          AND metadata_->>'collection' <> ''
                        UNION ALL
                        -- どちらも無いレコードは default にカウント
                        SELECT 'default' AS name
                        FROM {self.DATA_TABLE}
                        WHERE (metadata_->'collections') IS NULL
                          AND (metadata_->>'collection' IS NULL OR metadata_->>'collection' = '')
                    )
                    SELECT name, COUNT(*)::int AS cnt
                    FROM expanded
                    WHERE name IS NOT NULL AND name <> ''
                    GROUP BY name
                    ORDER BY cnt DESC, name ASC
                    """
                )
                return [(row[0], row[1]) for row in cur.fetchall()]
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
