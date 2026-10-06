# Runbook: Container Restart & Cache Flush

Procedures for performing zero-downtime or graceful container restarts and recovering from cache or lock freezes.

---

## 1. Graceful Application Restart

To restart the WebApp container cleanly:

```bash
docker compose restart web
```

Verify the container is listening on port 80:
```bash
curl -f http://localhost/health
```

---

## 2. Emergency Cache & Session Purge

If the application experiences stale views, locked sessions, or permission sync issues:

### Via Admin UI:
1. Navigate to **Admin &rarr; System &rarr; Maintenance** (`/admin/system/maintenance`).
2. Click **Flush View Cache** to invalidate pre-rendered templates.
3. Click **Clear Sessions** to prune expired browser cookies and release session locks.

### Via Spark CLI:
```bash
docker compose exec web php spark cache:clear
```

---

## 3. Full Ecosystem Stack Restart

To cycle the entire stack (Database, WebApp, ML microservice):

```bash
docker compose down
docker compose up -d
```

Check health status of all containers:
```bash
docker compose ps
```\n