# Configuration Guide — M-Pesa Analyzer Platform

All multi-service configurations are managed via the `.env` file at the root of the monorepo.

---

## 1. Stack & Port Variables

| Variable | Service | Default | Purpose |
|---|---|---|---|
| `WEB_PORT` | WebApp | `80` | Host published port for the CodeIgniter dashboard |
| `ML_MPESA_ANALYZER_API_PORT` | ML | `8001` | Host published port for the FastAPI microservice |
| `ML_MPESA_ANALYZER_LLAMA_PORT` | ML | `8080` | Host published port for the local llama-server |
| `REDIS_PORT` | Redis | `6379` | Host published port for Redis 7 Cache |
| `MINIO_API_PORT` | MinIO | `9000` | Host published port for MinIO S3 API |
| `MINIO_CONSOLE_PORT` | MinIO | `9001` | Host published port for MinIO Web Console |
| `MYSQL_HOST_PORT` | MySQL | `3306` | Host published port for external MySQL connections |

---

## 2. Database Variables

| Variable | Default | Purpose |
|---|---|---|
| `DB_HOST` | `mysql` | Hostname of MySQL container on the Docker network |
| `DB_PORT` | `3306` | Internal MySQL port |
| `DB_NAME` | `db_mpesa_analyzer` | Relational database schema name |
| `DB_USER` | `root` | Database username |
| `DB_PASSWORD` | `root_password` | Database password |
| `MYSQL_ROOT_PASSWORD` | `root_password` | Root administrative password for MySQL container |

---

## 3. WebApp & Mobile API Gateway Variables

| Variable | Default | Purpose |
|---|---|---|
| `CI_ENVIRONMENT` | `production` | CodeIgniter mode (`development` or `production`) |
| `app.baseURL` | `http://localhost/` | Base URL used for link generation and redirects |
| `ML_BACKEND_URL` | `http://ml-mpesa-analyzer:9050` | Microservice URL used by WebApp commands |
| `MPESA_CRYPT_KEY` | *(Set in .env)* | 16-byte key for AES-128-CBC decryption |
| `MPESA_CRYPT_IV` | *(Set in .env)* | Fallback 16-byte IV for legacy uploads |
| `SUPERADMIN_EMAIL` | `superadmin@mpesa-analyzer.local` | Email of seeded superadmin user |
| `SUPERADMIN_PASSWORD` | *(Set in .env)* | Initial password of seeded superadmin user |

---

## 4. ML Intelligence Microservice Variables

| Variable | Default | Purpose |
|---|---|---|
| `LLM_ENGINE` | `local` | Active inference engine (`local` or `external`) |
| `LLM_MODEL` | `qwen2.5-1.5b-instruct` | Active model identifier |
| `MODEL_PATH` | `/models/qwen2.5-1.5b-instruct-q4_k_m.gguf` | Path to active GGUF weights inside container |
| `LLAMA_PORT` | `8080` | Internal port for llama-server binary |
| `LLM_CTX_SIZE` | `16384` | Context window size allocated to llama-server |
| `LLM_BATCH_SIZE` | `512` | Batch evaluation size for prompt token ingestion |
| `BATCH_SIZE` | `5` | Unprocessed SMS processed per classification run |
| `POLL_INTERVAL` | `30` | Seconds between autonomous DB poll queries |
| `LLM_FALLBACK_ENABLED`| `false` | Enable cloud LLM fallback if local engine errors |
| `ML_INTERNAL_SECRET` | *(Optional)* | Secret key for `X-Internal-Secret` header validation |

---

## 5. Object Storage (MinIO / S3) Variables

| Variable | Default | Purpose |
|---|---|---|
| `STORAGE_DRIVER` | `minio` | Active storage engine (`minio` or `local`) |
| `MINIO_ENDPOINT` | `http://minio:9000` | Internal S3 API endpoint for backend service network |
| `MINIO_PUBLIC_ENDPOINT` | *(empty)* | Optional browser-accessible S3 endpoint for pre-signed URLs (e.g. `http://localhost:9000`) |
| `MINIO_ROOT_USER` | `admin_mpesa` | Root administrative user for MinIO S3 API & Console |
| `MINIO_ROOT_PASSWORD` | `change_this_minio_password_123!` | Root administrative password for MinIO |
| `MINIO_BUCKET_PAYLOADS` | `mpesa-payloads` | S3 bucket for incoming encrypted SMS upload blobs (90-day ILM retention) |
| `MINIO_BUCKET_BACKUPS` | `mpesa-backups` | S3 bucket for database snapshot archives (`backup_*.sql.gz`) |
| `MINIO_BUCKET_MODELS` | `mpesa-models` | S3 bucket for quantized GGUF neural network weights |
| `MINIO_BUCKET_EXPORTS` | `mpesa-exports` | S3 bucket for ephemeral user financial export downloads (7-day ILM retention) |
