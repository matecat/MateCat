# Known Concurrency Issues — TMAnalysisWorker Pipeline

These issues **pre-date the service refactor** (PR #4570). They exist in the original
monolithic `TMAnalysisWorker` on `develop` and were carried over as-is. Documenting
them here as known technical debt for future remediation.

---

## CRITICAL

### 1. DB/Redis split-brain — lost-count / never-complete bug

**Where:** `TMAnalysisWorker::process()` — the gap between `setAnalysisValue()` (MySQL)
and `incrementAnalyzedCount()` (Redis).

**Scenario:** DB update succeeds and commits → Redis `INCRBY` throws (network blip,
Predis timeout) → the Executor catches `Predis\Connection\ConnectionException` and
requeues → on retry, `setAnalysisValue()` returns `0` (row already DONE) → worker
skips all side effects → segment is DONE in MySQL but **never counted in Redis**.
The project never completes.

**Impact:** The project's `num_analyzed` counter permanently lags behind reality.
`tryCloseProject()` never fires.

### 2. Crash during project completion loses liveness

**Where:** `ProjectCompletionService::tryCloseProject()`

**Scenario:** Worker acquires the completion lock (NX, TTL 86400s) and calls
`removeProjectFromQueue()` → process crashes (OOM, SIGKILL) before reaching the
catch block → lock is held for 24 h, project is removed from the queue, and no
other worker can retry closure.

**Impact:** Project stuck in non-DONE state for up to 24 hours.

### 3. Initialization is not crash-safe

**Where:** `TMAnalysisWorker::initializeTMAnalysis()` /
`AnalysisRedisService::acquireInitLock()`

**Scenario:** The init-lock winner dies after acquiring the NX semaphore but before
writing `PROJECT_TOT_SEGMENTS` → all other workers spin in `waitForInitialization()`
up to 5 s then proceed without totals → `tryCloseProject()` returns early because
`project_segments` is empty.

**Impact:** All workers process segments but the project can never complete until
the 86400 s lock TTL expires and a new winner re-initializes.

---

## HIGH

### 4. Non-atomic counter group

**Where:** `AnalysisRedisService::incrementAnalyzedCount()` — three separate `INCRBY`
calls for `eq_wc`, `st_wc`, and `num_done`.

**Risk:** If a failure occurs between increments, word counts and segment count
diverge. A Lua script or `MULTI/EXEC` pipeline would make them atomic.

### 5. Duplicate delivery race on same segment

**Where:** No per-segment claim/lock exists in the worker pipeline.

**Risk:** If ActiveMQ delivers the same segment to two workers (redelivery, connection
drop), both may compute results. Correctness depends entirely on the DAO's
`WHERE tm_analysis_status NOT IN ('DONE','SKIPPED')` predicate returning `0`
affected rows for the loser.

### 6. `forceSetSegmentAnalyzed()` has no terminal-state guard

**Where:** `SegmentUpdaterService::forceSetSegmentAnalyzed()`

**Risk:** The `UPDATE` uses `WHERE id_segment = ? AND id_job = ?` without checking
the current `tm_analysis_status`. Under duplicate delivery, both workers could
see `affectedRows > 0` and double-increment Redis counters.

### 7. Equality close condition is fragile

**Where:** `ProjectCompletionService::tryCloseProject()` —
`(int)$projectTotals['project_segments'] - (int)$projectTotals['num_analyzed'] === 0`

**Risk:** If `num_analyzed` ever exceeds `project_segments` (e.g., double-counting
from issue #6), the subtraction is negative and `=== 0` is never true. The project
is permanently stuck. Using `<= 0` would be more defensive.

---

## MEDIUM

### 8. TOCTOU in initialization wait

**Where:** `AnalysisRedisService::waitForInitialization()`

**Risk:** Workers check for `PROJECT_TOT_SEGMENTS` but the baseline `num_analyzed`
set by the winner may not be written yet. Workers could proceed with stale or
missing baseline counts.

### 9. Lock TTL is operationally dangerous

**Where:** Both `acquireInitLock()` and `acquireCompletionLock()` use `'EX', 86400`.

**Risk:** A transient crash (OOM, network partition) holds the lock for 24 hours,
turning a momentary failure into a day-long outage. Shorter TTLs with renewal
would be more resilient.

---

## Recommended Remediation (future PR)

1. **Atomic side effects**: wrap `INCRBY` calls in a Lua script or `MULTI/EXEC`.
2. **Terminal-state guard on `forceSetSegmentAnalyzed`**: add
   `AND tm_analysis_status NOT IN ('DONE','SKIPPED')` to the `WHERE` clause.
3. **Defensive close condition**: use `<= 0` instead of `=== 0`.
4. **Crash-safe initialization**: use an explicit "init complete" flag, and clean up
   partial state on failure.
5. **Shorter lock TTLs with renewal**: e.g., 60 s TTL with periodic extension while
   work is in progress.
6. **DB-authoritative completion**: recompute segment counts from MySQL in
   `tryCloseProject()` instead of relying solely on Redis counters.
