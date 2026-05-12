# Known Concurrency Issues — TMAnalysisWorker Pipeline

These issues **pre-date the service refactor** (PR #4570). They exist in the original
monolithic `TMAnalysisWorker` on `develop` and were carried over as-is. Documenting
them here as known technical debt for future remediation.

**Last updated:** 2026-05-12 against refactored codebase. Severities reassessed after
mitigations applied. Risk tolerance: project loss is acceptable; permanent silent
failures are not.

---

## FIXED

### 1. ~~DB/Redis split-brain — lost-count / never-complete bug~~ — FIXED

**Where:** `TMAnalysisWorker::process()` — the gap between `setAnalysisValue()` (MySQL)
and `incrementAnalyzedCount()` (Redis).

**Original scenario:** DB update succeeds and commits → Redis `INCRBY` throws →
segment is DONE in MySQL but never counted in Redis → project never completes.

**Fix (two layers):**
- **Layer 1:** `applyPostCommitSideEffects()` retries `incrementAnalyzedCount` with
  exponential backoff (500ms initial, 7.5s total window) + Predis reconnect between
  retries. Handles transient Redis blips.
- **Layer 2 (root fix):** `tryCloseProject()` now has a DB-authoritative gate —
  after Redis triggers a close attempt and the completion lock is acquired, MySQL
  is queried via `getProjectSegmentsTranslationSummary()` to verify all segments
  are actually DONE/SKIPPED before proceeding. If MySQL disagrees (Redis/MySQL
  drift from sustained outage), the lock is released and the close is aborted.
  Redis is the fast trigger, MySQL is the judge.

### 3. ~~Initialization is not crash-safe~~ — FIXED

**Where:** `TMAnalysisWorker::initializeTMAnalysis()` /
`AnalysisRedisService::acquireInitLock()`

**Fix:** Init lock TTL reduced from 86400s to 30s. On timeout, losers re-try
`acquireInitLock()` — if the winner crashed, the lock expired and a loser
becomes the new winner and re-initializes from MySQL. New
`initializeProjectCounters()` wraps all init writes in a Redis MULTI/EXEC
transaction for guaranteed atomic ordering.

### 6. ~~Equality close condition is fragile~~ — FIXED

**Where:** `ProjectCompletionService::tryCloseProject()`

**Fix:** Changed `=== 0` to `<= 0`. If `num_analyzed` ever exceeds `project_segments`
(e.g., double-counting from issue #4's partial increment), the subtraction is negative
and `<= 0` still triggers completion instead of leaving the project permanently stuck.

### 7. ~~`forceSetSegmentAnalyzed()` has no terminal-state guard~~ — FIXED

**Where:** `SegmentUpdaterService::forceSetSegmentAnalyzed()`

**Fix:** Replaced `Database::update()` with raw SQL that includes
`AND tm_analysis_status NOT IN ('DONE', 'SKIPPED')` in the WHERE clause.
Under duplicate delivery, only the first worker can transition the segment —
the second sees `affectedRows=0` regardless of `MYSQL_ATTR_FOUND_ROWS` config.

### 8. ~~TOCTOU in initialization wait~~ — FIXED

**Where:** `AnalysisRedisService::waitForInitialization()`

**Fix:** Now polls for both `PROJECT_TOT_SEGMENTS` and `PROJECT_NUM_SEGMENTS_DONE`
before letting losers proceed. Returns `bool` to enable re-try logic for issue #3.

---

## MEDIUM

### 2. Crash during project completion — 24h delay

**Where:** `ProjectCompletionService::tryCloseProject()`

**Scenario:** Worker acquires the completion lock (NX, TTL 86400s) → process crashes
after commit but before lock release → lock held for up to 24h.

**Mitigations already applied:**
- `removeProjectFromQueue()` moved after `commit()` — crash before commit leaves
  project in queue for retry.
- DB-authoritative gate ensures correctness even if the close is delayed.

**Residual risk:** Crash in the narrow post-commit/pre-unlock window causes a
24h delay. Self-healing (lock expires). Not permanent.

**Future fix (P3):** Shorter completion lock TTL with renewal mechanism.

### 4. ~~Non-atomic per-segment counter group~~ — FIXED

**Where:** `AnalysisRedisService::incrementAnalyzedCount()`

**Fix:** All three `INCRBY` calls (`eq_wc`, `st_wc`, `num_done`) are now wrapped
in a Redis MULTI/EXEC transaction, both in the init path (`initializeProjectCounters`)
and in the per-segment path (`incrementAnalyzedCount`). A crash or connection failure
between increments can no longer leave word counts and segment count diverged.

### 5. Duplicate delivery race on same segment

**Where:** No per-segment claim/lock exists in the worker pipeline.

**Mitigations already applied:**
- `setAnalysisValue()` has `WHERE tm_analysis_status NOT IN ('DONE','SKIPPED')`.
- `forceSetSegmentAnalyzed()` has the same guard (#7).
- InnoDB row-level locking serializes concurrent UPDATEs.

**Residual risk:** Theoretical only — three independent defense layers would all
need to fail simultaneously. No practical scenario identified.

---

## LOW

### 9. Completion lock TTL is operationally long

**Where:** `acquireCompletionLock()` uses `'EX', 86400`.

**Mitigations already applied:**
- Init lock reduced to 30s (#3).
- Queue removal after commit (#2) limits blast radius.
- DB-authoritative gate ensures correctness regardless of lock state.

**Residual risk:** 24h delay on crash during completion. Self-healing.

**Future fix (P3):** Shorter TTL with renewal mechanism.

---

## Recommended Remediation (future PR)

| Priority | Fix | Addresses |
|----------|-----|-----------|
| ~~P0~~ | ~~**DB-authoritative completion**~~ **DONE** | ~~#1~~ |
| ~~P1~~ | ~~**Crash-safe initialization**~~ **DONE** | ~~#3, #8~~ |
| ~~P2~~ | ~~**Defensive close condition**~~ **DONE** | ~~#6~~ |
| ~~P3~~ | ~~**Terminal-state guard on `forceSetSegmentAnalyzed`**~~ **DONE** | ~~#7~~ |
| ~~P3~~ | ~~**Atomic per-segment side effects**~~ **DONE** | ~~#4~~ |
| P3 | **Shorter completion lock TTL with renewal**: e.g., 60s TTL with periodic extension. | #2, #9 |
