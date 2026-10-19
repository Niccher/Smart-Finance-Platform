# Mobile API Contract (`/api/v1`) — Smart Finance Platform

This specification documents the REST API consumed by the Android companion client (`Smart-Finance-Android`).

---

## 1. Authentication & Device Handshake

### `POST /api/v1/auth/login`
Authenticates a user via email and password or token, returning a persistent session token.

### `POST /api/v1/auth/verify`
Validates an access token during app startup.
- **Headers**: `Authorization: Bearer <token>`
- **Response**: `{"status": "success", "user": {"id": 1, "username": "chege"}}`

### `POST /api/v1/device`
Registers an Android device hardware fingerprint (15 build attributes) and associates it with the authenticated account.

---

## 2. Ingestion & Data Sync

### `POST /api/v1/upload`
Accepts a binary stream containing client-side encrypted SMS payloads (`loot_[uuid].enc`).
- **Headers**: `Content-Type: application/octet-stream`, `Authorization: Bearer <token>`
- **Stream Format**: First 16 bytes contain the dynamic IV; remaining bytes contain AES-128-CBC cipher text.
- **Response**:
  ```json
  {
    "status": "success",
    "loot_id": 142,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "sms_count": 85
  }
  ```

### `POST /api/v1/process/scan`
Triggers immediate processing of uploaded SMS data.

### `POST /api/v1/process/progress`
Returns real-time processing counts for active extraction jobs.

---

## 3. Financial Analytics Endpoints (Dual GET / POST)

All read-only financial endpoints support dual-verb access: standard HTTP `GET` with Bearer headers or query parameters for REST clients, and HTTP `POST` form bodies for backward compatibility with mobile APKs.

| Endpoint | Method | Purpose |
|----------|:------:|---------|
| `/api/v1/financial/overview` | GET / POST | High-level inflow/outflow, total balance, and recent transaction counts. |
| `/api/v1/financial/categories` | GET / POST | Aggregated spending grouped by M-Pesa category (PayBill, Buy Goods, etc.). |
| `/api/v1/financial/senders` | GET / POST | Transaction volume grouped by counterparty / sender. |
| `/api/v1/financial/uploads` | GET / POST | Paginated listing of historic batch upload jobs. |
| `/api/v1/financial/uploads-count` | GET / POST | Total count and state of processed upload batches. |
| `/api/v1/financial/uploads-summary`| GET / POST | Detailed summary calculation for a specific upload UUID (`varLootUuid`). |
| `/api/v1/financial/health` | GET / POST | Algorithmic financial health score based on savings vs burn rate. |
| `/api/v1/financial/alerts` | GET / POST | Automated alerts (budget overruns, sudden spikes, unusual fees). |
| `/api/v1/financial/recurring` | GET / POST | Detected recurring subscriptions and recurring utility payments. |
| `/api/v1/financial/trends` | GET / POST | Multi-month trend calculations for visual charts. |
| `/api/v1/financial/insights` | GET / POST | Automated AI observations, recommendations, and spending tips. |

---

## 4. Conversational AI Assistant & History Endpoints

| Endpoint | Method | Purpose |
|----------|:------:|---------|
| `/api/v1/chat` | POST | Conversational AI query gateway. Accepts `{user_id, message, history}` and proxies to ML assistant with spending context. Logs turn to `tbl_Chat_Messages` with platform tracking. |
| `/api/v1/chat/info` | GET | Active model discovery endpoint. Returns status, model name (e.g. `deepseek-chat`, `qwen2.5`), and provider. |
| `/api/v1/chat/history` | GET | Fetches stored multi-turn conversation history for the authenticated user from `tbl_Chat_Messages`. |
| `/api/v1/chat/history` | DELETE | Clears all persistent chat dialogues for the authenticated user. |

---

## 5. Account & Settings Endpoints

| Endpoint | Method | Purpose |
|----------|:------:|---------|
| `/api/v1/settings/profile` | GET / POST | Fetch user account profile information. |
| `/api/v1/settings/profile/update` | POST | Update email, username, or contact preferences. |
| `/api/v1/settings/preferences` | GET / POST | Retrieve app synchronization and notification preferences. |
| `/api/v1/settings/preferences/save` | POST | Persist modified user notification, theme, and threshold settings. |
| `/api/v1/settings/delete-data` | POST | Delete all financial records and uploaded SMS while keeping account. |
| `/api/v1/settings/delete-account`| POST | Permanently purge user account, credentials, and data. |
| `/api/v1/system/version` | GET | Public endpoint returning current ecosystem release version and changelogs. |

---

## 6. ML Microservice API Endpoints (`http://ml-mpesa-analyzer:9050`)

The FastAPI service exposes autonomous batch processing, status monitoring, and administration endpoints.

### Core Pipeline Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `/health` | GET | Liveness probe returning model and database connection status. |
| `/api/v1/chat` | POST | Conversational AI assistant query with dynamic user financial history injection. |
| `/process/trigger` | POST | Manually trigger one processing cycle across all unprocessed SMS. |
| `/process/for-user/{user_id}` | POST | Trigger asynchronous LLM processing for a specific user ID. |
| `/process/db` | POST | Alias for `/process/trigger`. |

### Administrative & Telemetry Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `/admin/status` | GET | Operational summary: active engine, GGUF model metadata, and queue metrics. |
| `/admin/telemetry` | GET | Real-time system utilization: CPU, RAM, disk, llama PID, and inference latency. |
| `/admin/prompts` | GET / POST | Manage versioned classification and extraction prompts. |
| `/admin/prompts/activate/{id}` | POST | Switch active prompt version for classifier or extractor. |
| `/admin/models` | GET | List installed GGUF models in `/models` with file sizes and architectures. |
| `/admin/models/upload` | POST | Stream upload new quantized `.gguf` model files. |
| `/admin/models/activate` | POST | Set active model in `tbl_ML_Controls` and restart `llama-server`. |
| `/admin/models/delete` | POST | Remove an inactive GGUF weight file. |
