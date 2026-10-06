# System Architecture Overview

This document defines the C4 Context and Container boundaries for the **Smart Finance Platform** monorepo.

---

## 1. High-Level Context

The platform automates the ingestion, client-side encryption, server-side persistence, and autonomous AI enrichment of mobile money SMS notifications from Kenyan financial institutions (M-PESA, commercial banks, SACCOs, fintechs).

```mermaid
flowchart TD
  User((End User))

  subgraph Mobile Device
    App[Android App<br/>Smart-Finance-Android]
  end

  subgraph Monorepo Infrastructure: Smart-Finance-Platform
    WebApp[Web & API Gateway<br/>web/<br/>Port 80]
    ML[ML Intelligence Service<br/>ml/<br/>Port 8001 / Internal 9050]
    Redis[(Redis 7 In-Memory Cache<br/>mpesa-redis<br/>Port 6379)]
    MySQL[(Shared MySQL 8.4<br/>db_mpesa_analyzer<br/>Port 3306)]
  end

  User -->|Reads SMS / Views UI / AI Chat| App
  User -->|Browser Dashboard / AI Chat| WebApp
  App -->|Encrypted SMS & Chat POST /api/v1| WebApp
  WebApp -->|Store & Fetch SMS, Logs & Chat| MySQL
  WebApp -->|Session & Cache Acceleration| Redis
  WebApp -.->|Dynamic Session Failover on Disconnect| MySQL
  WebApp -->|Internal Proxy /api/v1/chat| ML
  ML -->|Poll & Single Canonical Write| MySQL
  WebApp -->|Trigger Job / Rescan / Models| ML
```

---

## 2. Container Responsibilities & Monorepo Paths

| Container / Component | Monorepo Subdirectory | Runtime & Stack | Ownership & Role |
|---|---|---|---|
| **Android App** | External (`Niccher/Smart-Finance-Android`) | Kotlin 2.0, Android SDK 35, Jetpack Compose, Retrofit 2, Glance Widget | Captures local SMS, performs regex pre-classification, encrypts with dynamic IV AES-128-CBC, streams uploads, provides home screen glance widget, and hosts real-time AI conversational finance (`ChatActivity`). |
| **Web & API Gateway** | `web/` | PHP 8.3, Apache, CodeIgniter 4, Shield | Decrypts payloads, persists raw SMS in `tbl_Sms`, stores persistent chat dialogues in `tbl_Chat_Messages` (with `webapp` vs `mobile` platform tracking), exposes Ace Admin dashboard, provides SweetAlert2 AJAX actions, and proxies mobile AI chat requests on port 80 to protect internal ML ports. |
| **ML Intelligence** | `ml/` | Python 3.12, FastAPI, llama-server, Qwen2.5 1.5B / DeepSeek / Gemini | Autonomous background polling, known-sender dictionary lookup, LLM classification, batch extraction, and multi-turn financial reasoning. |
| **Redis Cache** | Root `docker-compose.yml` | Redis 7 Alpine | In-memory session persistence, query cache, prompt acceleration, and live telemetry tracking (128 MB maxmemory with `volatile-lru`). Features automated dynamic failover to MySQL database sessions if Redis becomes unreachable. |
| **Database** | Root `docker-compose.yml` | MySQL 8.4 (InnoDB) | Central persistence for raw SMS, classification profiles, transactions, persistent chat history (`tbl_Chat_Messages`), cron execution telemetry, and audit jobs. |

---

## 3. Trust Boundaries & Authentication

1. **Android Client $\to$ WebApp API**:
   - Authenticated via SHA-256 Access Tokens (`auth_identities` table managed by Shield).
   - Payloads are encrypted client-side using dynamic IV AES-128-CBC; backend decrypts via `openssl_decrypt()`.
   - Conversational AI requests (`POST /api/v1/chat`) route through WebApp port 80, eliminating direct exposure of ML microservice port 8001 to mobile firewalls or NAT boundaries.
2. **Browser $\to$ WebApp Dashboard**:
   - Session-cookie authenticated via CodeIgniter Shield.
   - Global CSRF token verification enabled on all mutating POST requests, with explicit CSRF exemption scoped strictly to `/api/*` REST endpoints.
3. **WebApp $\to$ ML Service**:
   - Internal Docker network communication on `http://ml-mpesa-analyzer:9050` (or `http://localhost:8001` natively).
   - Admin management protected by WebApp session role filtering (`admin` filter). Optional `X-Internal-Secret` header validation.
4. **ML Service $\to$ MySQL**:
   - Direct connection via asynchronous SQLAlchemy engine (`asyncmy`).
   - Gated by admin control flag (`tbl_ML_Controls.auto_jobs_enabled`).
5. **High-Availability Session Resilience**:
   - WebApp checks Redis connectivity on startup. If Redis is down, CodeIgniter seamlessly fails over to MySQL table-backed sessions (`ci_sessions`), maintaining uninterrupted user authentication.
