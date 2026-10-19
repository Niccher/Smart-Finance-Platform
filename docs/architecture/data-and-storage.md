# Data Architecture & Storage Design

The system relies on a single shared MySQL 8.4 database (`db_mpesa_analyzer`). This document describes the database design principles, canonical record structure, relational entity mappings, and analytical views.

---

## 1. Entity-Relationship Diagram (ERD)

The diagram below illustrates relationships between device registration, uploaded payload batches, canonical SMS storage, and background ML processing jobs:

```mermaid
erDiagram
    users ||--o{ tbl_User_Devices : "owns"
    tbl_Devices ||--o{ tbl_User_Devices : "bound to"
    users ||--o{ tbl_Loot : "uploads"
    tbl_Loot ||--|{ tbl_Sms : "contains"
    tbl_Sms ||--o| tbl_Sms_Processing : "tracked by"
    users ||--o{ tbl_Processing_Jobs : "triggers"
    tbl_Sms ||--o| tbl_Sender_Profiles : "matched to"

    users {
        int id PK
        string username
        string email
    }

    tbl_Devices {
        int device_id PK
        string device_print
        string model
        string brand
    }

    tbl_User_Devices {
        int id PK
        int user_id FK
        int device_id FK
    }

    tbl_Loot {
        int loot_id PK
        string loot_uuid
        int user_id FK
        datetime created_at
    }

    tbl_Sms {
        int sms_id PK
        int loot_id FK
        int user_id FK
        string sms_sender
        text sms_body
        string sms_category
        boolean sms_is_finance
        float sms_confidence
        string sms_direction
        decimal sms_amount
        decimal sms_balance
        string sms_counterparty
        string sms_transaction_type
        boolean sms_is_transactional
        string sms_trans_id
        datetime sms_trans_date
    }

    tbl_Sms_Processing {
        int sms_id PK,FK
        string status
        int attempt_count
        text error_message
    }

    tbl_Processing_Jobs {
        int job_id PK
        int user_id FK
        string status
        json metadata
        datetime created_at
    }

    tbl_Sender_Profiles {
        int sp_id PK
        string sp_number
        string sp_name
        string sp_category
        boolean sp_is_finance
        float sp_confidence
    }

    tbl_Allowed_Senders {
        int id PK
        string sender
        string category
    }
```

---

## 2. Single Canonical Record (`tbl_Sms`)

To avoid duplicate writes and synchronization drift between classification and extraction, all attributes for an individual SMS message live on a **single canonical row** in `tbl_Sms`.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        tbl_Sms (Canonical Record)                       │
├────────────────────────────────────────────────────────────────────────┤
│ • Ingestion Metadata:   sms_id, user_id, loot_id, sender, raw_body     │
│ • Classification:       sms_category, sms_is_finance, sms_confidence   │
│ • Extraction Fields:    sms_direction, sms_amount, sms_balance,        │
│                         sms_counterparty, sms_transaction_type,        │
│                         sms_is_transactional, sms_trans_id,            │
│                         sms_trans_date                                 │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
            ┌───────────────────────┴───────────────────────┐
            ▼                                               ▼
┌───────────────────────────────┐               ┌───────────────────────────────┐
│  VIEW tbl_Sms_Classification  │               │ VIEW tbl_Analyzed_Transactions│
│  (Derived classification map) │               │ (Derived transactional subset)│
└───────────────────────────────┘               └───────────────────────────────┘
```

The database views `tbl_Sms_Classification` and `tbl_Analyzed_Transactions` are **read-only projections** over `tbl_Sms`. The ML service writes once to `tbl_Sms`, and the web application queries the views for reporting. For architectural rationale, see [ADR 0001: Single Canonical SMS Record](../adr/0001-single-canonical-sms-record.md).

---

## 3. Sender Profile & Allowlist Strategy

1. **`tbl_Allowed_Senders`**: Global database allowlist populated with verified Kenyan financial senders. Entries here bypass LLM classification entirely and receive immediate `0.95` confidence.
2. **`tbl_Sender_Profiles`**: Cached sender catalog generated dynamically by the classifier. Once an unknown sender is evaluated by the LLM, the result is saved here to prevent subsequent LLM calls.
3. **`tbl_Blocked_Senders`**: Per-user exclusions for marketing or non-relevant sender numbers.

---

## 4. Job Execution & Audit Tracking

- **`tbl_Processing_Jobs`**: Every batch run or user-triggered rescan inserts a job row. The `metadata` column stores a detailed JSON document detailing:
  - Total SMS processed, financial count, skipped count
  - Category and direction distribution
  - Model path, LLM context size, temperature, and batch configuration
  - Execution duration in seconds and error logs
- **`tbl_ML_Controls`**: Contains persistent system toggles, notably `auto_jobs_enabled`, which allows operators to pause background processing.
- **`tbl_LLM_Prompts`**: Stores versioned prompt templates. Edits made in the admin UI create new versions rather than overwriting historical prompts.

---

## 5. Persistent Chat Dialogues (`tbl_Chat_Messages`)

Multi-turn conversations between authenticated users and the AI Financial Assistant are stored in `tbl_Chat_Messages` (migration `2026-09-25-000031_CreateTblChatMessages.php`).

```
┌────────────────────────────────────────────────────────────────────────┐
│                      tbl_Chat_Messages                                 │
├────────────────────────────────────────────────────────────────────────┤
│ • Identity & Turn:      id (PK), user_id (indexed), role (user|assistant)│
│ • Content:              message (TEXT)                                 │
│ • Client Provenance:    platform ('webapp' | 'mobile', indexed),        │
│                         device_info (UA or phone model),               │
│                         app_version (e.g. '3.5.0')                     │
│ • AI Observability:     model, provider, tokens_used, latency_ms       │
│ • Timestamp:            created_at (indexed with user_id)               │
└────────────────────────────────────────────────────────────────────────┘
```

### Key Capabilities:
1. **Platform Segregation**: The `platform` column discriminates between questions typed into the WebApp browser and those submitted via the Android companion app, allowing unified storage with per-device telemetry.
2. **Context Windowing**: On chat initialization, the WebApp and Mobile endpoints fetch the user's latest 50 dialogue turns to populate the multi-turn prompt history.
3. **Auditability**: Latency (`latency_ms`) and token consumption (`tokens_used`) are recorded for each AI response, supporting system performance audits.

---

## 6. Redis 7 In-Memory Caching & Session Storage

The `mpesa-redis` container provides high-throughput, low-latency caching and temporary storage:

| Attribute | Specification | Purpose |
|---|---|---|
| **Engine** | Redis 7 Alpine (`redis:7-alpine`) | Ephemeral storage & cache acceleration |
| **Published Port** | `6379` | Host and inter-container connectivity |
| **Memory Limit** | `128 MB` (`--maxmemory 128mb`) | Strict RAM cap preventing container OOM |
| **Eviction Policy** | `volatile-lru` (`--maxmemory-policy volatile-lru`) | Automatically evicts oldest expired keys |
| **Session Key Pattern** | `ci_session:*` | Distributed WebApp user sessions |
| **Prompt Cache Pattern** | `mpesa:chat:cache:*` | Caches repetitive financial aggregate queries |
| **Live Telemetry** | Polled via Redis `INFO` / Socket | Powers the 4th KPI card on `admin/telemetry` |

---

## 7. Dual-Engine Resilience & High-Availability Failover

To eliminate single-point-of-failure vulnerabilities, the platform implements a **self-healing, zero-downtime dual-engine failover mechanism** for user sessions and application cache:

```mermaid
graph TD
    A[Incoming HTTP / API Request] --> B{50ms Socket Probe to Redis}
    B -->|Online| C[RedisHandler In-Memory RAM]
    B -->|Offline / Timeout| D[DatabaseHandler MySQL ci_sessions]
    C --> E[Fast Session Load & Save < 1ms]
    D --> F[Persistent MySQL Fallback 2-5ms]
    F --> G[Log Pre-emptive Notice]
    G --> H[Serve Request with Zero 500 Errors]
    E --> H
    D -.->|When Redis Restores| C
```

### Storage Driver Fallback Matrix

| Layer | Primary In-Memory Driver | Fallback Durable Driver | Failover Trigger | Recovery Behavior |
|---|---|---|---|---|
| **Web Sessions** | `RedisHandler` (`tcp://redis:6379`) | `DatabaseHandler` (`ci_sessions` table) | Redis socket probe $> 50\text{ ms}$ or connection refused | Instant switch back to Redis on next probe |
| **Application Cache** | `RedisHandler` (RAM keyspace) | `FileHandler` (`WRITEPATH . 'cache/'`) | Redis unreachable during initialization | Automatically re-engages Redis when online |
| **Financial SMS & Ledger** | **Always MySQL** | **Always MySQL** | Not applicable | ACID durability across all transactions |
| **Conversational AI History**| **Always MySQL** | **Always MySQL** | Not applicable | Permanent audit log in `tbl_Chat_Messages` |

### Architectural Guarantees
1. **Zero User Disruption**: When Redis restarts or crashes, the platform transparently persists session tokens in MySQL table `ci_sessions`. Users remain authenticated without 500 errors.
2. **Ultra-Low Probe Overhead**: The socket probe uses a strict 50ms timeout (`@fsockopen()`) with static request-level memoization, ensuring subsequent calls in the same request take $0\text{ ms}$.
3. **Automated Session Pruning**: CodeIgniter CLI command `php spark session:gc` automatically deletes expired records from `ci_sessions` (`timestamp < UNIX_TIMESTAMP() - expiration`), preventing MySQL table bloat.
4. **Live Observability**: Real-time status is exposed via the public `/health` endpoint (`redis_status: "connected" | "fallback_active"`), the SuperAdmin telemetry dashboard, and an automated alert badge on the SuperAdmin navbar.

---

## 8. S3-Compatible Object Storage (MinIO)

The platform integrates MinIO (`minio:RELEASE.2024-08-29T01-40-52Z`) as an S3-compatible object store to eliminate local container volume lock-in and support elastic multi-node deployments:

```mermaid
flowchart LR
    subgraph Clients & Engines
        Mobile[Android App] -->|Upload loot_*.enc| Web[CodeIgniter 4 WebApp]
        Admin[Web Admin] -->|Trigger DB Backup| Web
        FastAPI[FastAPI ML Service] -->|Cache Model Weights| LocalDisk[(Local /models/)]
    end

    subgraph MinIO S3 Object Store
        Web -->|Put / Get Payload SigV4| B1[(mpesa-payloads<br/>ILM: 90 Days)]
        Web -->|Put Backup & Presigned URL| B2[(mpesa-backups<br/>Permanent)]
        FastAPI -->|Pull / Sync GGUF Weights| B3[(mpesa-models<br/>Permanent)]
        Web -->|User Data Exports| B4[(mpesa-exports<br/>ILM: 7 Days)]
    end
```

### Bucket Architecture & Lifecycle Management (ILM)

| Bucket | Default Lifecycle | Content & Ingestion Pipeline | Access Pattern |
|---|---|---|---|
| `mpesa-payloads` | **90-Day Auto Expiration** | Client-encrypted binary blobs (`loot_*.enc`) from mobile devices | Written on ingest via `StorageService::putPayload()`; retrieved by `UploadModel` for SMS parsing. |
| `mpesa-backups` | Persistent | Compressed database dumps (`backup_*.sql.gz`) | Written on manual/scheduled dump; served via secure pre-signed 15-minute S3 URLs. |
| `mpesa-models` | Persistent | Quantized GGUF neural network weights (`*.gguf`) | Managed via ML admin router; synced to container `/models/` cache for memory-mapped (`mmap`) inference. |
| `mpesa-exports` | **7-Day Auto Expiration** | Ephemeral CSV/JSON financial export files | Generated on user demand; automatically cleaned up by MinIO lifecycle engine. |

### Hybrid Fallback & Zero-Downtime Migration

To preserve backward compatibility with existing single-node setups and avoid downtime during migration, `App\Services\StorageService` implements a **hybrid fallback strategy**:
- **Read Path**: When an object is requested, `StorageService::getPayload()` checks the MinIO bucket first. If missing or if MinIO is unreachable, it seamlessly falls back to the legacy local path (`WRITEPATH . 'uploads/payloads/'`). When found locally, it automatically backfills the object into MinIO in the background.
- **Write Path**: New uploads are written to MinIO and mirrored to local disk, ensuring immediate availability for local processing scripts and background workers.
- **Zero-Dependency Native SigV4**: CodeIgniter 4 signs requests using native PHP HMAC-SHA256 (`hash_hmac`), removing the need for the heavyweight 30MB AWS SDK in resource-constrained environments.
- **FastAPI Model Caching**: While `llama-server` requires local POSIX paths for memory-mapping weights (`mmap`), MinIO acts as the central durable model repository. On container boot or model activation, `storage.py` checks `/models/` and pulls missing weights from `mpesa-models` automatically.

For maintenance procedures, bucket replication, and console access, see the [MinIO Maintenance Runbook](../runbooks/minio-maintenance.md).


