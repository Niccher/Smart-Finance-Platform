# Smart Finance Platform — Engineering Documentation

Welcome to the central engineering documentation hub for the **Smart Finance Platform** monorepo. This repository consolidates the PHP 8.3 CodeIgniter 4 WebApp, the Python 3.12 FastAPI LLM intelligence microservice (Qwen 2.5, DeepSeek, Gemini), a high-performance Redis 7 session & cache engine, and shared database operations into a unified workspace.

If you only need to run the application using Docker, refer to the root [Operator Guide](../README.md).

---

## Documentation Index

| Topic | Document | Target Audience |
|---|---|---|
| **System Architecture Overview** | [architecture/overview.md](architecture/overview.md) | C4 context & container diagrams, Redis 7, and Gateway Proxy |
| **Inter-Service Communication** | [architecture/communication.md](architecture/communication.md) | Protocols, sequence diagrams, and port 80 chat proxy |
| **Data & Storage Design** | [architecture/data-and-storage.md](architecture/data-and-storage.md) | MySQL schema, ERD, `tbl_Chat_Messages`, and Redis caching |
| **Deployment & Lifecycle** | [architecture/deployment.md](architecture/deployment.md) | Docker Compose, 13-step `deploy.sh` pipeline, and container topology |
| **Threat Model & Security** | [architecture/threat-model.md](architecture/threat-model.md) | STRIDE analysis, CSRF exemptions, and cryptographic mitigations |
| **Combined API Contract** | [api/contract.md](api/contract.md) | Mobile REST API (`/api/v1`), Chat endpoints, and ML microservice |
| **OpenAPI 3.0 Specification** | [api/openapi.yaml](api/openapi.yaml) | Interactive OpenAPI 3.0 contract definition |
| **CodeIgniter 4 Service Handbook** | [services/codeigniter.md](services/codeigniter.md) | Controllers, models, Shield, and scheduled cron jobs |
| **FastAPI Microservice Handbook** | [services/fastapi.md](services/fastapi.md) | Routers, background loops, and Pydantic schemas |
| **ML & Inference Handbook** | [services/ml.md](services/ml.md) | Local LLM, Qwen2.5 GGUF, prompts, and fallback engines |
| **Android Client Contract** | [services/android.md](services/android.md) | Integration specs for `Smart-Finance-Android` |
| **Local Development** | [engineering/local-development.md](engineering/local-development.md) | Native PHP 8.3 & Python 3.12 setup |
| **Making Changes Safely** | [engineering/making-changes.md](engineering/making-changes.md) | Development workflow & Definition of Done |
| **Database & Migrations** | [engineering/database.md](engineering/database.md) | Migrations ownership and query patterns |
| **Automated Testing Guide** | [engineering/testing.md](engineering/testing.md) | PHPUnit and Pytest test runners |
| **Security & Cryptography** | [engineering/security.md](engineering/security.md) | AES-128 dynamic IV, token hashing, and Shield |
| **Release & Compatibility** | [engineering/release.md](engineering/release.md) | Compatibility across WebApp, ML, and Android |
| **Contributing Standards** | [engineering/contributing.md](engineering/contributing.md) | PR guidelines and commit conventions |
| **Dev Troubleshooting** | [engineering/troubleshooting.md](engineering/troubleshooting.md) | Engineering debugging and stack trace analysis |
| **Runbook: Container Restart** | [runbooks/restart.md](runbooks/restart.md) | Graceful cycling and cache purging |
| **Runbook: Backup & Restore** | [runbooks/backup-and-restore.md](runbooks/backup-and-restore.md) | Database snapshotting and restoration |
| **Runbook: AI Rescan** | [runbooks/full-rescan.md](runbooks/full-rescan.md) | Historical loot dataset reprocessing |
| **Runbook: Model Upgrade** | [runbooks/model-upgrade.md](runbooks/model-upgrade.md) | GGUF model upgrade & context resizing |
| **ADR 0001: Single Canonical SMS** | [adr/0001-single-canonical-sms-record.md](adr/0001-single-canonical-sms-record.md) | Architectural decision for unified storage |
| **ADR 0002: Local CPU Inference** | [adr/0002-local-cpu-llm-vs-hosted-apis.md](adr/0002-local-cpu-llm-vs-hosted-apis.md) | Architectural decision for on-premise LLMs |
| **ADR 0003: Monorepo Consolidation** | [adr/0003-unified-monorepo-consolidation.md](adr/0003-unified-monorepo-consolidation.md) | Architectural decision for unified codebase |

---

## Client Ecosystem Repository

- **Android Mobile Client**: [Niccher/Smart-Finance-Android](https://github.com/Niccher/Smart-Finance-Android) (Kotlin 2.0, Retrofit 2, Jetpack Compose, Glance Widget)
