# Developer Troubleshooting — Mpesa Analyzer WebApp

Technical troubleshooting for software engineers working on the PHP codebase.

---

## 1. Migration Table Lock or Corrupted Migrations

**Symptom**: `MigrationException: Migration failed` or `Table already exists`.

**Remedy**:
1. Check `migrations` table in MySQL to inspect executed batches.
2. Rollback or delete errant batch entry if testing new migration files:
   ```bash
   php spark migrate:rollback
   ```

---

## 2. Session File Write Failures

**Symptom**: `ErrorException: session_start(): open(...) failed: Permission denied`.

**Remedy**:
Ensure the web server has write access to `writable/session/`:
```bash
chmod -R 775 writable/
```

---

## 3. Missing PHP Extensions on Host

**Symptom**: `Call to undefined function intl_is_failure()` or `mb_strlen()`.

**Remedy**:
Install missing PHP 8.4 modules:
```bash
sudo apt-get install -y php8.4-intl php8.4-mbstring php8.4-xml php8.4-curl php8.4-mysql
```
