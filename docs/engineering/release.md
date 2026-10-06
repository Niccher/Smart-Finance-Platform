# Ecosystem Release & Compatibility Matrix

This document tracks versioning and inter-component compatibility across the Smart Finance ecosystem.

---

## 1. Compatibility Matrix

| WebApp Version | Android App Version | ML Backend Version | Minimum API Level | Database Schema |
|----------------|---------------------|--------------------|-------------------|-----------------|
| **v3.5.0** | **v3.5.0 (build 4)** | **v3.5.0** | `/api/v1` | Migration `2026-09-25` (`tbl_Chat_Messages`) |
| **v3.4.0** | **v3.4.0 (build 3)** | **v3.4.0** | `/api/v1` | Migration `2026-09-09` |
| **v3.3.0** | **v3.3.0 (build 2)** | **v3.3.0** | `/api/v1` | Migration `2026-09-07` |
| **v3.2.0** | **v3.2.0** | **v3.2.0** | `/api/v1` | Migration `2026-08` |
| **v3.1.0** | **v3.1.0** | **v3.1.0** | `/api/v1` | Migration `2026-08` |
| **v3.0.0** | **v3.0.0** | **v3.0.0** | `/api/v1` | Migration `2026-05` |

---

## 2. Release Checklist

Before tagging a new release across repositories:

1. **Android App**:
   - Update `versionCode` and `versionName` in `app/build.gradle.kts`.
   - Verify CameraX vertical QR scanner builds cleanly.
   - Run unit tests: `./gradlew test`.
2. **ML Backend**:
   - Verify `GET /health` and `GET /admin/telemetry` endpoints respond with 200 OK.
   - Ensure default model preset is available and tested.
3. **WebApp**:
   - Update `app/Config/version.json` with new release tag.
   - Verify all database migrations run cleanly: `php spark migrate`.
   - Validate documentation quality using `lint-docs.py`.\n