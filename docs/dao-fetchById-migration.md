# DAO `fetchById` / `destroyFetchByIdCache` Migration Plan

## Goal

Replace per-DAO dynamic `findById($id)` / `getById($id)` methods (that query by primary key `id`) with the generic `AbstractDao::fetchById(int $id, string $fetchClass, ?int $ttl = null)` and `AbstractDao::destroyFetchByIdCache(int $id, string $fetchClass)`.

---

## Category A: Easy Drop-In Replacement (fetch)

These methods already query `WHERE id = :id`, return a single struct (or null/false), and have no side effects beyond the query itself.

| # | DAO | Method | Return Type | Callers | TTL | Notes |
|---|-----|--------|-------------|---------|-----|-------|
| 1 | `ConnectedServiceDao` | `findById(int $id)` | `ConnectedServiceStruct\|false` | `GDriveUserAuthorizationModel:113`, `DownloadController:694` | 0 | Return type `false` → `null`. Trivial. |
| 2 | `CommentDao` | `getById(int $id, int $ttl = 86400)` | `?BaseCommentStruct` | `CommentController:164` | 86400 | Already uses `_fetchObjectMap`. Direct replacement with `fetchById($id, BaseCommentStruct::class, 86400)`. |
| 3 | `FilesPartsDao` | `getById(int $id, int $ttl = 0)` | `?FilesPartsStruct` | `Translated.php:441` | 604800 | Already uses `_fetchObjectMap`. Direct replacement with `fetchById($id, FilesPartsStruct::class, 604800)`. |
| 4 | `EntryCommentDao` | `findById(int $id)` | `?EntryCommentStruct` | *No dynamic callers currently* | 0 | Dead method candidate. Remove or convert. |
| 5 | `MembershipDao` | `findById(int $id)` | `MembershipStruct\|false` | *No dynamic callers currently* | 0 | Dead method candidate. Remove or convert. |

### Migration for Category A

```php
// Before:
$dao->findById($lastId);                                    // ConnectedServiceDao
$commentDao->getById($idComment);                           // CommentDao
(new FilesPartsDao())->getById($segment->id_file_part, 604800); // FilesPartsDao

// After:
$dao->fetchById($lastId, ConnectedServiceStruct::class);
$commentDao->fetchById($idComment, BaseCommentStruct::class, 86400);
(new FilesPartsDao())->fetchById($segment->id_file_part, FilesPartsStruct::class, 604800);
```

---

## Category B: Requires Adaptation (fetch)

These query by PK `id` but have additional behavior that `AbstractDao::fetchById` won't replicate out of the box.

### B1 — SegmentDao::getById (extra debug logging on miss)

| # | DAO | Method | Return Type | Callers | TTL |
|---|-----|--------|-------------|---------|-----|
| 1 | `SegmentDao` | `getById(int $id_segment)` | `?SegmentStruct` | `EntryValidator:90`, `SetTranslationController:665`, `GetSearchController:404` | 0 |

**Issue**: Contains debug logging when the segment is not found:
```php
LoggerFactory::getLogger('exception_handler')->debug(
    "*** Segment not found in database. Skipping: " . $id_segment, $stmt->errorInfo()
);
```

**Resolution options**:
1. **Keep thin wrapper** — `SegmentDao::getById()` calls `$this->fetchById($id, SegmentStruct::class)` and adds logging on `null` result. The wrapper becomes a 3-line method.
2. **Remove logging** — If it's no longer needed (debugging artifact), delete it and use `fetchById` directly.

---

### B2 — ApiKeyDao::getById (returns list, not single struct)

| # | DAO | Method | Return Type | Callers | TTL |
|---|-----|--------|-------------|---------|-----|
| 1 | `ApiKeyDao` | `getById(int $id)` | `list<ApiKeyStruct>` | `ApiKeyDao:52` (self, after insert) | 0 |

**Issue**: Returns `array` (list) even though PK guarantees 0-1 results. Likely a design oversight.

**Resolution**:
1. Change caller to: `$result = $this->fetchById((int)$conn->lastInsertId(), ApiKeyStruct::class);` (returns `?ApiKeyStruct`).
2. Update `ApiKeyDao::create()` return type from `$result[0]` to just `$result`.

---

### B3 — OwnerFeatureDao::getById (static method, called via $this)

| # | DAO | Method | Return Type | Callers | TTL |
|---|-----|--------|-------------|---------|-----|
| 1 | `OwnerFeatureDao` | `static getById(int $id)` | `?OwnerFeatureStruct` | `OwnerFeatureDao:50` (`$this->getById()` after insert) | 0 |

**Issue**: Method is `public static`. Called via `$this->getById()` (PHP allows this but it's misleading).

**Resolution**:
1. Change caller at line 50 to: `$record = $this->fetchById((int) $conn->lastInsertId(), OwnerFeatureStruct::class);`
2. Deprecate or remove the static `getById()`.

---

## Category C: Easy Drop-In Replacement (cache destruction)

These `destroyCache*ById` methods destroy cache for a `WHERE id = :id` query — directly replaceable with `AbstractDao::destroyFetchByIdCache(int $id, string $fetchClass)`.

| # | DAO | Method | Dynamic Callers |
|---|-----|--------|-----------------|
| 1 | `ProjectDao` | `static destroyCacheById(int $id)` | `ProjectDao:117` (self, changePassword), `ProjectDao:135` (self, changeName), `ProjectModel:265`, `ChangePasswordController:100,146`, `ChangeProjectNameController:84`, `aligner/ProjectDao:56` |
| 2 | `TeamDao` | `destroyCacheById(int $id)` | `ProjectCreation/ProjectManager:357` (via `destroyCacheAssignee` which calls different method — **excluded**) |

### Notes on ProjectDao::destroyCacheById

The `ProjectDao::destroyCacheById` is **static** but uses `self::$sql_find_by_id` = `"SELECT * FROM projects WHERE id = :id"`. The `AbstractDao::destroyFetchByIdCache` uses the same SQL template via `FIND_BY_ID_SQL` constant. **However**, the cache keys must match between fetch and destroy:

- `AbstractDao::fetchById` uses keyMap: `static::class . "::findById-" . $id`
- `ProjectDao::destroyCacheById` currently uses the default keyMap derived from the caller backtrace.

**For the replacement to work correctly**: callers must migrate their `findById` fetch calls to `AbstractDao::fetchById` FIRST. Then the destroy calls can use `destroyFetchByIdCache`. Otherwise the keyMap namespaces won't match and cache invalidation fails silently.

### Migration for Category C

```php
// Before:
ProjectDao::destroyCacheById($id);                   // static call
$pDao->destroyCacheById($id);                        // instance call to static method
$teamDao->destroyCacheById($id);                     // instance call

// After:
(new ProjectDao())->destroyFetchByIdCache($id, ProjectStruct::class);
$pDao->destroyFetchByIdCache($id, ProjectStruct::class);
$teamDao->destroyFetchByIdCache($id, TeamStruct::class);
```

---

## Category D: Requires Adaptation (cache destruction)

### D1 — TeamDao::destroyCacheById (keyMap mismatch risk)

| # | DAO | Method | Dynamic Callers |
|---|-----|--------|-----------------|
| 1 | `TeamDao` | `destroyCacheById(int $id)` | *No external callers found* (TeamDao's own `findById` is also dead — never called dynamically) |

**Issue**: `TeamDao::destroyCacheById` is currently **dead code** (no external callers after your TeamDao fix). It can be safely removed if `TeamDao` now uses `AbstractDao::fetchById` + `destroyFetchByIdCache`.

---

### D2 — Callers that only destroy but never fetch by PK `id`

The following `destroyCacheById` calls exist **without** a corresponding dynamic `findById`/`getById` fetch call in the same DAO:

| DAO | destroyCache method | Has matching fetchById? |
|-----|--------------------|-----------------------|
| `ProjectDao::destroyCacheById` | Yes (6 callers) | **No** — ProjectDao has no instance `findById` or `getById`. Only static `exists()` uses `$sql_find_by_id`. |

**Risk**: If `ProjectDao` doesn't actually use `AbstractDao::fetchById` to populate the cache, then `destroyFetchByIdCache` will be a no-op (destroying a cache key that was never set).

**Resolution**: Either:
1. Migrate ProjectDao to use `$this->fetchById($id, ProjectStruct::class, $ttl)` for reading projects by ID (requires finding where projects are read by PK — likely in `ProjectStruct::getProject()` or similar).
2. Keep `ProjectDao::destroyCacheById` as-is until the read path is also migrated.

---

## Summary Matrix

| DAO | fetch method | Category | destroy method | Category |
|-----|-------------|----------|----------------|----------|
| `ConnectedServiceDao` | `findById` | **A** (drop-in) | *none* | — |
| `CommentDao` | `getById` | **A** (drop-in) | *none for PK* | — |
| `FilesPartsDao` | `getById` | **A** (drop-in) | *none* | — |
| `EntryCommentDao` | `findById` | **A** (dead) | *none* | — |
| `MembershipDao` | `findById` | **A** (dead) | *none* | — |
| `SegmentDao` | `getById` | **B1** (logging) | *none for PK* | — |
| `ApiKeyDao` | `getById` | **B2** (returns list) | *none* | — |
| `OwnerFeatureDao` | `static getById` | **B3** (static) | *none for PK* | — |
| `ProjectDao` | *none (static only)* | — | `destroyCacheById` | **D2** (no matching fetch) |
| `TeamDao` | *dead* | — | `destroyCacheById` | **D1** (dead) |

---

## Recommended Migration Order

1. **Phase 1** — Category A (trivial, 3 active + 2 dead methods)
2. **Phase 2** — Category B (SegmentDao wrapper, ApiKeyDao return type, OwnerFeatureDao static→instance)
3. **Phase 3** — Category C/D (ProjectDao cache: migrate read path first, then destroy follows)

---

## Already Migrated

- `ProjectCompletionRepository` — already calls `$this->projectDao->destroyFetchByIdCache($pid, ProjectStruct::class)` (lines 149, 159). This means ProjectDao's read path for projects by ID must already be using `fetchById` somewhere in the analysis pipeline, OR this is a premature migration that destroys a key never written (investigate).
