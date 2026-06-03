# Matecat Codebase Review

**Date:** 2026-04-03  
**Scope:** `lib/` (737 PHP files), `tests/` (211 PHP files)  
**Method:** Static analysis, grep-based metrics, manual sampling of key files  
**Verdict:** C+ — Solid bones, real technical debt in the seams. Active improvement trajectory.

---

## Table of Contents

1. [Codebase at a Glance](#codebase-at-a-glance)
2. [What Is Good](#what-is-good)
3. [What Is Shit — The Critical Issues](#what-is-shit--the-critical-issues)
4. [What Is Annoying But Manageable](#what-is-annoying-but-manageable)
5. [Scores by Area](#scores-by-area)
6. [Prioritised Remediation Roadmap](#prioritised-remediation-roadmap)

---

## Codebase at a Glance

| Metric                                     | Value                                                             |
| ------------------------------------------ | ----------------------------------------------------------------- |
| PHP source files (`lib/`)                  | 737                                                               |
| PHP test files (`tests/`)                  | 211                                                               |
| Test-to-source file ratio                  | ~29%                                                              |
| Commits since 2025-01-01                   | 2,332                                                             |
| Commits since 2024-01-01                   | 4,549                                                             |
| Commits since 2023-01-01                   | 5,619                                                             |
| `Database::obtain()` call sites            | 324 across 99 files (in `lib/`); 751 across 266 files (full repo) |
| `$_POST`/`$_GET`/`$_REQUEST` direct access | 43 usages in `lib/`                                               |
| TODO / FIXME / HACK markers                | 19                                                                |
| `mixed` typed properties                   | 21                                                                |
| `mixed` return types                       | 30                                                                |

---

## What Is Good

### 1. No Global Variables

```bash
$ grep -rn "global \$" lib/ | wc -l
0
```

Zero `global $var` in the entire codebase. For a ~10-year-old PHP project, this is genuinely impressive and suggests the architecture was intentionally designed to avoid procedural patterns.

---

### 2. Clean Namespace Structure

`lib/` is consistently namespaced under `Controller\`, `Model\`, `Utils\`, and `Plugins\`. No spaghetti files at the root. The directory layout maps directly to namespaces. A developer can find anything in under 30 seconds.

```
lib/
├── Controller/API/{V1,V2,V3,App,GDrive}/
├── Model/{Projects,Jobs,Segments,Users,Analysis,...}/
├── Utils/{Engines,Tools,AsyncTasks,Redis,...}/
└── Plugins/Features/{ReviewExtended,TranslationVersions,...}/
```

---

### 3. PDO Throughout, No Legacy `mysql_*`

All database access goes through PDO with `ERRMODE_EXCEPTION`. No `mysql_query()`, no `mysqli_*`. Prepared statements are the norm.

```php
// lib/Model/DataAccess/Database.php
$this->connection = new PDO(
    "mysql:host=$this->server;dbname=$this->database",
    $this->user, $this->password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, ...]
);
```

---

### 4. `IDatabase` Interface Exists

`AbstractDao` accepts an optional `IDatabase $con` in its constructor, making DAOs technically mockable in tests:

```php
// lib/Model/DataAccess/AbstractDao.php
public function __construct(?IDatabase $con = null)
{
    if ($con == null) {
        $con = Database::obtain(); // fallback to singleton — see Issue #1
    }
    $this->database = $con;
}
```

The interface was the right architectural decision. The problem is that callers rarely pass it.

---

### 5. Feature/Plugin System Is Thoughtfully Designed

`FeatureSet::filter()` and `FeatureSet::run()` implement a clean WordPress-style hook system:

```php
// Plugins override behaviour without touching core:
$newStruct = $this->featureSet->filter(
    'filterContributionStructOnMTSet',
    $contributionStruct,
    $_Translation,
    $this->data['segment'],
    $this->filter
);
```

This is used in 142 places across the codebase, enabling the Airbnb plugin and others to inject behaviour cleanly. The pattern is consistent and well-understood by the team.

---

### 6. `ProjectStructure` Enforces a Closed Schema

`AbstractDaoObjectStruct::__set()` throws a `DomainException` on writes to unknown properties:

```php
public function __set($name, $value)
{
    if (!property_exists($this, $name)) {
        throw new DomainException('Unknown property ' . $name);
    }
    $this->$name = $value;
}
```

This prevents silent state drift via typos or dynamic property creation. `ProjectStructure`'s 83 fields are at least all explicitly declared and grouped by lifecycle phase (see Issue #2 for why it's still a problem).

---

### 7. ProjectCreation Test Suite Is Genuinely Good

`tests/unit/Model/ProjectCreation/` has 30+ focused unit tests with proper mocking patterns:

```php
// TestableProjectManager bypasses the heavy constructor
class TestableProjectManager extends ProjectManager
{
    public function __construct() {} // intentionally empty

    public function initForTest(
        MateCatFilter $filter,
        FeatureSet $features,
        MetadataDao $filesMetadataDao,
        MatecatLogger $logger,
    ): void { ... }
}
```

Test coverage breakdown is documented inline. PHPUnit 12.4 (current). This area shows what the rest of the codebase should look like.

---

### 8. Active Development Velocity

2,332 commits in 2025, with conventional commits now being adopted. Ongoing refactoring of `ProjectManager` into focused services (`SegmentExtractor`, `FileInsertionService`, `JobCreationService`, etc.) shows the team is aware of the debt and is paying it down.

---

## What Is Shit — The Critical Issues

### Issue 1: `Database::obtain()` Singleton — 324 Call Sites in 99 Files (`lib/` only; 751 across 266 files repo-wide)

**Severity: 🔴 Critical**

```php
// Appears 324 times across 99 files:
$conn = Database::obtain()->getConnection();
$db   = Database::obtain();
Database::obtain()->begin();
Database::obtain()->commit();
```

`Database::obtain()` is a classic Singleton that acts as a hidden global. Even though `IDatabase` exists, most code bypasses it:

- `AbstractDao` falls back to `Database::obtain()` when no `$con` is passed
- Controllers call `Database::obtain()->begin()` directly for transactions
- DAOs call `Database::obtain()->getConnection()` to write raw PDO queries

**Consequences:**

- Unit tests that need DB interaction must either spin up a real DB or fight the singleton
- The singleton carries a single connection across the entire request lifecycle; re-entrant transactions are impossible
- No connection pooling; no read/write split is achievable without refactoring 99 files

**What it should be:** Pass `IDatabase` through constructors everywhere. The interface already exists — it just needs to be wired properly.

---

### Issue 2: `ProjectStructure` Is an 83-Property God Object

**Severity: 🔴 Critical**

```php
// lib/Model/ProjectCreation/ProjectStructure.php — 83 public properties:
// Group A (49 keys): init-only input
public ?string $project_name = null;
public ?array $target_language = null;
// ...

// Group B (10 keys): mutable pipeline state
public mixed $xliff_parameters = [];
public mixed $session = null;

// Group C (14 keys): per-file transient data
public array $segments = [];
public array $translations = [];

// Group D: output/result keys
public array $result = ['errors' => [], 'data' => []];
public array $array_jobs = ['job_list' => [], ...];
```

All four groups live in the same object, which is passed **by reference** through the entire creation pipeline and mutated by every service. This means:

- `SegmentExtractor` reads from Group A and writes to Group C
- `JobCreationService` reads from Groups A+C and writes to Group D
- `ProjectMetadataService` reads from Groups A+B
- Nothing enforces which stage can write to which group

**Consequences:**

- Impossible to reason about what state the object is in at any given point
- Tests must pre-populate all 83 fields or leave them in potentially invalid defaults
- Serialisation to the job queue (via `JsonSerializable`) ships ALL state, including transient per-file data

**What it should be:** Three or four distinct, immutable-input DTOs passed to specific services, with a separate output result type.

---

### Issue 3: Massive Controllers That Mix All Concerns

**Severity: 🔴 Critical**

| File                                              | Lines | Methods |
| ------------------------------------------------- | ----- | ------- |
| `Controller/API/V1/NewController.php`             | 1,337 | 25      |
| `Controller/API/App/SetTranslationController.php` | 1,030 | 17      |
| `Controller/API/App/CreateProjectController.php`  | 897   | 21      |
| `Controller/API/V2/DownloadController.php`        | 957   | 17      |

`NewController::validateTheRequest()` alone is **281 lines** — it validates every possible parameter for project creation in a single method. It handles language pairs, QA models, MT engines, XLIFF parameters, glossaries, filters, and payable rates all in one giant array-building function.

`SetTranslationController` combines: HTTP parsing, translation validation, segment fetching, TM contribution, MT contribution, activity logging, propagation, and error handling.

**Consequence:** These cannot be unit-tested. Changes in one area risk breaking unrelated behaviour.

---

### Issue 4: God Utility Classes

**Severity: 🟠 High**

| File                       | Lines | Static Methods | Responsibilities                                                                                  |
| -------------------------- | ----- | -------------- | ------------------------------------------------------------------------------------------------- |
| `Utils/Tools/CatUtils.php` | 1,067 | 35             | Regex placeholders, word count, segment splitting, CJK detection, filter calls, tag manipulation  |
| `Utils/Tools/Utils.php`    | 925   | 34             | Password hashing, IP detection, file naming, email sending, UUID generation, timestamp formatting |

These are untestable bags of static functions with no cohesion. They accumulate every utility that didn't fit elsewhere:

```php
// CatUtils — totally unrelated concerns in one class:
CatUtils::isCJK($lang);
CatUtils::getWordCount($segment, $lang, ...);
CatUtils::placeholderToLayer1($segment);
CatUtils::checkParallelTokenConsistency($s, $t);
```

---

### Issue 5: `SegmentDao` and `TMAnalysisWorker` Are Monoliths

**Severity: 🟠 High**

| File                                                     | Lines | Methods |
| -------------------------------------------------------- | ----- | ------- |
| `Model/Segments/SegmentDao.php`                          | 1,108 | 10      |
| `Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php` | 1,055 | 18      |

`SegmentDao` is a 1,108-line DAO with 10 methods — some returning raw arrays, some returning `SegmentStruct[]`, some running complex multi-join queries inline. At that size, a DAO is no longer just data access; it's accumulated business logic with nowhere else to go.

`TMAnalysisWorker` at 1,055 lines handles: queue message decoding, TM lookup, MT fallback, match scoring, segment update, pre-translation logic, and analysis data persistence — in one class.

---

### Issue 6: `FeatureSet::filter()` Uses Unsafe Variadic Dispatch

**Severity: 🟠 High**

```php
public function filter(string $method, mixed $filterable): mixed
{
    $args = array_slice(func_get_args(), 1); // ← grabs ALL args dynamically

    foreach ($this->features as $feature) {
        $obj = $feature->toNewObject();
        if (method_exists($obj, $method)) {
            array_shift($args);
            array_unshift($args, $filterable); // ← manual arg shifting each iteration
            $filterable = call_user_func_array([$obj, $method], $args);
        }
    }
    return $filterable;
}
```

Problems:

- `func_get_args()` is invisible to static analysis (PHPStan/Psalm cannot infer types)
- The `array_shift` / `array_unshift` dance mutates `$args` each iteration — adding extra args beyond `$filterable` requires careful counting
- Plugin authors have no type-safe contract for what `$filterable` is
- The return type is `mixed`, so callers must cast

This is used in 142 places across the codebase.

---

### Issue 7: `ProjectStruct` Is Both a DAO Struct and an Active Record

**Severity: 🟠 High**

`ProjectStruct` (the DB-mapped entity, not `ProjectStructure`) directly instantiates DAOs and makes DB calls:

```php
// lib/Model/Projects/ProjectStruct.php
public function setMetadata(string $key, string $value): bool
{
    $dao = new MetadataDao(Database::obtain()); // ← DB call inside a "value object"
    return $dao->set($this->id, $key, $value);
}

public function getJobs(int $ttl = 0): array
{
    return $this->cachable(__METHOD__, function () use ($ttl) {
        return JobDao::getByProjectId($this->id, $ttl); // ← static DAO call
    });
}
```

This happens in 6 methods across `ProjectStruct`. The struct is neither a pure DTO nor a clean Active Record — it's a hybrid that makes side effects invisible to callers.

---

### Issue 8: A Confirmed Bug Commented with TODO and Never Fixed

**Severity: 🟠 High**

```php
// lib/Model/DataAccess/Database.php:319
foreach ($data as $key => $value) {
    // ...
    $valuesToBind[":dupUpdate_" . $key] = $value;
    //TODO this is a bug: bind values are not returned and not inserted in the mask
}
```

There is a known bug in the `Database` layer itself — in the `ON DUPLICATE KEY UPDATE` path — that has been commented as `TODO` and left unfixed. The scope of this bug is unclear but it affects any code that uses `upsert`-style queries.

Additionally:

```php
// lib/Model/DataAccess/Database.php:364
/**
 * TODO this trim should be removed and ALL codebase migrated from $db->escape()
 * to prepared Statements
 */
```

Manual escaping via `->escape()` is still used in `UserDao` and `SearchModel` despite the comment explicitly saying all code should be migrated to prepared statements.

---

## What Is Annoying But Manageable

### A. `$_POST` / `$_GET` / `$_REQUEST` Direct Access — 43 Usages

Most are concentrated in the legacy `lib/View/fileupload/UploadHandler.php` (a vendor-ish file). A handful appear in newer V3 controllers. The modern controllers use `$this->request->param()` properly.

```php
// lib/Controller/API/V3/DeepLGlossaryController.php:58
$name = filter_var($_POST['name'], FILTER_SANITIZE_SPECIAL_CHARS, ...);
```

**Fix:** Move these to the Klein request abstraction already used elsewhere.

---

### B. `mixed` Type Overuse — 21 Properties, 30 Return Types

`ProjectStructure` contributes heavily here:

```php
public mixed $qa_model_template = null;
public mixed $qa_model = null;
public mixed $filters_extraction_parameters = null;
public mixed $session = null;
```

These fields have been `mixed` because they carry heterogeneous data that was never properly typed. Each is a candidate for a specific DTO or union type.

---

### C. `old_tests/` — 36 Dead Test Files

`old_tests/` contains 36 PHP test files that are not in the PHPUnit configuration and are not run in CI. They are dead code and create confusion.

**Fix:** Delete the directory.

---

### D. Static Initializer Magic in `TMAnalysisWorker` and Others

Several workers and DAOs use static state that persists across test runs:

```php
// lib/Model/DataAccess/AbstractDao.php
protected static array $auto_increment_field = [];

public function __construct(?IDatabase $con = null)
{
    // ...
    self::$auto_increment_field = []; // reset on every construction
}
```

Static arrays reset in the constructor is a code smell — it indicates state is being managed at the wrong scope level.

---

### E. `VersionHandlerInterface` Uses Array Shape Docblock Instead of Typed Contract

```php
// lib/Plugins/Features/TranslationVersions/VersionHandlerInterface.php
/**
 * @param array{
 *     translation: SegmentTranslationStruct,
 *     old_translation: SegmentTranslationStruct,
 *     propagation: array,
 *     chunk: JobStruct,
 *     user: UserStruct,
 *     source_page_code: int,
 *     features: FeatureSet,
 * } $data
 */
public function handle(array $data): void;
```

The `array $data` parameter is shaped only in a docblock. PHP has no runtime enforcement. An implementing class that reads `$data['chunk']` has no IDE guarantee it's a `JobStruct`.

**Fix:** Define a proper `VersionHandlerData` value object and type the parameter.

---

## Scores by Area

| Area                            | Grade                   | Rationale                                                                    |
| ------------------------------- | ----------------------- | ---------------------------------------------------------------------------- |
| Namespace / directory structure | **B+**                  | Clean, consistent, navigable                                                 |
| Database / SQL safety           | **C**                   | PDO ✓, singleton ✗, confirmed bug ✗, manual escaping ✗                       |
| Dependency injection            | **C**                   | `IDatabase` interface exists but fallback to singleton dominates (99 files)  |
| Type safety                     | **C+**                  | Improving; `mixed` overuse (21+30); no generics                              |
| God objects / SRP               | **D**                   | `ProjectStructure` (83 props), `CatUtils` (35 statics), `Utils` (34 statics) |
| Controller size / separation    | **D**                   | `NewController` 1337 lines, `SetTranslationController` 1030 lines            |
| DAO layer                       | **C**                   | 1108-line SegmentDao, Active Record leak in ProjectStruct                    |
| Plugin system                   | **B**                   | Clever hook design; type-unsafe dispatch                                     |
| Test coverage                   | **C+**                  | Good for ProjectCreation, sparse elsewhere (29% file ratio)                  |
| Test quality                    | **B** (where it exists) | Proper mocking, `TestableProjectManager` pattern is correct                  |
| Technical debt visibility       | **C**                   | 19 TODOs; one confirmed DB bug unaddressed                                   |
| Development velocity            | **A**                   | Active, improving; conventional commits adopted                              |

**Overall: C+**

---

## Prioritised Remediation Roadmap

### Priority 1 — Fix the Confirmed Bug

**File:** `lib/Model/DataAccess/Database.php:319`  
The `ON DUPLICATE KEY UPDATE` bind-values bug must be diagnosed and fixed before it causes silent data corruption. It has a TODO comment but no corresponding issue or fix.

---

### Priority 2 — Break the `Database::obtain()` Singleton Dependency

**Goal:** All 99 files that call `Database::obtain()` should receive `IDatabase` via constructor injection.

**Approach:**

1. Audit all 99 files; start with the DAOs (they already accept `IDatabase` in constructors)
2. Wire the `IDatabase` instance from a central bootstrap / DI container
3. Remove the `Database::obtain()` fallback in `AbstractDao::__construct()`
4. The singleton can remain in `bootstrap.php` as the single creation site

**Impact:** Every DAO becomes unit-testable without a real DB.

---

### Priority 3 — Split `ProjectStructure` Into Phase-Specific DTOs

**Goal:** Eliminate the 83-property god object.

**Approach:**

1. Extract a `ProjectCreationInput` immutable DTO (current Group A)
2. Extract a `ProjectCreationResult` DTO (current Group D)
3. Keep pipeline mutable state internal to `ProjectManager` (not in a shared bag)
4. Remove `JsonSerializable` from the DTO; use an explicit serialiser for queue transport

---

### Priority 4 — Break Up the Monolith Controllers

**Target files:**

- `NewController.php` (1337 lines) → extract `ProjectCreationRequestParser`, `ProjectCreationOrchestrator`
- `SetTranslationController.php` (1030 lines) → extract `TranslationSaver`, `ContributionDispatcher`

`validateTheRequest()` at 281 lines alone should be a dedicated request-validator class.

---

### Priority 5 — Dissolve `CatUtils` and `Utils` God Classes

Break them up by actual responsibility:

- `CatUtils` → `SegmentFormatter`, `WordCounter`, `LanguageUtils`, `TagReplacer`
- `Utils` → `AuthHelper`, `FileHelper`, `NetworkHelper`, `DateHelper`

Make them instance classes (or at least group related statics under narrowly-scoped classes) so they can be tested and injected.

---

### Priority 6 — Type the FeatureSet Filter Dispatch

Replace:

```php
public function filter(string $method, mixed $filterable): mixed
```

with a typed, contract-based dispatch. Each hook point should have a named interface (`FilterContributionStructInterface`, etc.) with a typed method signature. Static analysis can then verify plugin implementations.

---

### Priority 7 — Delete `old_tests/`, Fix `$_REQUEST` Usages

Small wins:

- Delete `old_tests/` (36 dead files, zero CI value)
- Move remaining `$_POST`/`$_GET` direct accesses to the Klein request abstraction
- Replace `->escape()` in `UserDao` and `SearchModel` with PDO bound parameters

---

_Review conducted via static analysis and manual code sampling. No profiling or runtime analysis was performed._

---

### Verification Note

_All quantitative claims in this document were cross-validated on 2026-04-03 against the actual codebase at commit `953e0d02b8` (branch `context-review`). Line counts were verified with `wc -l`, method counts with `grep -c "function "`, property counts by manual inspection, and grep-based metrics with exact shell commands. Original inaccuracies (overstated method counts, inflated `$_POST`/`mixed` metrics) have been corrected. The `->filter()` usage count was originally underreported (47 → 142) and has been updated._
