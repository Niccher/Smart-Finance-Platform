"""Vector Store Service for Smart Finance AI.

Provides semantic search over historical financial transactions using ChromaDB in
persistent local client mode (stored in /models/chroma_db or a configurable path).
"""

import logging
import os
from typing import Any, Dict, List, Optional

logger = logging.getLogger("smart_finance.vectors")

CHROMA_PERSIST_DIR = os.getenv("CHROMA_PERSIST_DIR", "/models/chroma_db")


class VectorStoreService:
    def __init__(self, persist_directory: str = CHROMA_PERSIST_DIR):
        self.persist_directory = persist_directory
        self._client = None
        self._collection = None

    def _get_collection(self):
        if self._collection is None:
            try:
                import chromadb
                os.makedirs(self.persist_directory, exist_ok=True)
                self._client = chromadb.PersistentClient(path=self.persist_directory)
                self._collection = self._client.get_or_create_collection(
                    name="smart_finance_transactions",
                    metadata={"hnsw:space": "cosine"},
                )
            except Exception as e:
                logger.warning(f"Could not initialize ChromaDB vector store: {e}")
                return None
        return self._collection

    def index_transactions(self, transactions: List[Dict[str, Any]]) -> int:
        """Indexes a list of parsed transaction records."""
        collection = self._get_collection()
        if not collection or not transactions:
            return 0

        documents = []
        metadatas = []
        ids = []

        for tx in transactions:
            tx_id = str(tx.get("id") or tx.get("sms_id") or "")
            if not tx_id:
                continue

            counterparty = str(tx.get("counterparty") or tx.get("sms_counterparty") or "Unknown")
            body = str(tx.get("body") or tx.get("sms_text") or "")
            category = str(tx.get("category") or tx.get("sms_category") or "Uncategorized")
            try:
                amount = float(tx.get("amount") or tx.get("sms_amount") or 0.0)
            except (ValueError, TypeError):
                amount = 0.0
            direction = str(tx.get("direction") or tx.get("sms_direction") or "none")

            doc_text = f"Transaction with {counterparty}. Category: {category}. Amount: KES {amount:.2f}. Details: {body}"

            documents.append(doc_text)
            metadatas.append({
                "counterparty": counterparty,
                "category": category,
                "amount": amount,
                "direction": direction,
                "user_id": str(tx.get("user_id") or tx.get("sms_user_id") or "1"),
            })
            ids.append(f"tx_{tx_id}")

        if not ids:
            return 0

        try:
            collection.upsert(
                documents=documents,
                metadatas=metadatas,
                ids=ids,
            )
            return len(ids)
        except Exception as e:
            logger.error(f"Error upserting vectors to ChromaDB: {e}")
            return 0

    def search_semantic(
        self,
        query: str,
        n_results: int = 5,
        user_id: Optional[str] = None,
    ) -> List[Dict[str, Any]]:
        """Performs semantic cosine similarity search against indexed transactions."""
        collection = self._get_collection()
        if not collection:
            return []

        where_filter = {"user_id": str(user_id)} if (user_id and str(user_id) not in ("-1", "0", "all")) else None

        try:
            kwargs = {
                "query_texts": [query],
                "n_results": n_results,
            }
            if where_filter:
                kwargs["where"] = where_filter

            res = collection.query(**kwargs)
            results = []
            if res and res.get("documents") and len(res["documents"]) > 0:
                docs = res["documents"][0]
                metas = res.get("metadatas", [[]])[0]
                distances = res.get("distances", [[]])[0]
                ids = res.get("ids", [[]])[0]

                for i in range(len(docs)):
                    results.append({
                        "id": ids[i] if i < len(ids) else "",
                        "document": docs[i],
                        "metadata": metas[i] if i < len(metas) else {},
                        "distance": distances[i] if i < len(distances) else 1.0,
                        "similarity": round(1.0 - (distances[i] if i < len(distances) else 1.0), 4),
                    })
            return results
        except Exception as e:
            logger.error(f"Error querying semantic vectors: {e}")
            return []


vector_store = VectorStoreService()
