# In-Context Review — Feature Development Spec

> **Branch:** `context-review`
> **Started:** March 2026
> **Last updated:** 2026-03-26
> **Reference spec:** `Technical Specification_ Mapping Between Contextual Markup and Translatable Strings.md`

---

## 1. Feature Overview

In-context review allows reviewers to see translated segments within their original document layout, providing visual context for translation quality assessment. A project is created **without a flag** declaring that an in-context review type is enabled — it works like any other normal project. The backend extracts defined attributes from the XLIFF `<trans-unit>` and stores them in `segments_metadata`.

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

| Enum Case | DB Value (`meta_key`) | Marshall | Unmarshall |
|---|---|---|---|
| `ID_REQUEST` | `id_request` | `string → string` | `string → string` |
| `ID_CONTENT` | `id_content` | `string → string` | `string → string` |
| `ID_ORDER` | `id_order` | `string → string` | `string → string` |
| `ID_ORDER_GROUP` | `id_order_group` | `string → string` | `string → string` |
| `SCREENSHOT` | `screenshot` | `string → string` | `string → string` |
| `SIZE_RESTRICTION` | `sizeRestriction` | `mixed → string` (cast) | `string → int` (cast) |
| `RESNAME` | `resname` | `string → string` | `string → string` |
| `RESTYPE` | `restype` | `string → string` (validated) | `string → string` |

> `RESTYPE.marshall()` validates via `ContextResType::tryFrom()` — returns `null` for invalid values, mapper skips them.

### 2.3 Key Design Decisions

| Decision | Rationale |
|---|---|
| `SegmentMetadataStruct::meta_value` stays `string` | DB-honest — the database column is VARCHAR. No lying about what the DB returns. |
| `Collection::find()` returns `?string` (raw) | Write path — `SegmentExtractor::createSegmentHash(?string $sizeRestriction)` needs raw string. |
| `Collection::findTyped()` returns `mixed` (typed) | Read path — calls `unmarshall()` internally for typed access. |
| `SegmentMetadataMarshaller::unmarshall()` is static | Follows `ProjectsMetadataMarshaller::unMarshall(MetadataStruct)` pattern. Accepts struct, not string. |
| `jsonSerialize()` serves typed values | API sends `sizeRestriction` as `int 42`, not `"42"`. Frontend confirmed safe (JS coercion). |
| No project-level flag | Per requirement: "a project is created without a flag declaring that an in-context review type is enabled." |
| `ProjectManagerModel::isAMetadata()` delegates to marshaller | Single source of truth for allowed keys. |
| `SizeRestrictionChecker::SIZE_RESTRICTION` constant removed | Replaced by `SegmentMetadataMarshaller::SIZE_RESTRICTION->value` across entire codebase. |

---

## 3. Completed Work

### 3.1 Backend — Commit 1 (`7860617`)

`✨ feat: centralize segment metadata pipeline with marshaller, mapper, and collection`

#### Files Created

| File | Purpose |
|---|---|
| `lib/Model/Segments/SegmentMetadataMarshaller.php` | Backed enum (8 cases): `isAllowed()`, `marshall()`, static `unmarshall()` |
| `lib/Model/Segments/SegmentMetadataMapper.php` | `fromTransUnitAttributes(array): SegmentMetadataCollection` |
| `lib/Model/Segments/SegmentMetadataCollection.php` | Read-only collection: `find()`, `findTyped()`, `IteratorAggregate`, `Countable`, `JsonSerializable` |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 22 tests |
| `tests/unit/Model/Segments/SegmentMetadataMapperTest.php` | 10 tests |
| `tests/unit/Model/Segments/SegmentMetadataCollectionTest.php` | 15 tests (9 base + 4 findTyped + 2 jsonSerialize) |

#### Files Modified

| File | Change |
|---|---|
| `lib/Model/ProjectCreation/ProjectManagerModel.php` | `isAMetadata()` delegates to `SegmentMetadataMarshaller::isAllowed()` |
| `lib/Model/ProjectCreation/SegmentExtractor.php` | Uses mapper + collection, calls `$collection->find()` |
| `lib/Model/ProjectCreation/ProjectManager.php` | Added `use SegmentMetadataMapper` |
| `lib/Model/ProjectCreation/SegmentStorageService.php` | Iterates `SegmentMetadataCollection` via `foreach` |
| `lib/Utils/LQA/QA/SizeRestrictionChecker.php` | Uses `SegmentMetadataMarshaller::SIZE_RESTRICTION->value` (constant removed) |
| `lib/Utils/LQA/QA.php` | `SIZE_RESTRICTION` re-export removed |
| `lib/Controller/API/App/SetTranslationController.php` | Uses marshaller enum value |
| `lib/Controller/API/App/GetWarningController.php` | Uses marshaller enum value |
| `tests/unit/LQA/SizeRestrictionCheckerTest.php` | Updated to use enum |
| `tests/unit/LQA/QATest.php` | Updated to use enum |
| `tests/unit/Model/ProjectCreation/TestableSegmentExtractor.php` | Updated types |
| `tests/unit/Model/ProjectCreation/ExtractSegmentsTest.php` | Updated expectations |
| `tests/unit/Model/ProjectCreation/SegmentStorageServiceTest.php` | Updated expectations |

### 3.2 Backend — Commit 2 (`bd6b45a`)

`♻️ refactor: typed segment metadata pipeline — static unmarshall, DAO returns collection, API serves typed values`

#### Changes

| File | Change |
|---|---|
| `lib/Model/Segments/SegmentMetadataMarshaller.php` | `unmarshall()` → static, accepts `SegmentMetadataStruct` |
| `lib/Model/Segments/SegmentMetadataCollection.php` | Added `findTyped()`, implemented `JsonSerializable` (typed values) |
| `lib/Model/Segments/SegmentMetadataDao.php` | `getAll()` → returns `SegmentMetadataCollection`; `get()` → returns `?SegmentMetadataStruct` |
| `lib/Controller/API/App/SetTranslationController.php` | Removed `[0] ?? null` |
| `lib/Controller/API/App/GetWarningController.php` | Removed `[0] ?? null` |
| `plugins/translated/lib/Features/Translated.php` | Removed `[0] ?? null`, uses nullsafe `?->` |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 3 unmarshall tests updated (struct-based) |
| `tests/unit/Model/Segments/SegmentMetadataCollectionTest.php` | 6 tests added (4 findTyped + 2 jsonSerialize) |

#### Unchanged Files (and why)

| File | Reason |
|---|---|
| `SegmentMetadataStruct::meta_value` | Stays `string` (DB-honest) |
| `SegmentExtractor` | Uses `find()` (raw `?string`) — write path, no change |
| `SizeRestrictionChecker` | Receives struct, PHP coercion handles comparison |
| `SegmentMetadataDao::getBySegmentIds()` | Multi-segment, returns `SegmentMetadataStruct[]` — different use case |
| `GetSegmentsController:158` | `JsonSerializable` handles serialization automatically |
| Frontend JS files | All numeric ops use JS coercion — string→int transparent |

### 3.3 Backend — Commit 3 (pending)

`✨ feat: add resname/restype context mapping to segment metadata pipeline with ContextResType enum`

#### Files Created

| File | Purpose |
|---|---|
| `lib/Model/Segments/ContextResType.php` | Backed string enum (5 cases): validates `restype` values against allowed lookup strategies |
| `tests/unit/Model/Segments/ContextResTypeTest.php` | 15 tests |

#### Files Modified

| File | Change |
|---|---|
| `lib/Model/Segments/SegmentMetadataMarshaller.php` | Added `RESNAME` + `RESTYPE` cases; `RESTYPE.marshall()` validates via `ContextResType::tryFrom()` |
| `tests/unit/Model/Segments/SegmentMetadataMarshallerTest.php` | 14 tests added (2 isAllowed + 1 enum cases + 2 resname + 5 valid restype + 4 invalid restype) |

### 3.4 Frontend (pre-existing, before our session)

Context review page and supporting infrastructure built in earlier commits (`f4ef2b7`→`2de74713`):

| File | Purpose |
|---|---|
| `public/js/pages/ContextReview.js` | Standalone context review page (583 lines) |
| `lib/Controller/Views/ContextReviewController.php` | Server-side view controller |
| `lib/Routes/view_routes.php` | Route registration |
| `lib/View/templates/_context_review.html` | HTML template |
| `public/css/sass/components/pages/ContextReviewPage.scss` | Page styling (205 lines) |
| `public/js/utils/contextReviewUtils.js` | XLIFF parsing, rendering, context extraction (428 lines) |
| `public/js/utils/contextReviewChannel.js` | Cross-tab BroadcastChannel communication (97 lines) |
| `public/js/hooks/useResizable.js` | Resizable panel hook |
| `public/img/icons/EyeIcon.js` | Eye icon component |
| `webpack.config.js` | Build entry point for context review page |
| `Segment.js`, `SegmentsContainer.js`, `Editarea.js`, `CatTool.js` | CAT tool integration points |
| `sample-context-review.html`, `*.xliff`, `equipment.xlf` | Test/demo files |

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
    public static function unmarshall(SegmentMetadataStruct $struct): mixed;
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

| File:Line | Operation | String `"42"` | Int `42` | Safe? |
|---|---|---|---|---|
| `SegmentTarget.js:325` | `.meta_value` read | `"42"` | `42` | Yes |
| `SegmentHeader.js:165` | `counter > limit` | JS coerces | native int | Yes |
| `SegmentHeader.js:167` | `limit - 20` | `"42" - 20 = 22` | `42 - 20 = 22` | Yes |
| `SegmentHeader.js:185` | `limit > 0` | `"42" > 0` = true | `42 > 0` = true | Yes |
| `SegmentHeader.js:189` | `<span>{limit}</span>` | renders "42" | renders "42" | Yes |
| `SegmentFooterTabMessages.js:15` | `meta_key !== 'sizeRestriction'` | key check only | key check only | Yes |

---

## 7. Remaining Work (TODO)

### 7.1 Context Mapping — Done

- [x] **Analyze reference spec** — `Technical Specification_ Mapping Between Contextual Markup and Translatable Strings.md` reviewed. Both XLIFF 1.2 and 2.0 normalized to use `resname` and `restype` field names.
- [x] **Design decision: ContextResType enum** — Dedicated backed string enum to validate `restype` values against the 5 allowed lookup strategies. Chosen over `in_array` for type safety, IDE support, and extensibility.
- [x] **Implement ContextResType enum** (TDD) — `lib/Model/Segments/ContextResType.php` with 5 cases. 15 tests.
- [x] **Add RESNAME + RESTYPE to marshaller** (TDD) — Two new cases in `SegmentMetadataMarshaller`. `RESTYPE.marshall()` validates via `ContextResType::tryFrom()`, returns `null` for invalid values. `RESNAME.marshall()` default string behavior. 14 tests added.

### 7.2 Future Work (no plan yet)

- [ ] **Integration: Connect metadata to context review page** — The backend stores `id_content`, `id_order`, `id_order_group`, `screenshot` but the frontend context review page doesn't consume them yet.
- [ ] **Frontend: Extend segment message protocol** — Currently sends `{sid, source, target}`. Needs `resname` + `restype` metadata for lookup strategies.
- [ ] **Frontend: Implement 5 lookup strategies** — XPath, ID, CSS class, custom path, attribute pair. Currently only text-matching is implemented.
- [ ] **Frontend: Fallback chain** — `restype` lookup → text matching (graceful degradation).
- [ ] **Screenshot handling** — The `screenshot` metadata key is stored but there's no upload/retrieval/display flow.
- [ ] **Context rendering** — Use `id_content` and `id_order` to display segments in their original document layout on the context review page.
- [ ] **Cross-tab sync** — `contextReviewChannel.js` BroadcastChannel exists but may need backend coordination for segment selection sync.
- [ ] **Review workflow states** — Currently no project-level flag. May eventually need review states (pending, in-progress, approved, rejected).
- [ ] **`getBySegmentIds()` collection support** — Currently returns `SegmentMetadataStruct[]`. Could return collection if multi-segment typed access is needed.

---

## 8. Constraints (verbatim from requirements)

- "a project is created without a flag declaring that an in-context review type is enabled like any other normal project"
- "the backend must get a list of defined attributes from the xliff trans-unit and store the in segments_metadata"
- "for now we will use only the parameters defined in ProjectMetadataModel::isAMetadata"
- "exclude group ExternalServices, you do not have the needed AWS and MyMemory keys"
- "Before closing a task and decide to commit, YOU MUST RUN ALL the tests"
- "do not use class constant SizeRestrictionChecker::SIZE_RESTRICTION, remove this class constant from all the codebase and use the enum"
- "it should not know about segment metadata structs" (TransUnitAttributeFilter scope)
- "I DON'T WANT segmentextractor call any method to unmarshall in SegmentExtractor"
- "SegmentMetadataMapper::fromTransUnitAttributes is the one who creates the SegmentMetadataStruct, it must create the definitive segmentstruct"
- "segmentMetadataMarshaller must accept a Struct, not a string"
- "serve typed" (API returns typed values, not all strings)
- "i want point 5 implementation, dedicated ContextResType enum" (enum over `in_array` for restype validation)
- "both 1.2 and 2.0 now get restype and resname as field names" (XLIFF versions normalized)
- "we will ignore Equipment.html.xlf Equipment.html files" (not lookup strategies)
- Do NOT modify files in `mm_bkp/` directory
- Do NOT touch `ERR_SIZE_RESTRICTION` (int constant, error code 3000)

---

## 9. Related Implementation Plans

| Plan | Status | Location |
|---|---|---|
| Segment metadata filter | Executed | `docs/superpowers/plans/2026-03-25-segment-metadata-filter.md` |
| Metadata validation hardening | Executed | `docs/superpowers/plans/2026-03-24-metadata-validation-hardening.md` |
| DAO unmarshalling | Executed | `docs/superpowers/plans/2026-03-25-segment-metadata-dao-unmarshalling.md` |

---

## 10. Context Mapping — Technical Analysis

> Based on analysis of `Technical Specification_ Mapping Between Contextual Markup and Translatable Strings.md`

### 10.1 Reference Spec Summary

The specification defines how XLIFF `<trans-unit>` attributes map translated segments to their visual positions in the original document (HTML context). Two attributes are key:

| Attribute | Purpose | Example |
|---|---|---|
| `resname` | **Target value** — the identifier/selector/path to locate the element in context HTML | `//html/body/div[2]/p[1]`, `content-block-42`, `product-title` |
| `restype` | **Strategy type** — declares HOW `resname` should be interpreted | `x-path`, `x-tag-id`, `x-css_class` |

### 10.2 The 5 Lookup Strategies (from spec)

| `restype` value | Strategy | `resname` contains | Example |
|---|---|---|---|
| `x-path` | XPath | Full XPath expression | `//html/body/div[2]/p[1]` |
| `x-client_nodepath` | Client node path | Proprietary CMS path | `root.section[2].paragraph[1]` |
| `x-tag-id` | Element ID | HTML `id` attribute value | `product-title` |
| `x-css_class` | CSS class | CSS class name | `translatable-block` |
| `x-attribute_name_value` | Attribute pair | `name=value` format | `data-trans-id=seg-42` |

### 10.3 Gap Analysis

| Layer | Status | Detail |
|---|---|---|
| **XLIFF Parsing** | ✅ Done | `XliffParserV1/V2` already extract ALL trans-unit attributes including `resname` and `restype` |
| **Storage (marshaller whitelist)** | ✅ Done | `RESNAME` + `RESTYPE` cases added to `SegmentMetadataMarshaller` (Commit 3) |
| **Storage (validation)** | ✅ Done | `ContextResType` enum validates `restype` against 5 allowed strategies (Commit 3) |
| **Frontend message protocol** | ❌ Not started | Segment messages send `{sid, source, target}` — no metadata |
| **Frontend lookup strategies** | ❌ Not started | Only text-matching implemented (`contextReviewUtils.js:tagSegments()`) |

**Backend pipeline complete.** XLIFF files with `resname`/`restype` attributes will now have them stored automatically on project creation. No changes needed to mapper, collection, DAO, or storage service.

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

**Behavior**: Invalid `restype` values (e.g. `x-title`, `x-li`) are silently rejected by `marshall()` returning `null` → mapper skips them. No error thrown, no invalid data stored.

### 10.5 Cross-Validation Note

No cross-validation between `resname` and `restype` at storage time. If `restype` is present but `resname` is missing (or vice versa), both are stored independently. The consumer (frontend) is responsible for checking that both are present before attempting a lookup strategy — missing either = graceful fallback to text matching.

### 10.6 XLIFF Parser Evidence

```
// XliffParserV1::extractTransUnitMetadata()
// Already extracts: $xliff['files'][$i]['trans-units'][$j]['attr']
// Includes ALL attributes: id, resname, restype, maxwidth, size-unit, etc.
// No parser changes needed.
```

### 10.7 Updated Allowed Metadata Keys (after implementation)

| Enum Case | DB Value (`meta_key`) | Marshall | Unmarshall |
|---|---|---|---|
| `ID_REQUEST` | `id_request` | `string → string` | `string → string` |
| `ID_CONTENT` | `id_content` | `string → string` | `string → string` |
| `ID_ORDER` | `id_order` | `string → string` | `string → string` |
| `ID_ORDER_GROUP` | `id_order_group` | `string → string` | `string → string` |
| `SCREENSHOT` | `screenshot` | `string → string` | `string → string` |
| `SIZE_RESTRICTION` | `sizeRestriction` | `mixed → string` (cast) | `string → int` (cast) |
| `RESNAME` | `resname` | `string → string` | `string → string` |
| `RESTYPE` | `restype` | `string → string` (validated) | `string → string` |

> `RESTYPE.marshall()` returns `null` for values not in `ContextResType` — mapper skips invalid entries.

### 10.8 Frontend Status (pre-existing, before our session)

| Component | Current State |
|---|---|
| `contextReviewUtils.js:tagSegments()` | Text-matching only — walks block-level elements, normalizes text, case-insensitive match |
| Segment message protocol | `{sid, source, target}` — no metadata fields |
| XPath lookup | Not implemented |
| ID lookup | Not implemented |
| CSS class lookup | Not implemented |
| Custom path lookup | Not implemented |
| Attribute pair lookup | Not implemented |
| Fallback chain | Not implemented (text-matching is the ONLY strategy) |
