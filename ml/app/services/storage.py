import logging
import os
from typing import Optional

logger = logging.getLogger(__name__)

_s3_client = None


def get_s3_client():
    """Create or return cached boto3 S3 client for MinIO."""
    global _s3_client
    if _s3_client is not None:
        return _s3_client

    endpoint = os.getenv("MINIO_ENDPOINT", "http://minio:9000")
    access_key = os.getenv("MINIO_ROOT_USER", "admin_mpesa")
    secret_key = os.getenv("MINIO_ROOT_PASSWORD", "change_this_minio_password_123!")
    region = os.getenv("MINIO_REGION", "us-east-1")

    try:
        import boto3
        from botocore.config import Config

        _s3_client = boto3.client(
            "s3",
            endpoint_url=endpoint,
            aws_access_key_id=access_key,
            aws_secret_access_key=secret_key,
            region_name=region,
            config=Config(signature_version="s3v4", s3={"addressing_style": "path"}),
        )
        return _s3_client
    except Exception as e:
        logger.warning(f"Could not initialize boto3 S3 client: {e}")
        return None


def is_minio_enabled() -> bool:
    """Return whether MinIO object storage driver is active."""
    return os.getenv("STORAGE_DRIVER", "minio").lower() == "minio"


def get_models_bucket() -> str:
    """Return the configured bucket for model weights."""
    return os.getenv("MINIO_BUCKET_MODELS", "mpesa-models")


def ensure_model_cached(filename: str) -> Optional[str]:
    """Check if model exists in local /models cache. If missing, fetch from MinIO.

    Returns the absolute path to the local model file if ready, or None.
    """
    model_dir = os.getenv("MODEL_DIR", "/models")
    filename = os.path.basename(filename)
    dest_path = os.path.join(model_dir, filename)

    if os.path.isfile(dest_path):
        return dest_path

    if not is_minio_enabled():
        return None

    client = get_s3_client()
    if client is None:
        return None

    bucket = get_models_bucket()
    temp_path = dest_path + ".miniodownload"

    try:
        # Check if object exists in MinIO
        client.head_object(Bucket=bucket, Key=filename)
        logger.info(f"Pulling model '{filename}' from MinIO bucket '{bucket}' into local cache '{dest_path}'...")

        os.makedirs(model_dir, exist_ok=True)
        client.download_file(Bucket=bucket, Key=filename, Filename=temp_path)

        # Basic GGUF header check
        with open(temp_path, "rb") as vf:
            magic = vf.read(4)
        if magic != b"GGUF":
            if os.path.exists(temp_path):
                os.remove(temp_path)
            logger.error(f"MinIO model '{filename}' has invalid GGUF header ({magic!r})")
            return None

        os.replace(temp_path, dest_path)
        logger.info(f"Model '{filename}' successfully cached from MinIO ({round(os.path.getsize(dest_path)/(1024*1024), 1)} MB)")
        return dest_path
    except Exception as e:
        if os.path.exists(temp_path):
            try:
                os.remove(temp_path)
            except Exception:
                pass
        logger.warning(f"Could not pull model '{filename}' from MinIO: {e}")
        return None


def upload_model_to_minio(local_path: str, filename: Optional[str] = None) -> bool:
    """Upload a local model file to the MinIO models bucket."""
    if not is_minio_enabled():
        return False

    client = get_s3_client()
    if client is None:
        return False

    if not os.path.isfile(local_path):
        logger.warning(f"Cannot upload to MinIO: '{local_path}' is not a file")
        return False

    bucket = get_models_bucket()
    key = os.path.basename(filename or local_path)

    try:
        logger.info(f"Uploading model '{key}' to MinIO bucket '{bucket}'...")
        client.upload_file(
            Filename=local_path,
            Bucket=bucket,
            Key=key,
            ExtraArgs={"ContentType": "application/octet-stream"},
        )
        logger.info(f"Model '{key}' successfully uploaded to MinIO.")
        return True
    except Exception as e:
        logger.error(f"Failed to upload model '{key}' to MinIO: {e}")
        return False


def delete_model_from_minio(filename: str) -> bool:
    """Delete a model file from the MinIO models bucket."""
    if not is_minio_enabled():
        return False

    client = get_s3_client()
    if client is None:
        return False

    bucket = get_models_bucket()
    key = os.path.basename(filename)

    try:
        client.delete_object(Bucket=bucket, Key=key)
        logger.info(f"Deleted model '{key}' from MinIO bucket '{bucket}'")
        return True
    except Exception as e:
        logger.warning(f"Failed to delete model '{key}' from MinIO: {e}")
        return False


def list_minio_models() -> list[dict]:
    """List all model files stored in the MinIO models bucket."""
    if not is_minio_enabled():
        return []

    client = get_s3_client()
    if client is None:
        return []

    bucket = get_models_bucket()
    models = []
    try:
        paginator = client.get_paginator("list_objects_v2")
        for page in paginator.paginate(Bucket=bucket):
            for item in page.get("Contents", []):
                key = item.get("Key", "")
                if key.lower().endswith((".gguf", ".bin")):
                    size_bytes = item.get("Size", 0)
                    models.append({
                        "filename": key,
                        "size_mb": round(size_bytes / (1024 * 1024), 1),
                        "last_modified": item.get("LastModified", "").isoformat() if item.get("LastModified") else "",
                        "in_minio": True,
                    })
    except Exception as e:
        logger.warning(f"Failed to list MinIO models in bucket '{bucket}': {e}")

    return models


def test_minio_connection() -> dict:
    """Test connectivity to MinIO."""
    if not is_minio_enabled():
        return {"status": "disabled", "driver": "local"}

    client = get_s3_client()
    if client is None:
        return {"status": "error", "message": "boto3 client initialization failed"}

    bucket = get_models_bucket()
    try:
        client.head_bucket(Bucket=bucket)
        return {"status": "ok", "driver": "minio", "bucket": bucket}
    except Exception as e:
        return {"status": "error", "driver": "minio", "bucket": bucket, "message": str(e)}
