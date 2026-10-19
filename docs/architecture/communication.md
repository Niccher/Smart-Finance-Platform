# Inter-Service Communication & Protocols

This document details transport protocols, sequences, and security contracts between the Android application, CodeIgniter 4 WebApp, and the FastAPI ML service.

---

## 1. Service Communication Matrix

| Source | Destination | Transport | Auth Mechanism | Dev URL | Purpose |
|--------|-------------|-----------|----------------|---------|---------|
| **Android App** | WebApp API | HTTP POST | Bearer SHA-256 Token | `http://10.0.2.2/api/v1/` | Payload upload, stats fetch, auth |
| **Android App** | WebApp Gateway | HTTP POST | Bearer / User Token | `http://10.0.2.2/api/v1/chat` | Conversational AI financial chat |
| **WebApp** | ML Service | HTTP POST | Internal network | `http://ml-mpesa-analyzer:9050/` | Trigger user job, rescan, health, chat |
| **WebApp** | Redis | TCP (6379) | Socket / Host | `redis:6379` | Session storage, cache, live telemetry |
| **WebApp** | MinIO S3 | HTTP PUT/GET (9000) | AWS SigV4 | `http://minio:9000` | Archiving payloads, backups, exports |
| **WebApp** | MySQL | TCP (3306) | User/Password | `mysql:3306` | Web queries, Shield auth, inserts |
| **ML Service** | MinIO S3 | HTTP GET/PUT (9000) | AWS SigV4 (boto3) | `http://minio:9000` | GGUF model distribution (`mpesa-models`) |
| **ML Service** | MySQL | TCP (3306) | User/Password | `mysql:3306` | Polling, canonical SMS updates, metrics |
| **FastAPI** | llama-server | HTTP POST | Localhost loopback | `http://localhost:8080/v1` | OpenAI-compatible completions |

---

## 2. Conversational AI Financial Assistant Flow

```mermaid
sequenceDiagram
  autonumber
  actor User as User (Android or WebApp)
  participant Client as Web/Mobile Client
  participant Web as CI4 WebApp Gateway
  participant DB as MySQL (tbl_Chat_Messages)
  participant ML as FastAPI Service (:9050)
  participant LLM as Active LLM (DeepSeek/Qwen/Gemini)

  User->>Client: Ask question ("How much did I spend this month?")
  Client->>Web: POST /dashboard/chat/send or /api/v1/chat
  Web->>DB: INSERT user prompt (platform: webapp | mobile, device_info, app_version)
  Web->>ML: POST /api/v1/chat {user_id, message, history}
  ML->>DB: Query user transaction aggregates & monthly metrics
  ML->>LLM: Generate response with injected spending context
  LLM-->>ML: Financial advice response (Sheng/English)
  ML-->>Web: Return {reply, model, latency_ms, tokens_used}
  Web->>DB: INSERT assistant reply (platform: webapp | mobile, model, latency_ms)
  Web-->>Client: JSON response
  Client-->>User: Display markdown response with model & latency badges
```

---

## 3. End-to-End Ingestion & Processing Flow

```mermaid
sequenceDiagram
  autonumber
  participant App as Android Client
  participant Web as CI4 WebApp
  participant MinIO as MinIO S3 (:9000)
  participant DB as MySQL 8.4
  participant ML as FastAPI Service
  participant LLM as llama-server

  App->>App: Read SMS & AES-128 Encrypt (dynamic IV)
  App->>Web: POST /api/v1/upload (loot stream)
  Web->>MinIO: PUT mpesa-payloads/loot_{uuid}.enc (SigV4 archive)
  Web->>Web: Decrypt stream & parse JSON
  Web->>DB: INSERT into tbl_Sms & tbl_Loot
  Web->>ML: POST /process/for-user/{id} (or poller picks up)
  ML->>DB: SELECT unprocessed from tbl_Sms
  ML->>ML: Match sender in tbl_Allowed_Senders / known dict
  alt Unknown Sender
    ML->>LLM: POST /v1/chat/completions (classify sender)
    LLM-->>ML: Return {is_finance, category, confidence}
    ML->>DB: UPSERT tbl_Sender_Profiles
  end
  alt Finance Sender
    ML->>LLM: POST /v1/chat/completions (extract transactions)
    LLM-->>ML: Structured JSON (amount, direction, balance)
  end
  ML->>DB: UPDATE tbl_Sms (single canonical record)
  ML->>DB: INSERT tbl_Processing_Jobs (audit & metrics)
  App->>Web: POST /api/v1/financial/overview
  Web-->>App: Return classified financial summaries
```

---

## 4. Security & Payload Protocol

### Dynamic IV AES-128-CBC
1. **Client Streaming**: The Android app generates a 16-byte cryptographically secure random IV (`SecureRandom`).
2. **IV Prefixing**: The IV is written as the first 16 bytes of the binary payload stream.
3. **Payload**: The remainder of the stream contains the AES-128-CBC encrypted JSON body.
4. **Server Decryption**: The CI4 WebApp reads the initial 16 bytes, sets it as the IV, and executes `openssl_decrypt()`.

---

## 5. API Error Body Convention

All JSON API endpoints conform to a standardized error envelope:

```json
{
  "status": "error",
  "message": "Human-readable description of error",
  "code": 400,
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```
