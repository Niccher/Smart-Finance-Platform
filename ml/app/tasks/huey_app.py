"""Huey Task Queue Application Instance for Smart Finance ML.

Connects to the shared Redis instance to manage asynchronous batch classification,
semantic vector indexing, and background task retry workflows.
"""

import logging
import os
import urllib.parse
from huey import RedisHuey

logger = logging.getLogger("smart_finance.huey")

REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379/1")

parsed = urllib.parse.urlparse(REDIS_URL)
_host = parsed.hostname or "redis"
_port = parsed.port or 6379
_db_str = parsed.path.lstrip("/")
_db = int(_db_str) if _db_str.isdigit() else 1
_password = parsed.password

huey = RedisHuey(
    "smart_finance_tasks",
    host=_host,
    port=_port,
    db=_db,
    password=_password,
    blocking=True,
    read_timeout=2,
    results=True,
    store_none=False,
)
