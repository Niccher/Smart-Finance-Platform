# CodeIgniter 4 Service Handbook — Smart Finance Platform

This handbook covers MVC architecture, Shield authentication, route definitions, Telemetry monitoring, Maintenance runbooks, and custom Spark CLI commands.

---

## 1. Directory Structure

```
web/app/
├── Config/                  # App, Database, Filters, Routes, Shield, MlBackend, Redis, Storage
├── Controllers/
│   ├── Api/V1/              # Android API controllers (Uploads, Auth, Analytics, Chat, Settings)
│   ├── Admin/               # Admin consoles (Ml, Overview, Users, Crons, Devices, Telemetry, System)
│   │   ├── System/          # Maintenance, Backup, Logs, Cache management
│   │   └── Telemetry.php    # Live container diagnostics and sparkline dashboards
│   ├── DashboardController  # Main user financial dashboard
│   ├── ChatController       # Multi-turn conversational AI financial assistant UI & API
│   ├── Graph                # Financial analytics & charts
│   ├── Transactions         # Paginated transaction listing & CSV export
│   └── Budget               # Spending limits & progress tracking
├── Commands/                # Spark CLI background commands (llm:process, data:retention)
├── Database/                # Migrations & Seeds
├── Filters/                 # Shield session, token, CSRF, admin filters
├── Helpers/                 # Date & encryption helper functions
├── Models/                  # ModUploads, ModUser, ModBudget, ModInsights, DeviceModel, ChatMessageModel
├── Services/                # StorageService (MinIO S3 / Local fallback)
└── Views/                   # Dashboard layouts, admin views, public marketing portals
```

---

## 2. Authentication Architecture (CodeIgniter Shield)

The application utilizes **CodeIgniter Shield** supporting two distinct operational contexts:

1. **Web Dashboard Users**:
   - Authenticated via standard session cookies accelerated by Redis 7 (with dynamic failover to MySQL database sessions if Redis is unreachable).
   - Guarded by the `'session'` filter in `web/app/Config/Routes.php`.
   - Supports Magic Link passwordless login (`/magic-link`).
2. **Mobile Android Clients**:
   - Authenticated via SHA-256 access tokens (`auth_identities` table).
   - Guarded by token validation in `App\Controllers\Api\V1\AuthController`.
   - Supports CameraX vertical QR pairing for instant token exchange without manual entry.

---

## 3. Container Observability & Telemetry Controller

Real-time diagnostic metrics are orchestrated by `App\Controllers\Admin\Telemetry`:

- **Host & Container Headroom**: Computes used vs. free vs. total RAM, CPU core load, and disk utilization with pre-execution safety checks.
- **Targeted MySQL Status Query**: Replaces expensive full status dumps with a focused 9-variable query (`Threads_connected`, `Threads_running`, `Max_used_connections`, `Slow_queries`, `Aborted_connects`, `Innodb_buffer_pool_reads`, `Innodb_buffer_pool_read_requests`, `Questions`, `Uptime`).
- **Redis 7 Telemetry KPI Card**: Real-time monitoring of Redis memory RSS, hit rate %, total keys, ops/sec, and ping latency.
- **In-Memory Caching & Failover**: Telemetry results cache database size and active session counts (60-second TTL). If Redis is offline, seamlessly falls back to local database queries.
- **Zero-Overhead Visualizations**: Employs pure-SVG client-side sparklines to render live CPU, RAM, and query trendlines without heavy JavaScript charting libraries.
- **AJAX Interactions**: Administrative controls use SweetAlert2 AJAX actions, eliminating full-page reloads when restarting services, running crons, or clearing caches.

---

## 4. System Maintenance & Automated Backups

Admin consoles under `App\Controllers\Admin\System\`:

- **Maintenance (`/admin/system/maintenance`)**:
  - Cache management (OpCache, framework cache, view rendering cache).
  - Session clearing and orphaned lock recovery.
  - Automated data retention policies (pruning non-financial SMS or logs older than $N$ days).
- **Backups (`/admin/system/backup`)**:
  - Full SQL database dump generation with table exclusion rules.
  - Pre-flight disk space headroom checks to prevent out-of-space container panics.
  - One-click backup creation, download, and restoration.
  - Direct streaming to MinIO S3 bucket `mpesa-backups` with time-limited 15-minute S3 pre-signed download URLs.

---

## 5. Custom Spark CLI Commands

Custom background tasks reside in `app/Commands/`:

| Command | Class | Description |
|---------|-------|-------------|
| `php spark llm:process` | `LlmProcess` | CLI trigger to dispatch pending SMS to the ML service. |
| `php spark cron:run` | `CronRun` | Executes scheduled cron definitions configured in the admin UI. |
| `php spark data:retention` | `DataRetention` | Purges expired logs and non-essential raw cache data. |
| `php spark uploads:cleanup` | `UploadsCleanup` | Cleans temporary binary upload streams from `writable/uploads/`. |
| `php spark digest:weekly` | `WeeklyDigest` | Generates weekly financial digest notifications for active users. |

---

## 6. S3-Compatible Object Storage (`StorageService`)

Orchestrated by `App\Services\StorageService` (configured in `web/app/Config/Storage.php`):

- **Zero-SDK AWS SigV4 Signing**: Implements native PHP HMAC-SHA256 request signing (`hash_hmac`), bypassing the 30MB AWS SDK footprint for minimal container images and rapid execution.
- **Managed S3 Buckets**:
  - `mpesa-payloads`: Incoming encrypted SMS uploads (`loot_*.enc`) retained under a 90-day ILM auto-deletion policy.
  - `mpesa-backups`: Compressed database dumps (`backup_*.sql.gz`) with 15-minute S3 pre-signed secure download URLs.
  - `mpesa-models`: Quantized GGUF neural network weights distributed to the FastAPI service.
  - `mpesa-exports`: Ephemeral CSV/JSON exports with automated 7-day ILM lifecycle expiration.
- **Resilient Hybrid Fallback**: All writes mirror to `web/writable/` paths. If MinIO is unreachable or disabled (`STORAGE_DRIVER=local`), the service falls back to local disk storage seamlessly without raising 500 errors.