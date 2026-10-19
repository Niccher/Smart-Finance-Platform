# Smart Finance Platform

[![Release](https://img.shields.io/badge/Release-v3.5.0-blue.svg)](https://github.com/Niccher/Smart-Finance-Platform/releases)
[![License](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4.svg)](https://www.php.net/)
[![Python](https://img.shields.io/badge/Python-3.12-3776AB.svg)](https://www.python.org/)
[![Redis](https://img.shields.io/badge/Redis-7.0-DC382D.svg)](https://redis.io/)
[![MinIO](https://img.shields.io/badge/MinIO-S3_Storage-C72C48.svg)](https://min.io/)
[![Companion App](https://img.shields.io/badge/Companion_App-Smart--Finance--Android-green.svg)](https://github.com/Niccher/Smart-Finance-Android)

Full-stack financial intelligence platform and mobile API gateway for M-Pesa, Commercial Banking, and Digital Credit SMS transactions. Combines a CodeIgniter 4 web application, an autonomous Python FastAPI LLM intelligence microservice (Qwen 2.5, DeepSeek, Gemini), a high-performance Redis 7 session & cache engine, an on-premise MinIO S3 object store, and a shared MySQL 8.4 database to ingest, decrypt, classify, analyze, and chat with personal finances.

Stack: PHP 8.3 (CodeIgniter 4), Python 3.12 (FastAPI, llama.cpp, Qwen2.5), MinIO (S3 Object Storage), Redis 7, MySQL 8.4, Docker Compose

**If you only need to run the system, this page is enough.**  
Software engineers: [docs/README.md](docs/README.md).

---

## What “Running” Looks Like

| Component | URL / Port | Expected Response / Check |
|-----------|------------|---------------------------|
| **Web Dashboard** | http://localhost | Login / Dashboard interface |
| **AI Financial Assistant** | http://localhost/dashboard/chat | Interactive AI Financial Chat |
| **WebApp Health** | http://localhost/health | HTTP 200 OK |
| **ML Microservice** | http://localhost:8001 | FastAPI root / API endpoint |
| **ML Health Check** | http://localhost:8001/health | `{"status": "ok", "db_configured": true}` |
| **ML Swagger UI** | http://localhost:8001/docs | Interactive OpenAPI documentation |
| **MinIO S3 API** | http://localhost:9000 | S3-compatible REST API |
| **MinIO Web Console** | http://localhost:9001 | Bucket management & diagnostics |
| **Redis Cache** | localhost:6379 | In-memory session & cache store |
| **MySQL Server** | localhost:3306 | Direct database access port |
| **Android Companion Client** | [Smart-Finance-Android](https://github.com/Niccher/Smart-Finance-Android) | Edge SMS capture, encryption & In-App AI Chat |

---

## Prerequisites

### Option A — Docker (Recommended)
- Git
- Docker Engine 24+ & Docker Compose v2
- Minimum 4 GB host RAM (8 GB recommended for local CPU LLM inference)

### Option B — Without Docker
- Native PHP 8.3+ with `intl`, `mbstring`, `mysqli`, `curl` extensions & Composer 2.x
- Python 3.12+ with virtual environment & llama-server binary
- Running MySQL 8.4 server instance
- Full native instructions: [docs/engineering/local-development.md](docs/engineering/local-development.md)

---

## Setup and Run

### Method 1 — One-Click Automated Pipeline (Recommended)
Run the automated deployment script from the repository root:
```bash
bash scripts/deploy.sh
```
This 13-step script automatically detects your dynamic IP, verifies system RAM/disk, writes `.env`, builds containers, waits for MySQL health, executes migrations, validates endpoints, and outputs a formatted status summary.

### Method 2 — Manual Docker Compose
1. Copy environment configuration:
   ```bash
   cp .env.example .env
   ```
2. Start the multi-container stack:
   ```bash
   docker compose up --build -d
   ```
3. Verify health checks:
   ```bash
   curl http://localhost/health
   curl http://localhost:8001/health
   ```
4. Stop the stack when done:
   ```bash
   docker compose down
   ```

---

## Configuration Users May Change

| Variable | Default | Purpose |
|----------|---------|---------|
| `WEB_PORT` | `80` | Host port for the WebApp dashboard |
| `ML_MPESA_ANALYZER_API_PORT` | `8001` | Host port for FastAPI microservice |
| `MYSQL_HOST_PORT` | `3306` | External port for MySQL 8.4 |
| `REDIS_PORT` | `6379` | Host port for Redis 7 Cache |
| `CI_ENVIRONMENT` | `production` | CodeIgniter environment mode |
| `LLM_ENGINE` | `local` | Inference engine (`local` or `external`) |
| `STORAGE_DRIVER` | `minio` | Storage driver (`minio` or `local`) |
| `MINIO_API_PORT` | `9000` | Port for MinIO S3 API |
| `MINIO_CONSOLE_PORT` | `9001` | Port for MinIO Web Console |
| `SUPERADMIN_EMAIL` | `superadmin@mpesa-analyzer.local` | Default admin email seeded at boot |
| `SUPERADMIN_PASSWORD` | `change_this_secure_password_123!` | Initial administrator password |

Full environment variable reference: [docs/user/configuration.md](docs/user/configuration.md).

---

## Something Went Wrong?

- **Port in use (80, 8001, 3306)**: Adjust the respective port in `.env`.
- **MySQL container unhealthy**: Check logs with `docker compose logs mysql`.
- **ML container fails health check**: Ensure the GGUF model exists or check `docker compose logs ml`.
- **Web 500 error**: Verify `web/writable/` permissions: `chmod -R 775 web/writable`.
- Detailed operational remedies: [docs/user/troubleshooting.md](docs/user/troubleshooting.md).

---

## Software Engineers

- **Architecture Overview**: [docs/architecture/overview.md](docs/architecture/overview.md)
- **Inter-Service Communication**: [docs/architecture/communication.md](docs/architecture/communication.md)
- **Data & Canonical Storage**: [docs/architecture/data-and-storage.md](docs/architecture/data-and-storage.md)
- **CodeIgniter 4 Service**: [docs/services/codeigniter.md](docs/services/codeigniter.md)
- **FastAPI Microservice**: [docs/services/fastapi.md](docs/services/fastapi.md)
- **LLM & Inference**: [docs/services/ml.md](docs/services/ml.md)
- **Mobile Client Integration**: [docs/services/android.md](docs/services/android.md)
- **MinIO Maintenance**: [docs/runbooks/minio-maintenance.md](docs/runbooks/minio-maintenance.md)
- **Making Changes Safely**: [docs/engineering/making-changes.md](docs/engineering/making-changes.md)
