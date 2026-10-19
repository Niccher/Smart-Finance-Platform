# Setup and Run Guide — M-Pesa Analyzer Platform

This guide walks through starting and operating the full **M-Pesa Analyzer Platform** multi-container stack, which runs the CodeIgniter 4 WebApp, the Python FastAPI ML microservice, Redis 7 cache, MinIO S3 object storage, and MySQL 8.4.

---

## 1. Prerequisites

- **Docker Engine** 24.0+ and **Docker Compose** v2
- Host ports available:
  - `80` (WebApp Dashboard)
  - `8001` (FastAPI Microservice)
  - `8080` (llama-server)
  - `9000` (MinIO S3 API)
  - `9001` (MinIO Web Console)
  - `6379` (Redis 7 Cache)
  - `3306` (MySQL Database)

---

## 2. Option A — One-Click Automated Deployment (Recommended)

Run the deployment script from the repository root:
```bash
bash scripts/deploy.sh
```

The script automatically executes a 13-step pipeline:
1. Detects dynamic host IP (public IP or LAN fallback).
2. Performs system resource checks (OS, CPU, RAM, Disk).
3. Prepares working tree and pulls latest changes.
4. Dynamically configures `.env` with the detected IP and ports.
5. Gracefully stops legacy containers.
6. Builds and boots all services (`mysql`, `redis`, `minio`, `web`, `ml`).
7. Waits for MySQL health check.
8. Applies migrations (`php spark migrate --all`) and seeders.
9. Waits for ML microservice readiness (`/health`).
10. Waits for WebApp readiness (`/health`).
11. Enforces `web/writable/` ownership and permissions (`www-data:775`).
12. Runs cache clearing and Docker layer housekeeping.
13. Outputs a formatted deployment summary and live service endpoints.

---

## 3. Option B — Manual Docker Compose Setup

From the repository root:

### Step 1: Copy Environment Configuration
```bash
cp .env.example .env
```

### Step 2: Start the Multi-Service Stack
```bash
docker compose up --build -d
```

During container boot:
1. The **MySQL 8.4** container starts and runs health checks on port `3306`.
2. The **Redis 7** container starts on port `6379` providing session store and fast key-value caching.
3. The **MinIO S3** container starts on ports `9000` (API) and `9001` (Console), followed by `minio-init` automatically provisioning managed buckets (`mpesa-payloads`, `mpesa-backups`, `mpesa-models`, `mpesa-exports`) and applying 90-day and 7-day ILM retention rules.
4. The **WebApp** container waits for MySQL, Redis, and MinIO, executes migrations via `php spark migrate --all`, seeds default admin credentials, starts the cron daemon, and launches Apache on port `80`.
5. The **ML** container verifies or synchronizes the Qwen2.5 GGUF weights from MinIO or disk, starts `llama-server` on port `8080`, and launches FastAPI with the background DB poller on port `9050`.

### Step 3: Verify Service Health
```bash
# Check WebApp health
curl -f http://localhost/health

# Check ML microservice health
curl -f http://localhost:8001/health
```

Expected ML response:
```json
{"status": "ok", "llm_provider": "openai-compatible", "llm_model": "qwen2.5-1.5b-instruct", "db_configured": true}
```

### Step 4: Access Interfaces
- **Web Application Dashboard**: http://localhost
- **AI Financial Assistant**: http://localhost/dashboard/chat
- **Admin ML Config Panel**: http://localhost/admin/ml
- **ML Swagger UI & Documentation**: http://localhost:8001/docs
- **MinIO Web Console**: http://localhost:9001 (Credentials: `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` in `.env`)

### Step 5: Stopping the Stack
```bash
docker compose down
```
To preserve persistent database data in the `mysql-data` volume and object storage in `minio-data`, do not pass `-v`.
