# In-Context Review — Feature Development Spec

> **Branch:** `context-review`
> **Started:** March 2026
> **Last updated:** 2026-04-03
> **Reference spec:** `Technical Specification_ Mapping Between Contextual Markup and Translatable Strings.md`

---

## 1. Feature Overview

In-context review allows reviewers to see translated segments within their original document layout, providing visual
context for translation quality assessment. A project is created **without a flag** declaring that an in-context review
type is enabled — it works like any other normal project. The backend extracts defined attributes from the XLIFF
`<trans-unit>` and stores them in `segments_metadata`.

---

## 2. Architecture

### 2.1 Metadata Pipeline (Backend)

```
XLIFF <trans-unit> attributes
        │
        ▼
SegmentMetadataMarshaller::isAllowed()    ← filter: only 6 keys pass
        │
        ▼
SegmentMetadataMapper::fromTransUnitAttributes()
        │  creates DB-ready structs with marshall()
        ▼
SegmentMetadataCollection
        │  read-only, iterable, countable, JsonSerializable
        ▼
SegmentStorageService::save()             ← foreach struct → SegmentMetadataDao::save()
        │
        ▼
segment_metadata table
        │
        ▼
SegmentMetadataDao::getAll() → SegmentMetadataCollection  (typed via jsonSerialize)
SegmentMetadataDao::get()    → ?SegmentMetadataStruct      (single key lookup)
```

### 2.2 Allowed Metadata Keys

| Enum Case          | DB Value (`meta_key`) | Marshall                      | Unmarshall            |
| ------------------ | --------------------- | ----------------------------- | --------------------- |
| `ID_REQUEST`       | `id_request`          | `string → string`             | `string → string`     |
| `ID_CONTENT`       | `id_content`          | `string → string`             | `string → string`     |
| `ID_ORDER`         | `id_order`            | `string → string`             | `string → string`     |
| `ID_ORDER_GROUP`   | `id_order_group`      | `string → string`             | `string → string`     |
| `SCREENSHOT`       | `screenshot`          | `string → string`             | `string → string`     |
| `SIZE_RESTRICTION` | `sizeRestriction`     | `mixed → string` (cast)       | `string → int` (cast) |
| `RESNAME`          | `resname`             | `string → string`             | `string → string`     |
| `RESTYPE`          | `restype`             | `string → string` (validated) | `string → string`     |
| `X_CLIENT_NAME`    | `x-client-name`       | `string → string`             | `string → string`     |

> `RESTYPE.marshall()` validates via `ContextResType::tryFrom()` — returns `null` for invalid values, mapper skips them.

### 2.3 Key Design Decisions

| Decision                                                     | Rationale                                                                                                   |
| ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `SegmentMetadataStruct::meta_value` stays `string`           | DB-honest — the database column is VARCHAR. No lying about what the DB returns.                             |
| `Collection::find()` returns `?string` (raw)                 | Write path — `SegmentExtractor::createSegmentHash(?string $sizeRestriction)` needs raw string.              |
| `Collection::findTyped()` returns `mixed` (typed)            | Read path — calls `unMarshall()` internally for typed access.                                               |
| `SegmentMetadataMarshaller::unMarshall()` is static          | Follows `ProjectsMetadataMarshaller::unMarshall(MetadataStruct)` pattern. Accepts struct, not string.       |
| `jsonSerialize()` serves typed values                        | API sends `sizeRestriction` as `int 42`, not `"42"`. Frontend confirmed safe (JS coercion).                 |
| No project-level flag                                        | Per requirement: "a project is created without a flag declaring that an in-context review type is enabled." |
| `ProjectManagerModel::isAMetadata()` delegates to marshaller | Single source of truth for allowed keys.                                                                    |
| `SizeRestrictionChecker::SIZE_RESTRICTION` constant removed  | Replaced by `SegmentMetadataMarshaller::SIZE_RESTRICTION->value` across entire codebase.                    |

---

## 3. Completed Work

### 3.1 Backend — Commit 1 (`7860617`)

`✨ feat: centralize segment metadata pipeline with marshaller, mapper, and collection`

#### Files Created

| File                                                          | Purpose                                                                                             |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `lib/Model/Segments/SegmentMetadataMarshaller.php`            | Backed enum (8 cases): `isAllowed()`, `marshall()`, static `unmarshall()`                           |
| `lib/Model/Segments/SegmentMetadataMapper.php`                | `fromTransUnitAttributes(array): SegmentMetadataCollection`                                         |
| `lib/Model/Segments/SegmentMetadataCollection.php`            | Read-only collection: `find()`, `findTyped()`, `IteratorAggregate`, `Countable`, `JsonSerializable` |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 22 tests                                                                                            |
| `tests/unit/Model/Segments/SegmentMetadataMapperTest.php`     | 10 tests                                                                                            |
| `tests/unit/Model/Segments/SegmentMetadataCollectionTest.php` | 15 tests (9 base + 4 findTyped + 2 jsonSerialize)                                                   |

#### Files Modified

| File                                                             | Change                                                                       |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `lib/Model/ProjectCreation/ProjectManagerModel.php`              | `isAMetadata()` delegates to `SegmentMetadataMarshaller::isAllowed()`        |
| `lib/Model/ProjectCreation/SegmentExtractor.php`                 | Uses mapper + collection, calls `$collection->find()`                        |
| `lib/Model/ProjectCreation/ProjectManager.php`                   | Added `use SegmentMetadataMapper`                                            |
| `lib/Model/ProjectCreation/SegmentStorageService.php`            | Iterates `SegmentMetadataCollection` via `foreach`                           |
| `lib/Utils/LQA/QA/SizeRestrictionChecker.php`                    | Uses `SegmentMetadataMarshaller::SIZE_RESTRICTION->value` (constant removed) |
| `lib/Utils/LQA/QA.php`                                           | `SIZE_RESTRICTION` re-export removed                                         |
| `lib/Controller/API/App/SetTranslationController.php`            | Uses marshaller enum value                                                   |
| `lib/Controller/API/App/GetWarningController.php`                | Uses marshaller enum value                                                   |
| `tests/unit/LQA/SizeRestrictionCheckerTest.php`                  | Updated to use enum                                                          |
| `tests/unit/LQA/QATest.php`                                      | Updated to use enum                                                          |
| `tests/unit/Model/ProjectCreation/TestableSegmentExtractor.php`  | Updated types                                                                |
| `tests/unit/Model/ProjectCreation/ExtractSegmentsTest.php`       | Updated expectations                                                         |
| `tests/unit/Model/ProjectCreation/SegmentStorageServiceTest.php` | Updated expectations                                                         |

### 3.2 Backend — Commit 2 (`bd6b45a`)

`♻️ refactor: typed segment metadata pipeline — static unmarshall, DAO returns collection, API serves typed values`

#### Changes

| File                                                          | Change                                                                                       |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `lib/Model/Segments/SegmentMetadataMarshaller.php`            | `unmarshall()` → static, accepts `SegmentMetadataStruct`                                     |
| `lib/Model/Segments/SegmentMetadataCollection.php`            | Added `findTyped()`, implemented `JsonSerializable` (typed values)                           |
| `lib/Model/Segments/SegmentMetadataDao.php`                   | `getAll()` → returns `SegmentMetadataCollection`; `get()` → returns `?SegmentMetadataStruct` |
| `lib/Controller/API/App/SetTranslationController.php`         | Removed `[0] ?? null`                                                                        |
| `lib/Controller/API/App/GetWarningController.php`             | Removed `[0] ?? null`                                                                        |
| `plugins/translated/lib/Features/Translated.php`              | Removed `[0] ?? null`, uses nullsafe `?->`                                                   |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 3 unmarshall tests updated (struct-based)                                                    |
| `tests/unit/Model/Segments/SegmentMetadataCollectionTest.php` | 6 tests added (4 findTyped + 2 jsonSerialize)                                                |

#### Unchanged Files (and why)

| File                                    | Reason                                                                |
| --------------------------------------- | --------------------------------------------------------------------- |
| `SegmentMetadataStruct::meta_value`     | Stays `string` (DB-honest)                                            |
| `SegmentExtractor`                      | Uses `find()` (raw `?string`) — write path, no change                 |
| `SizeRestrictionChecker`                | Receives struct, PHP coercion handles comparison                      |
| `SegmentMetadataDao::getBySegmentIds()` | Multi-segment, returns `SegmentMetadataStruct[]` — different use case |
| `GetSegmentsController:158`             | `JsonSerializable` handles serialization automatically                |
| Frontend JS files                       | All numeric ops use JS coercion — string→int transparent              |

### 3.3 Backend — Commit 3 (pending)

`✨ feat: add resname/restype context mapping to segment metadata pipeline with ContextResType enum`

#### Files Created

| File                                               | Purpose                                                                                    |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `lib/Model/Segments/ContextResType.php`            | Backed string enum (5 cases): validates `restype` values against allowed lookup strategies |
| `tests/unit/Model/Segments/ContextResTypeTest.php` | 15 tests                                                                                   |

#### Files Modified

| File                                                          | Change                                                                                            |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `lib/Model/Segments/SegmentMetadataMarshaller.php`            | Added `RESNAME` + `RESTYPE` cases; `RESTYPE.marshall()` validates via `ContextResType::tryFrom()` |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 14 tests added (2 isAllowed + 1 enum cases + 2 resname + 5 valid restype + 4 invalid restype)     |

### 3.4 Frontend (pre-existing, before our session)

Context review page and supporting infrastructure built in earlier commits (`f4ef2b7`→`2de74713`):

| File                                                              | Purpose                                                  |
| ----------------------------------------------------------------- | -------------------------------------------------------- |
| `public/js/pages/ContextReview.js`                                | Standalone context review page (583 lines)               |
| `lib/Controller/Views/ContextReviewController.php`                | Server-side view controller                              |
| `lib/Routes/view_routes.php`                                      | Route registration                                       |
| `lib/View/templates/_context_review.html`                         | HTML template                                            |
| `public/css/sass/components/pages/ContextReviewPage.scss`         | Page styling (205 lines)                                 |
| `public/js/utils/contextReviewUtils.js`                           | XLIFF parsing, rendering, context extraction (428 lines) |
| `public/js/utils/contextReviewChannel.js`                         | Cross-tab BroadcastChannel communication (97 lines)      |
| `public/js/hooks/useResizable.js`                                 | Resizable panel hook                                     |
| `public/img/icons/EyeIcon.js`                                     | Eye icon component                                       |
| `webpack.config.js`                                               | Build entry point for context review page                |
| `Segment.js`, `SegmentsContainer.js`, `Editarea.js`, `CatTool.js` | CAT tool integration points                              |
| `sample-context-review.html`, `*.xliff`, `equipment.xlf`          | Test/demo files                                          |

---

## 4. API Signatures (current)

### SegmentMetadataMarshaller

```php
enum SegmentMetadataMarshaller: string
{
    case ID_REQUEST     = 'id_request';
    case ID_CONTENT     = 'id_content';
    case ID_ORDER       = 'id_order';
    case ID_ORDER_GROUP = 'id_order_group';
    case SCREENSHOT     = 'screenshot';
    case SIZE_RESTRICTION = 'sizeRestriction';
    case RESNAME        = 'resname';
    case RESTYPE        = 'restype';

    public static function isAllowed(string $key): bool;
    public function marshall(mixed $value): ?string;
    public static function unMarshall(SegmentMetadataStruct $struct): mixed;
}
```

### ContextResType

```php
enum ContextResType: string
{
    case X_PATH                 = 'x-path';
    case X_CLIENT_NODEPATH      = 'x-client_nodepath';
    case X_TAG_ID               = 'x-tag-id';
    case X_CSS_CLASS            = 'x-css_class';
    case X_ATTRIBUTE_NAME_VALUE = 'x-attribute_name_value';
}
```

### SegmentMetadataCollection

```php
class SegmentMetadataCollection implements IteratorAggregate, Countable, JsonSerializable
{
    public function __construct(array $structs = []);
    public function find(SegmentMetadataMarshaller $key): ?string;      // raw value
    public function findTyped(SegmentMetadataMarshaller $key): mixed;   // typed value
    public function getIterator(): ArrayIterator;
    public function count(): int;
    public function isEmpty(): bool;
    public function jsonSerialize(): array;  // typed values for API
}
```

### SegmentMetadataMapper

```php
class SegmentMetadataMapper
{
    public static function fromTransUnitAttributes(array $transUnitAttributes): SegmentMetadataCollection;
}
```

### SegmentMetadataDao

```php
class SegmentMetadataDao extends DataAccess_AbstractDao
{
    public static function getAll(int $id_segment, int $ttl = 604800): SegmentMetadataCollection;
    public static function getBySegmentIds(array $ids, string $key, int $ttl = 604800): array;
    public static function get(int $id_segment, string $key, int $ttl = 604800): ?SegmentMetadataStruct;
    public static function save(SegmentMetadataStruct $metadataStruct): void;
}
```

---

## 5. Test Baseline

```
PHPUnit: 2190 tests, 16838 assertions, 0 failures
Command: vendor/bin/phpunit --exclude-group=ExternalServices --no-coverage
```

---

## 6. Frontend Impact Analysis

Typed API response (`sizeRestriction` as `int` instead of `"string"`) — confirmed **no breaking changes**.

| File:Line                        | Operation                        | String `"42"`     | Int `42`        | Safe? |
| -------------------------------- | -------------------------------- | ----------------- | --------------- | ----- |
| `SegmentTarget.js:325`           | `.meta_value` read               | `"42"`            | `42`            | Yes   |
| `SegmentHeader.js:165`           | `counter > limit`                | JS coerces        | native int      | Yes   |
| `SegmentHeader.js:167`           | `limit - 20`                     | `"42" - 20 = 22`  | `42 - 20 = 22`  | Yes   |
| `SegmentHeader.js:185`           | `limit > 0`                      | `"42" > 0` = true | `42 > 0` = true | Yes   |
| `SegmentHeader.js:189`           | `<span>{limit}</span>`           | renders "42"      | renders "42"    | Yes   |
| `SegmentFooterTabMessages.js:15` | `meta_key !== 'sizeRestriction'` | key check only    | key check only  | Yes   |

---

## 7. Remaining Work (TODO)

### 7.1 Context Mapping — Done

- [x] **Analyze reference spec** —
      `Technical Specification_ Mapping Between Contextual Markup and Translatable Strings.md` reviewed. Both XLIFF 1.2 and
      2.0 normalized to use `resname` and `restype` field names.
- [x] **Design decision: ContextResType enum** — Dedicated backed string enum to validate `restype` values against the 5
      allowed lookup strategies. Chosen over `in_array` for type safety, IDE support, and extensibility.
- [x] **Implement ContextResType enum** (TDD) — `lib/Model/Segments/ContextResType.php` with 5 cases. 15 tests.
- [x] **Add RESNAME + RESTYPE to marshaller** (TDD) — Two new cases in `SegmentMetadataMarshaller`. `RESTYPE.marshall()`
      validates via `ContextResType::tryFrom()`, returns `null` for invalid values. `RESNAME.marshall()` default string
      behavior. 14 tests added.

### 7.2 Context URL Pipeline — Done

- [x] **7.2.1a — PSR-1 rename `unmarshall` → `unMarshall`** in `SegmentMetadataMarshaller`, `SegmentMetadataCollection`,
      and tests (3 files). Uniform with `ProjectsMetadataMarshaller::unMarshall()` / `JobsMetadataMarshaller::unMarshall()`.
- [x] **7.2.1b — Create `FilesMetadataMarshaller`** — Pattern B enum (`isAllowed` + `marshall` + `unMarshall`); cases:
      `INSTRUCTIONS`, `PDF_ANALYSIS`, `CONTEXT_URL`. TDD (16 tests).
- [x] **7.2.1c — Wire `FilesMetadataMarshaller` into `Files\MetadataDao`** — Changed `MetadataStruct::$value` from
      `string` to `mixed`; added `unMarshall()` calls in `get()`, `getByJobIdProjectAndIdFile()`, and `insert()`/`update()`
      return paths.
- [x] **7.2.2 — Add `CONTEXT_URL = 'context-url'`** to `SegmentMetadataMarshaller` (9th case, Pattern B — falls through
      to default string handling) and `ProjectsMetadataMarshaller` (31st case, wired into string-cast branch of
      `unMarshall()`). TDD: +8 tests.
- [x] **7.2.3a — ContextUrlResolver** — Stateless static resolver with three-level fallback (segment → file → project).
      TDD: 7 tests.
- [x] **7.2.3b — GetSegmentsController integration** — Pre-fetches project/file context-urls before segment loop;
      resolves per-segment via `ContextUrlResolver::resolve()`. Returns `context_url` as top-level field on each segment.
- [x] **7.2.3c — Schema migration + `SegmentMetadataDao::upsert()`** — Added `UNIQUE KEY` constraint to
      `segment_metadata(id_segment, meta_key)` in 3 SQL files. Added `upsert()` (INSERT ... ON DUPLICATE KEY UPDATE) and
      `destroyGetAllCache()` methods. Runtime migration:
      `migrations/20260326190000_alter_table_segment_metadata_add_unique_index.php`.
- [x] **7.2.3d — ContextUrlController (3 APIs)** — Single controller with `setForProject()`, `setForFile()`,
      `setForSegment()` actions. Routes: `POST /api/app/context-url/{project,file,segment}`. Cache invalidation at all three
      levels.

### 7.3 Implementation Status (updated 2026-04-03)

> Cross-referenced against actual codebase. Items marked ✅ were implemented by Federico (riccio82) on the frontend.

#### Completed

- [x] **Integration: Connect metadata to context review page** — `extractSegmentContextFields()` (
      `contextReviewUtils.js:596`) extracts `context_url`, `resname`, `restype` from segment data. `ContextReview.js:87-98`
      builds a `metadataMap` filtering segments with `resname && restype`.
- [x] **Frontend: Extend segment message protocol** — `contextReviewChannel.js:11-30` defines the full protocol.
      CatTool→ContextReview messages include `{sid, source, target, context_url, resname, restype}`. Backend (
      `GetSegmentsController`) populates all three fields.
- [x] **Frontend: Implement 5 lookup strategies** — `contextReviewLookup.js:22-71` dispatches via
      `findElementByMetadata()`:
  - `x-tag-id` → `querySelector('#' + CSS.escape(resname))`
  - `x-css_class` → `querySelector('.' + CSS.escape(resname))`
  - `x-path` → `document.evaluate()` with `XPathResult`
  - `x-attribute_name_value` → parses `attr=value`, queries by attribute selector
  - `x-client_nodepath` → stub returning `null` (falls through to text matching)
- [x] **Frontend: Fallback chain** — `contextReviewUtils.js:495-572` implements a 3-pass strategy: (1) metadata lookup
      via `findElementByMetadata()`, (2) position-based text matching for untagged segments, (3) N:N broadcast for remaining
      unmatched.
- [x] **Cross-tab sync** — `contextReviewChannel.js:1-105` implements a singleton `BroadcastChannel` scoped per
      project (`matecat-context-review-${config.password}`). Two-way: `segments`/`highlight`/`updateTranslation` from
      CatTool; `segmentClicked`/`requestSegments`/`loadMoreSegments` from ContextReview. Hooks (
      `useContextReviewMessages.js`) subscribe and dispatch.

#### Not Started

- [ ] **Screenshot handling** — The `screenshot` metadata key is stored by the backend (
      `SegmentMetadataMarshaller::SCREENSHOT`), but no upload, retrieval, or display flow exists on the frontend.
- [ ] **Context rendering with `id_content` / `id_order`** — Backend stores and serves both fields, but the frontend
      does not consume them for document-layout rendering.
- [ ] **Review workflow states** — No state machine, workflow enum, or review-status message types exist. May eventually
      need review states (pending, in-progress, approved, rejected).

#### Deferred

- [ ] **`getBySegmentIds()` collection support** — Backend method exists (`SegmentMetadataDao::getBySegmentIds()`)
      returning `SegmentMetadataStruct[]`. The frontend uses incremental `getSegments()` instead of batch collection.
      Converting to `SegmentMetadataCollection` return type deferred until a concrete consumer needs it.

---

## 8. Constraints (active)

### Architecture & Design

1. "a project is created without a flag declaring that an in-context review type is enabled like any other normal
   project"
2. "I DON'T WANT segmentextractor call any method to unmarshall in SegmentExtractor"
3. "SegmentMetadataMapper::fromTransUnitAttributes is the one who creates the SegmentMetadataStruct, it must create the
   definitive segmentstruct"
4. "serve typed" — API returns typed values, not all strings
5. "SegmentMetadataStruct::meta_value MUST stay `string` — DB-honest, typed access only through
   `Collection::findTyped()`"
6. "Do NOT perform cross-validation between `resname` and `restype` at storage time" — stored independently, frontend
   responsible
7. `context-url` is the field name at all three levels — hyphenated to align with XLIFF attribute conventions
8. `context-url` has dual ingestion: XLIFF trans-unit attributes AND API. `FilesMetadataMarshaller` uses Pattern B (full
   pipeline) — ready for future XLIFF `<file>` attribute extraction
9. Fallback resolution order: segment → file → project (read-time, no data duplication)

### Implementation Guardrails

7. "we will ignore Equipment.html.xlf Equipment.html files" (not lookup strategies)
8. Do NOT modify files in `mm_bkp/` directory

### Workflow

9. "exclude group ExternalServices, you do not have the needed AWS and MyMemory keys"
10. "Before closing a task and decide to commit, YOU MUST RUN ALL the tests"
11. "MANDATORY: ALWAYS use conventional-commit"
12. "Wait my command before start implementing."
13. ALWAYS use `english-checker` skill
14. "use unMarshall, uniform SegmentMetadataMarshaller, stay compliant with PHP PSR-1 Basic Coding Standard" — all
    marshallers use `unMarshall()` (camelCase)
15. "ALWAYS follow this pattern: update the document, show me the commit message, WAIT for my authorization, commit"

---

## 9. Related Implementation Plans

| Plan                          | Status   | Location                                                                  |
| ----------------------------- | -------- | ------------------------------------------------------------------------- |
| Segment metadata filter       | Executed | `docs/superpowers/plans/2026-03-25-segment-metadata-filter.md`            |
| Metadata validation hardening | Executed | `docs/superpowers/plans/2026-03-24-metadata-validation-hardening.md`      |
| DAO unmarshalling             | Executed | `docs/superpowers/plans/2026-03-25-segment-metadata-dao-unmarshalling.md` |

---

## 10. Context Mapping — Technical Analysis

> Based on analysis of `Technical Specification_ Mapping Between Contextual Markup and Translatable Strings.md`

### 10.1 Reference Spec Summary

The specification defines how XLIFF `<trans-unit>` attributes map translated segments to their visual positions in the
original document (HTML context). Two attributes are key:

| Attribute | Purpose                                                                               | Example                                                        |
| --------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------- |
| `resname` | **Target value** — the identifier/selector/path to locate the element in context HTML | `//html/body/div[2]/p[1]`, `content-block-42`, `product-title` |
| `restype` | **Strategy type** — declares HOW `resname` should be interpreted                      | `x-path`, `x-tag-id`, `x-css_class`                            |

### 10.2 The 5 Lookup Strategies (from spec)

| `restype` value          | Strategy         | `resname` contains        | Example                        |
| ------------------------ | ---------------- | ------------------------- | ------------------------------ |
| `x-path`                 | XPath            | Full XPath expression     | `//html/body/div[2]/p[1]`      |
| `x-client_nodepath`      | Client node path | Proprietary CMS path      | `root.section[2].paragraph[1]` |
| `x-tag-id`               | Element ID       | HTML `id` attribute value | `product-title`                |
| `x-css_class`            | CSS class        | CSS class name            | `translatable-block`           |
| `x-attribute_name_value` | Attribute pair   | `name=value` format       | `data-trans-id=seg-42`         |

### 10.3 Gap Analysis (updated 2026-04-03)

| Layer                              | Status  | Detail                                                                                         |
| ---------------------------------- | ------- | ---------------------------------------------------------------------------------------------- |
| **XLIFF Parsing**                  | ✅ Done | `XliffParserV1/V2` already extract ALL trans-unit attributes including `resname` and `restype` |
| **Storage (marshaller whitelist)** | ✅ Done | `RESNAME` + `RESTYPE` cases added to `SegmentMetadataMarshaller` (Commit 3)                    |
| **Storage (validation)**           | ✅ Done | `ContextResType` enum validates `restype` against 5 allowed strategies (Commit 3)              |
| **Frontend message protocol**      | ✅ Done | Protocol extended with `context_url`, `resname`, `restype` (`contextReviewChannel.js:11-30`)   |
| **Frontend lookup strategies**     | ✅ Done | 5-strategy dispatcher in `contextReviewLookup.js:22-71` with 3-pass fallback chain             |

**Full pipeline complete.** XLIFF attributes are extracted → stored → served via API → consumed by frontend lookup
strategies with graceful fallback to text matching.

### 10.4 Design Decision: `ContextResType` Enum

**Choice**: Dedicated `ContextResType` backed string enum (over `in_array` validation).

**Rationale**:

- Type safety — IDE catches invalid values at write time
- Extensible — new strategies = new enum case (no hunting for array constants)
- Self-documenting — `ContextResType::X_PATH` is clearer than `'x-path'`
- Testable — enum cases are exhaustively testable
- Reusable — frontend lookup strategy dispatch can switch on enum values

**Implementation**:

```php
// lib/Model/Segments/ContextResType.php
enum ContextResType: string
{
    case X_PATH                 = 'x-path';
    case X_CLIENT_NODEPATH      = 'x-client_nodepath';
    case X_TAG_ID               = 'x-tag-id';
    case X_CSS_CLASS            = 'x-css_class';
    case X_ATTRIBUTE_NAME_VALUE = 'x-attribute_name_value';
}
```

**Integration with marshaller**:

```php
// In SegmentMetadataMarshaller:
case RESNAME = 'resname';   // default string → string
case RESTYPE = 'restype';   // validates via ContextResType::tryFrom()

public function marshall(mixed $value): ?string
{
    return match ($this) {
        self::SIZE_RESTRICTION => ((int)$value > 0) ? (string)(int)$value : null,
        self::RESTYPE          => ContextResType::tryFrom($value) !== null ? (string)$value : null,
        default                => (string)$value,
    };
}
```

**Behavior**: Invalid `restype` values (e.g. `x-title`, `x-li`) are silently rejected by `marshall()` returning `null` →
mapper skips them. No error thrown, no invalid data stored.

### 10.5 Cross-Validation Note

No cross-validation between `resname` and `restype` at storage time. If `restype` is present but `resname` is missing (
or vice versa), both are stored independently. The consumer (frontend) is responsible for checking that both are present
before attempting a lookup strategy — missing either = graceful fallback to text matching.

### 10.6 XLIFF Parser Evidence

```
// XliffParserV1::extractTransUnitMetadata()
// Already extracts: $xliff['files'][$i]['trans-units'][$j]['attr']
// Includes ALL attributes: id, resname, restype, maxwidth, size-unit, etc.
// No parser changes needed.
```

### 10.7 Updated Allowed Metadata Keys (after implementation)

| Enum Case          | DB Value (`meta_key`) | Marshall                      | Unmarshall            |
| ------------------ | --------------------- | ----------------------------- | --------------------- |
| `ID_REQUEST`       | `id_request`          | `string → string`             | `string → string`     |
| `ID_CONTENT`       | `id_content`          | `string → string`             | `string → string`     |
| `ID_ORDER`         | `id_order`            | `string → string`             | `string → string`     |
| `ID_ORDER_GROUP`   | `id_order_group`      | `string → string`             | `string → string`     |
| `SCREENSHOT`       | `screenshot`          | `string → string`             | `string → string`     |
| `SIZE_RESTRICTION` | `sizeRestriction`     | `mixed → string` (cast)       | `string → int` (cast) |
| `RESNAME`          | `resname`             | `string → string`             | `string → string`     |
| `RESTYPE`          | `restype`             | `string → string` (validated) | `string → string`     |
| `X_CLIENT_NAME`    | `x-client-name`       | `string → string`             | `string → string`     |

> `RESTYPE.marshall()` returns `null` for values not in `ContextResType` — mapper skips invalid entries.

### 10.8 Frontend Status (updated 2026-04-03)

> Reflects work done by Federico (riccio82) during March–April 2026.

| Component                             | Status         | Detail                                                                                                            |
| ------------------------------------- | -------------- | ----------------------------------------------------------------------------------------------------------------- |
| `contextReviewUtils.js:tagSegments()` | ✅ 3-pass      | (1) Metadata lookup via `findElementByMetadata()`, (2) position-based text match, (3) N:N broadcast for remaining |
| Segment message protocol              | ✅ Extended    | `{sid, source, target, context_url, resname, restype}` — all metadata fields included                             |
| `contextReviewLookup.js`              | ✅ Implemented | 5-strategy dispatcher: `x-tag-id`, `x-css_class`, `x-path`, `x-attribute_name_value`, `x-client_nodepath` (stub)  |
| XPath lookup                          | ✅ Done        | `document.evaluate()` with `XPathResult.FIRST_ORDERED_NODE_TYPE`                                                  |
| ID lookup                             | ✅ Done        | `querySelector('#' + CSS.escape(resname))`                                                                        |
| CSS class lookup                      | ✅ Done        | `querySelector('.' + CSS.escape(resname))`                                                                        |
| Custom path (`x-client_nodepath`)     | ⚠️ Stub        | Returns `null` — falls through to text matching                                                                   |
| Attribute pair lookup                 | ✅ Done        | Parses `attr=value` format, queries `[attr="value"]`                                                              |
| Fallback chain                        | ✅ Done        | Strategy-first → text match → broadcast (graceful degradation)                                                    |
| Cross-tab BroadcastChannel            | ✅ Done        | Singleton per project, 5 message types, bidirectional                                                             |
| `extractSegmentContextFields()`       | ✅ Done        | Extracts `context_url`, `resname`, `restype` from segment data                                                    |
| Screenshot display                    | ❌ Not started | Backend stores field, no frontend consumer                                                                        |
| `id_content` / `id_order` rendering   | ❌ Not started | Backend serves fields, no frontend consumer                                                                       |
| Review workflow states                | ❌ Not started | No state machine or status transitions                                                                            |

---

## 11. Context URL — Architecture & Design

### 11.1 Problem Statement

When a user wants in-context review, the system needs a URL to fetch the context HTML file. Three real-world scenarios
exist:

| Scenario                                         | Granularity | Example                                                            |
| ------------------------------------------------ | ----------- | ------------------------------------------------------------------ |
| Different segments refer to different HTML pages | Per-segment | An XLIFF where each trans-unit has its own `context-url` attribute |
| Each XLIFF file refers to a different HTML page  | Per-file    | Two XLIFF files, each translating a different web page             |
| All segments in a project share the same context | Per-project | One XLIFF, one HTML context page                                   |

### 11.2 Storage: Three-Level Metadata

The `context-url` key is stored at all three metadata levels:

| Level       | Table              | DAO                    | Key           |
| ----------- | ------------------ | ---------------------- | ------------- |
| **Segment** | `segment_metadata` | `SegmentMetadataDao`   | `context-url` |
| **File**    | `file_metadata`    | `Files\MetadataDao`    | `context-url` |
| **Project** | `project_metadata` | `Projects\MetadataDao` | `context-url` |

> **Naming convention**: hyphenated (`context-url`) to align with XLIFF attribute conventions (`x-path`, `x-tag-id`,
> `x-css_class`).

### 11.3 Dual Ingestion Paths

`context-url` can enter the system from two directions:

| Source                         | Segment level                                         | File level                                                                                                                                                                                                                        | Project level      |
| ------------------------------ | ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| **XLIFF trans-unit attribute** | ✅ Extracted via `SegmentMetadataMarshaller` pipeline | ⚠️ Future — XLIFF parser does not yet extract `<file>` attributes, but `FilesMetadataMarshaller` is designed ready for it. Test XLIFF files include a second `<file>` with `context-url` attribute for when parser support lands. | ❌ No XLIFF source |
| **API (user-submitted)**       | ✅ API 1                                              | ✅ API 2                                                                                                                                                                                                                          | ✅ API 3           |

### 11.4 Fallback Resolution (read-time)

When the GetSegments API returns segment data, `context-url` is resolved with this priority:

```
1. segment_metadata  (most specific — per-segment context)
2. file_metadata     (mid-level — per-file context)
3. project_metadata  (broadest — project-wide context)
```

Resolution happens at **read time**, not write time. No data duplication. If the project-level URL changes via API, all
segments without their own context-url immediately inherit the new value.

**Performance**: project-level and file-level context-urls are fetched **once** before the segment loop. Per-segment
check is a `Collection::find()` on the already-loaded collection — no extra query.

### 11.5 FilesMetadataMarshaller — Design Decision

**Current state**: `Files\MetadataDao` has NO marshaller. Values stored/retrieved as raw strings. Existing keys:
`instructions`, `mtc:instructions` (legacy), `pdfAnalysis` (JSON).

**Decision**: Create `FilesMetadataMarshaller` using **Pattern B** (full pipeline, same as `SegmentMetadataMarshaller`):

| Method                                      | Purpose                                                               |
| ------------------------------------------- | --------------------------------------------------------------------- |
| `isAllowed(string $key): bool`              | Whitelist gate — ready for future XLIFF `<file>` attribute extraction |
| `marshall(mixed $value): ?string`           | Write validation — returns `null` to reject invalid values            |
| `unmarshall(MetadataStruct $struct): mixed` | Read-time type restoration                                            |

**Rationale**: Although the XLIFF parser does not yet extract `<file>`-element attributes, future versions will.
Designing with full pipeline now avoids retrofitting later. Consistent interface across all three marshallers.

**Initial cases**:

| Enum Case      | DB Value (`key`) | Marshall                 | Unmarshall                     |
| -------------- | ---------------- | ------------------------ | ------------------------------ |
| `INSTRUCTIONS` | `instructions`   | `string → string`        | `string → string`              |
| `PDF_ANALYSIS` | `pdfAnalysis`    | `string → string` (JSON) | `string → array` (json_decode) |
| `CONTEXT_URL`  | `context-url`    | `string → string` (URL)  | `string → string`              |

### 11.6 Three APIs

| API                 | Input                                                                      | Stores in          | Notes                              |
| ------------------- | -------------------------------------------------------------------------- | ------------------ | ---------------------------------- |
| **API 1** (segment) | `context_url`, `segment_id`, `file_id` (optional), `project_id` (optional) | `segment_metadata` | Most specific override             |
| **API 2** (file)    | `context_url`, `file_id`, `project_id` (optional)                          | `file_metadata`    | Applies to all segments in file    |
| **API 3** (project) | `context_url`, `project_id`                                                | `project_metadata` | Broadest — all segments in project |

### 11.7 Deep Analysis — Files\MetadataDao Consumer Impact

> Based on deep analysis of all `Files\MetadataDao` consumers and `pdfAnalysis` data flow (2 parallel explore agents).

#### Read Path Consumers

| Consumer                   | Method                       | Usage                                | Impact                                                       |
| -------------------------- | ---------------------------- | ------------------------------------ | ------------------------------------------------------------ |
| `FilesInfoUtility:56-64`   | `getByJobIdProjectAndIdFile` | `$metadata[$key] = $value`           | ✅ Safe — direct assignment works with both string and array |
| `AbstractStatus:223`       | `getByJobIdProjectAndIdFile` | Passes to `AnalysisFile` constructor | ✅ Safe — wraps key/value pairs                              |
| `MetaDataController:122`   | `getByJobIdProjectAndIdFile` | `$stdClass->$key = $value`           | ✅ Safe — direct property assignment                         |
| `FilesInfoUtility:114-124` | `get`                        | Returns instructions `.value`        | ✅ Safe — instructions stay string                           |
| `FilesInfoUtility:141-144` | `get` + `insert`/`update`    | Instructions CRUD                    | ✅ Safe — instructions stay string                           |

#### Write Path (not affected by unmarshalling)

| Consumer                   | Method       | Key                     | Value Format                        |
| -------------------------- | ------------ | ----------------------- | ----------------------------------- |
| `SegmentExtractor:257`     | `insert`     | `mtc:references`        | Raw string                          |
| `SegmentExtractor:265`     | `insert`     | `data-type`             | Raw string                          |
| `FileInsertionService:327` | `insert`     | `pdfAnalysis`           | `json_encode($array)` → JSON string |
| `ProjectManager:1007`      | `insert`     | `instructions`          | Raw string                          |
| `FilesInfoUtility:144`     | `insert`     | `instructions`          | Raw string                          |
| `SegmentExtractor:282`     | `bulkInsert` | XLIFF custom attributes | Raw strings                         |

#### pdfAnalysis Data Flow

```
ConversionHandler → array → ConvertedFileModel → Redis (serialized)
    → FileInsertionService → json_encode($array) → DB (JSON string)
    → MetadataDao::get() → MetadataStruct (raw JSON string)
    → FilesInfoUtility / MetaDataController → API response (string in JSON body)
```

**After unmarshalling**: `MetadataDao::get()` returns decoded array. All consumers do direct assignment (
`$metadata[$key] = $value`), so `response->json()` handles arrays correctly. **Zero frontend JS references
to `pdfAnalysis`** — safe to change.

#### Conclusion

**No breaking changes.** All read-path consumers use direct assignment, which works with both string and mixed types. No
PHP code calls `json_decode()` on Files metadata values (unlike Projects `MMT.php:129` which is a different DAO). The
change is fully backward-compatible.

### 11.8 PSR-1 Method Naming Normalization

`SegmentMetadataMarshaller::unmarshall()` violates PSR-1 §4.3 ("Method names MUST be declared in camelCase"). Must
rename to `unMarshall()` to match `ProjectsMetadataMarshaller::unMarshall()` and `JobsMetadataMarshaller::unMarshall()`.

**Files affected:**

| File                                                          | Change                                          |
| ------------------------------------------------------------- | ----------------------------------------------- |
| `lib/Model/Segments/SegmentMetadataMarshaller.php`            | Method definition: `unmarshall` → `unMarshall`  |
| `lib/Model/Segments/SegmentMetadataCollection.php`            | 2 call sites: `findTyped()` + `jsonSerialize()` |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 5 test calls + 1 comment                        |

### 11.9 GetSegmentsController Change (implemented)

**Pre-fetch (before segment loop):**

```php
$projectContextUrl = $projectMetadata->setCacheTTL(60 * 60 * 24)->get(
    $project->id, ProjectsMetadataMarshaller::CONTEXT_URL->value
)?->value;
$filesMetadataDao = new FilesMetadataDao();
$fileContextUrls = [];
```

**Per-file lookup (inside `!isset($res[$id_file])` block):**

```php
$fileContextUrls[$id_file] = $filesMetadataDao->setCacheTTL(60 * 60 * 24)->get(
    $project->id, $id_file, FilesMetadataMarshaller::CONTEXT_URL->value
)?->value;
```

**Per-segment resolution (replaced old line 158):**

```php
$segmentMetadata = SegmentMetadataDao::getAll($seg['sid']);
$seg['metadata'] = $segmentMetadata->jsonSerialize();
$seg['context_url'] = ContextUrlResolver::resolve(
    $segmentMetadata, $fileContextUrls[$id_file] ?? null, $projectContextUrl
);
```

The resolved `context-url` is returned as a **top-level field** per segment (not nested in metadata), since it may come
from file or project level rather than segment metadata.

### 11.10 ContextUrlController (implemented)

Three POST endpoints under `/api/v3/context-url/`:

| Route           | Action            | DAO Method                            | Cache Invalidation                      |
| --------------- | ----------------- | ------------------------------------- | --------------------------------------- |
| `POST /project` | `setForProject()` | `ProjectsMetadataDao::set()`          | Internal (DAO handles it)               |
| `POST /file`    | `setForFile()`    | `FilesMetadataDao::get/insert/update` | `destroyCacheByJobIdProjectAndIdFile()` |
| `POST /segment` | `setForSegment()` | `SegmentMetadataDao::upsert()`        | `destroyGetAllCache()`                  |

All endpoints require `LoginValidator` authentication. Segment-level validates through
`SegmentMetadataMarshaller::CONTEXT_URL->marshall()`. Project/file levels store raw strings (Pattern A marshallers — no
`marshall()` method).

### 11.11 Schema Migration (implemented)

`segment_metadata` table index `idx_id_segment_meta_key(id_segment, meta_key)` upgraded from `KEY` to `UNIQUE KEY` in:

- `lib/Model/matecat.sql`
- `INSTALL/matecat.sql`
- `tests/inc/unittest_matecat_local.sql`

Runtime migration: `migrations/20260326190000_alter_table_segment_metadata_add_unique_index.php` (user-created).

### 11.12 Cache Management Architecture Document (added 2026-03-27)

`docs/cache-management-architecture.md` — standalone technical reference covering `DaoCacheTrait` (all 8 methods),
`AbstractDao` cache integration (`_fetchObjectMap`, `_destroyObjectCache`), the three invalidation strategies (surgical
reverse-lookup, nuclear direct delete, surgical field removal), the `Pager`/`getAllPaginated` pagination pattern with
`PaginationParameters`, and `SessionTokenStoreHandler` as the sole non-DAO consumer of the trait.

### 11.13 Test Resources — In-Context Review (added 2026-04-03)

All in-context review test files are organized under `tests/resources/files/in-context-review/`:

| File                                     | Purpose                                                          |
| ---------------------------------------- | ---------------------------------------------------------------- |
| `test-context-mapping.xlf`               | XLIFF 1.2 — all 5 restype strategies + `x-client-name` (2 files) |
| `test-context-mapping-2.0.xlf`           | XLIFF 2.0 — same content with `matecat:` namespace (2 files)     |
| `test-context-mapping.html`              | Context HTML for file 1 (`sample-document.html`)                 |
| `test-context-mapping-product-page.html` | Context HTML for file 2 (`product-page.html`)                    |

**Second `<file>` section** (t13–t18) exercises:

- **File-level `context-url`** — prepares for future `<file>` attribute extraction (§11.3)
- **`screenshot` attribute** on t18 — segment-level metadata the backend stores but frontend does not consume yet
- **`x-path`**, **`x-tag-id`**, **`x-css_class`** strategies against a product page DOM

### 11.14 Integration Tests — In-Context Review (added 2026-04-03)

Two test classes in `tests/unit/Model/Segments/InContextReview/`:

| Class                            | What it verifies                                                                                   |
| -------------------------------- | -------------------------------------------------------------------------------------------------- |
| `XliffToMetadataIntegrationTest` | Full pipeline: XLIFF bytes → `XliffParser` → `SegmentMetadataMapper` → `SegmentMetadataCollection` |
| `XPathResolutionContractTest`    | Every `restype="x-path"` trans-unit resolves against its context HTML and matches the XLIFF source |

**XliffToMetadataIntegrationTest** (14 tests):

- XLIFF 1.2 and 2.0 produce identical metadata after namespace stripping
- Multi-file XLIFF parsed correctly (12 + 6 trans-units)
- Every trans-unit has `resname` and `restype`
- All 5 restype strategies pass through the marshaller
- `x-client-name` extracted alongside `x-client_nodepath`
- `screenshot` attribute extracted on t18
- Non-metadata attributes (`id`, `xml:lang`, `approved`) filtered out

**XPathResolutionContractTest** (11 tests):

- Data provider dynamically extracts all `x-path` trans-units from the XLIFF 1.2 fixture
- Each XPath is evaluated via `DOMXPath` against the corresponding context HTML file
- Attribute-targeting XPaths (`/@alt`, `/@title`, `/@placeholder`) resolve `DOMAttr::nodeValue`
- Element-targeting XPaths resolve `DOMElement::textContent`
- XML entity normalization (`&#x00A9;` → `©`) via `html_entity_decode` for semantic comparison
- Coverage guard: asserts the provider covers every `x-path` trans-unit in the fixture
