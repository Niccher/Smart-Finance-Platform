"""Asynchronous Tasks for Smart Finance SMS Ingestion and Vector Indexing.

Executed by the Huey consumer worker process.
"""

import asyncio
import logging
from typing import Any, Dict, List
from app.tasks.huey_app import huey
from app.services.vector_store import vector_store

logger = logging.getLogger("smart_finance.tasks")


@huey.task(retries=3, retry_delay=10)
def run_user_processing_task(user_id: str, job_id: int) -> Dict[str, Any]:
    """Huey background task: executes SMS batch analysis and vector indexing for a user."""
    logger.info(f"[Huey Worker] Starting async SMS processing for user {user_id}, job {job_id}")
    try:
        from app.main import _run_user_job
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        try:
            # Create a thread-local lock bound to this loop to avoid cross-loop RuntimeError
            worker_lock = asyncio.Lock()
            loop.run_until_complete(_run_user_job(str(user_id), int(job_id), worker_lock))
        finally:
            loop.close()

        logger.info(f"[Huey Worker] Completed async processing for user {user_id}, job {job_id}")
        return {"status": "success", "user_id": user_id, "job_id": job_id}
    except Exception as e:
        logger.error(f"[Huey Worker] Exception in user processing job {job_id}: {e}", exc_info=True)
        raise


@huey.task(retries=2, retry_delay=5)
def index_transactions_vector_task(transactions: List[Dict[str, Any]]) -> int:
    """Huey background task: indexes a list of transactions into ChromaDB vector store."""
    try:
        count = vector_store.index_transactions(transactions)
        logger.info(f"[Huey Worker] Indexed {count} transaction vectors into semantic store.")
        return count
    except Exception as e:
        logger.error(f"[Huey Worker] Failed to index transaction vectors: {e}")
        raise
