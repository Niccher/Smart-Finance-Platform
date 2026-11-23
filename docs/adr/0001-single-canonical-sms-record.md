# ADR 0001: Single Canonical Record in `tbl_Sms` with Derived Views

- **Status**: Accepted
- **Date**: 2026-09-06
- **Context**: M-Pesa Analyzer Ecosystem (Database Architecture)

---

## Context and Problem Statement

Initially, SMS data ingestion, sender classification, and extracted transaction attributes were stored across multiple tables (`tbl_Sms`, `tbl_Sms_Classification`, and `tbl_Analyzed_Transactions`). 

During batch asynchronous processing by the FastAPI microservice, updating separate tables introduced:
1. Race conditions and partial write failures when transactions were extracted but classification failed to commit.
2. Complex join overhead for the CodeIgniter 4 web application when serving analytics dashboards.
3. Multiple sources of truth for transaction direction, amount, and timestamp fields.

---

## Decision

Consolidate all SMS attributes into a **single canonical record** on `tbl_Sms`:
1. Ingestion metadata, classification columns (`sms_category`, `sms_is_finance`, `sms_confidence`), and extracted transactional fields (`sms_amount`, `sms_balance`, `sms_counterparty`, `sms_direction`) reside directly on `tbl_Sms`.
2. The ML microservice performs an atomic single-row upsert (`upsert_sms_analysis()`) on `tbl_Sms`.
3. Synchronize high-throughput read tables `tbl_Sms_Classification` and `tbl_Analyzed_Transactions` directly from `tbl_Sms` to enable indexed high-performance reporting queries without view performance penalties.

---

## Consequences

### Positive
- **Atomicity**: A single SQL statement commits both classification and financial extraction.
- **Single Source of Truth**: Eliminates drift between raw message data and parsed transaction amounts.
- **Backward Compatibility**: Web dashboard controllers continue querying `tbl_Analyzed_Transactions` and `tbl_Sms_Classification` as views without breaking existing CodeIgniter model queries.

### Negative
- `tbl_Sms` has wider row width, requiring appropriate indexing on `sms_category`, `sms_is_finance`, and `sms_user_id`.

---

## Schema Architecture Summary

| Layer | Component | Type | Responsibility |
| :--- | :--- | :--- | :--- |
| **Ingestion & Canonical Store** | `tbl_Sms` | Physical Table | Canonical single source of truth for raw SMS text, sender, parsed metadata, extracted financial attributes, and category classification. |
| **Batch Worker State** | `tbl_Sms_Processing` | Physical Table | Granular per-message processing status (`pending`, `processing`, `done`, `skipped`, `error`) and worker attempt counts. |
| **Pipeline Jobs** | `tbl_Processing_Jobs` | Physical Table | Batch LLM / heuristic run lifecycle records (`queued`, `processing`, `done`, `failed`) and aggregated progress counters. |
| **Compatibility Views** | `tbl_Analyzed_Transactions`, `tbl_Sms_Classification` | MySQL Views | Zero-storage dynamic projections over `tbl_Sms` maintaining backward compatibility for legacy queries and external reports. |
| **Heuristics & Rules** | `tbl_Category_Rules` | Physical Table | System seeds and user-defined override rules (regex/exact matching) evaluated before LLM inference. |
