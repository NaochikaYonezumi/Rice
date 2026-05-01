import os
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

    def query(self, query_text):
        vector_store = self.get_vector_store()
        index = VectorStoreIndex.from_vector_store(vector_store)
        
        query_engine = index.as_query_engine(similarity_top_k=5)
        response = query_engine.query(query_text)
        
        return {
            "answer": str(response),
            "sources": [{"text": n.node.get_content(), "score": n.score, "url": n.node.extra_info.get('url')} for n in response.source_nodes]
        }
