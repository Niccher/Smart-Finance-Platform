# Runbook: Database Backup & Restoration

This runbook outlines standard operating procedures for taking, downloading, and restoring database backups in the Smart Finance Platform, integrating both local disk caching and durable MinIO S3 object storage.

---

## 1. WebApp UI Backup Procedure (Recommended)

1. Log into the Web Dashboard as a SuperAdmin.
2. Navigate to **Admin &rarr; System &rarr; Backups** (`/admin/system/backup`).
3. Verify the **Disk Headroom** badge confirms sufficient free space (minimum 2x database size required).
4. Click **Create Full Backup**.
5. The system generates the SQL snapshot in the background, compresses it (`.sql.gz`), writes a local copy to `web/writable/backups/`, and streams the archive to the MinIO object store in bucket `mpesa-backups`.
6. Click **Download** on the generated backup card. When MinIO is enabled, the platform generates a time-limited 15-minute S3 pre-signed download URL.

---

## 2. Command-Line Backup Procedures

### 2.1. Via Direct `mysqldump`
To create an immediate database dump from the container terminal:

```bash
docker compose exec -T mysql mysqldump \
  -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  ${DB_NAME:-db_mpesa_analyzer} | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### 2.2. Via MinIO Client (`mc`)
Inspect or retrieve database backups directly from the `mpesa-backups` object store:

```bash
# List all snapshots stored in MinIO
docker compose run --rm minio-init mc ls local/mpesa-backups/

# Copy a snapshot from MinIO to the host machine
docker compose run --rm minio-init mc cp local/mpesa-backups/backup_2026-10-19_00-00-00.sql.gz ./
```

For comprehensive MinIO CLI operations and disaster recovery, see [MinIO Maintenance Runbook](minio-maintenance.md).

---

## 3. Database Restoration Procedure

> [!CAUTION]
> Restoring a backup overwrites all current database tables and transactions. Ensure all users are logged out before proceeding.

### Via Web Console:
1. Navigate to **Admin &rarr; System &rarr; Backups**.
2. Locate the backup file in the archives table or upload a previous `.sql` or `.sql.gz` dump.
3. Click **Restore** and confirm the confirmation prompt.

### Via Command-Line:
```bash
# From an uncompressed SQL dump
docker compose exec -T mysql mysql \
  -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" \
  ${DB_NAME:-db_mpesa_analyzer} < backup_to_restore.sql

# Or directly from a gzip snapshot stored in MinIO
docker compose run --rm minio-init mc cat local/mpesa-backups/backup_to_restore.sql.gz | gunzip | docker compose exec -T mysql mysql \
  -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" \
  ${DB_NAME:-db_mpesa_analyzer}
```

After restoration, verify system health:
```bash
curl -f http://localhost/health
```