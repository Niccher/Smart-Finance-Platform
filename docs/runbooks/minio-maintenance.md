# Operational Runbook: MinIO Object Storage Maintenance

This runbook guides operators and DevOps engineers in maintaining, monitoring, and troubleshooting the MinIO S3-compatible object storage infrastructure within the Smart Finance Platform stack.

---

## 1. Overview & Service Architecture

The platform uses MinIO (`minio:RELEASE.2024-08-29T01-40-52Z`) as an S3-compatible object storage layer to decouple binary files and model weights from local host disk volumes:

| Parameter | Configuration Value | Description |
|---|---|---|
| **Service Name** | `minio` | MinIO S3 API & Console server container |
| **Provisioner** | `minio-init` | One-shot initialization container running `minio/mc` |
| **API Port** | `9000` (`MINIO_API_PORT`) | Standard AWS S3 SigV4 API endpoint |
| **Console Port** | `9001` (`MINIO_CONSOLE_PORT`) | MinIO Web Management Console |
| **Data Volume** | `minio-data` | Persistent Docker volume mounted at `/data` |
| **Root Credentials** | `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD` | Configured in `.env` |

### Managed Buckets & Policies

| Bucket Name | Purpose | Retention / Lifecycle Policy |
|---|---|---|
| `mpesa-payloads` | Incoming encrypted mobile SMS upload blobs (`loot_*.enc`) | **90-day automatic expiration** via S3 ILM |
| `mpesa-backups` | Database snapshot archives (`backup_*.sql.gz`) | Retained persistently; managed by admin retention |
| `mpesa-models` | Quantized GGUF model binaries (`*.gguf`) | Retained persistently; cached locally by FastAPI |
| `mpesa-exports` | User CSV and JSON financial export downloads | **7-day automatic expiration** via S3 ILM |

---

## 2. MinIO Web Console Access

The MinIO Web Console provides an intuitive graphical interface for inspecting buckets, browsing objects, reviewing access policies, and viewing disk usage metrics:

1. Open your browser and navigate to:
   ```
   http://localhost:9001
   ```
   *(or the remote server IP / hostname on port 9001)*

2. Sign in with the root credentials defined in `.env`:
   - **Username**: Value of `MINIO_ROOT_USER` (default: `admin_mpesa`)
   - **Password**: Value of `MINIO_ROOT_PASSWORD`

3. From the left navigation sidebar:
   - **Buckets**: View, inspect, or manually create buckets and set lifecycle policies.
   - **Object Browser**: Search and download stored payloads, database snapshots, or models.
   - **Monitoring / Metrics**: Monitor realtime S3 throughput, latency, and disk consumption.

---

## 3. MinIO Client (`mc`) CLI Operations

To manage MinIO directly via the command line or from within Docker:

### 3.1. Setting the MinIO Client Alias

Run an interactive shell inside a container or from a machine with `mc` installed:

```bash
docker compose run --rm minio-init /bin/sh
```

Configure or verify the local alias:
```bash
mc alias set local http://minio:9000 admin_mpesa change_this_minio_password_123!
```

### 3.2. Verifying Bucket Health & Disk Usage

List all buckets:
```bash
mc ls local/
```

Check disk usage across all managed buckets:
```bash
mc du local/
```

Inspect objects in a specific bucket:
```bash
mc ls local/mpesa-payloads/
mc ls local/mpesa-backups/
mc ls local/mpesa-models/
```

### 3.3. Verifying & Managing Lifecycle Rules (ILM)

Inspect current lifecycle rules on payloads and exports:
```bash
mc ilm rule ls local/mpesa-payloads
mc ilm rule ls local/mpesa-exports
```

If lifecycle rules need to be reapplied or adjusted:
```bash
# Expire payloads after 90 days
mc ilm rule add --expire-days 90 local/mpesa-payloads

# Expire exports after 7 days
mc ilm rule add --expire-days 7 local/mpesa-exports
```

---

## 4. Disaster Recovery & Snapshot Migration

### 4.1. Backing Up the MinIO Volume

To create an off-site backup of all MinIO data:

```bash
# Export all objects from MinIO to a local host directory
docker compose run --rm minio-init mc mirror local/ /backup/minio-mirror/
```

Alternatively, back up the underlying Docker volume directly:
```bash
docker run --rm -v hosts_minio-data:/data -v $(pwd):/backup alpine \
  tar -czf /backup/minio_data_backup_$(date +%F).tar.gz -C /data .
```

### 4.2. Restoring MinIO from a Backup

1. Stop the application stack:
   ```bash
   docker compose down
   ```

2. Restore data into the `minio-data` volume:
   ```bash
   docker run --rm -v hosts_minio-data:/data -v $(pwd):/backup alpine \
     tar -xzf /backup/minio_data_backup_YYYY-MM-DD.tar.gz -C /data
   ```

3. Restart the stack:
   ```bash
   docker compose up -d
   ```

---

## 5. Troubleshooting Common Issues

### Issue 1: `minio` Container Fails Healthcheck

**Symptom**: `docker compose ps` shows `minio (unhealthy)`.

**Check**:
```bash
docker compose logs minio
```

**Resolution**:
- Ensure port 9000 and 9001 are not in use by other processes on the host.
- Verify directory permissions on `/data` within the volume.
- Restart the container:
  ```bash
  docker compose restart minio
  ```

### Issue 2: `minio-init` Exits with Error

**Symptom**: Buckets are missing or `minio-init` logs connection refused.

**Resolution**:
- `minio-init` depends on `minio` reaching a healthy state. If MinIO took longer than usual to boot on cold start, re-run initialization:
  ```bash
  docker compose up minio-init
  ```

### Issue 3: WebApp Cannot Connect to MinIO

**Symptom**: Uploads fall back to local disk (`Storage payload upload notice` in logs).

**Resolution**:
- Check that `MINIO_ENDPOINT` is set to `http://minio:9000` (internal Docker network URL).
- Verify credentials in `.env` match `MINIO_ROOT_USER` and `MINIO_ROOT_PASSWORD`.
- Test connectivity from the web container:
  ```bash
  docker compose exec web curl -I http://minio:9000/minio/health/live
  ```
