# ProjectManager.php Refactoring Plan

**Created:** 2026-03-11  
**File:** `lib/Model/ProjectManager/ProjectManager.php` (3108 lines)

## Status

| # | Item | Priority | Status |
|---|------|----------|--------|
| 0a | Test `_extractSegments()` — seg-source path | 🔴 BLOCKER for step 2 | ✅ Done (11 tests) |
| 0b | Test `_extractSegments()` — non-seg-source path | 🔴 BLOCKER for step 2 | ✅ Done (8 tests) |
| 0b+ | Test notes, context-group, edge cases | 🔴 BLOCKER for step 2 | ✅ Done (12 tests) |
| 0c | Test `saveMetadata()` | 🟡 BLOCKER for step 4 | ✅ Done (18 tests) |
| 0d | Test `saveJobsMetadata()` | 🟡 BLOCKER for step 4 | ✅ Done (25 tests) |
| 0e | Test error recording | 🟡 BLOCKER for step 3 | ✅ Done (12 tests) |
| 1 | Delete dead code in `saveMetadata()` | HIGH | ✅ Done |
| 2 | Extract shared segment-processing logic from `_extractSegments()` | HIGH | ✅ Done |
| 3 | Extract error-recording helper (`addProjectError`) | MEDIUM | ✅ Done |
| 4 | Consolidate `saveJobsMetadata()` | LOW | ✅ Done |
| 5 | [Optional] Extract `_extractSegments()` into a separate class | OPTIONAL | ⬜ Pending |

### Coverage summary (86 tests, 208 assertions)

| Method | Lines covered | % |
|--------|--------------|---|
| `_extractSegments` | 165/190 | 86.8% 🟡 |
| `buildAndAppendSegment` | (new — extracted from `_extractSegments`) | covered via existing tests ✅ |
| `getSizeRestrictionValue` | (new — extracted from `_extractSegments`) | covered via existing tests ✅ |
| `__addNotesToProjectStructure` | 24/28 | 85.7% 🟡 |
| `getTargetStatesFromTransUnit` | 8/11 | 72.7% 🟡 |
| `createSegmentHash` | 5/7 | 71.4% 🟡 |
| `__isTranslated` | 7/7 | 100% ✅ |
| `__addTUnitContextsToProjectStructure` | 6/6 | 100% ✅ |
| `_strip_external` | 1/1 | 100% ✅ |
| `initArrayObject` | 2/2 | 100% ✅ |
| `sanitizedUnitId` | 1/1 | 100% ✅ |
| `_validateUploadToken` | 6/6 | 100% ✅ |
| `_validateXliffParameters` | 10/10 | 100% ✅ |
| `sanitizeProjectStructure` | 5/5 | 100% ✅ |
| `createProjectsMetadataDao` | (new — factory for testability) | covered via SaveMetadataTest ✅ |
| `saveMetadata` | 41/41 | 100% ✅ |
| `saveJobsMetadata` | 20/20 | 100% ✅ |
| `createJobsMetadataDao` | (new — factory for testability) | covered via SaveJobsMetadataTest ✅ |

---

## Step 0: Add Test Coverage (BLOCKER — must be done before any refactoring)

No test coverage currently exists for `ProjectManager`. Tests must be added **before** refactoring to catch regressions.

### Tests needed:

#### 0a. `_extractSegments()` — seg-source path
- Test with a sample XLIFF 1.2 file containing `<seg-source>` with `<mrk>` tags
- Verify segments are created with correct `xliff_mrk_id`, `xliff_ext_prec_tags`, `xliff_mrk_ext_prec_tags/succ_tags`
- Verify pre-translations are stored when target has translated state
- Verify `sizeRestriction` metadata is saved
- Verify `SegmentOriginalDataStruct` with `dataRefMap` is populated
- Verify word count and `show_in_cattool` counter increments
- Verify notes and context-group are added

#### 0b. `_extractSegments()` — non-seg-source path
- Test with a sample XLIFF 1.2 file WITHOUT `<seg-source>` (simple `<source>`/`<target>`)
- Verify segments are created with `null` mrk fields
- Verify pre-translations are stored when target has translated state
- Verify `sizeRestriction` metadata is saved
- Verify `SegmentOriginalDataStruct` handling (constructor-based, different from seg-source path)
- Verify word count and `show_in_cattool` counter increments
- Verify notes and context-group are added

#### 0c. `saveMetadata()` — ✅ DONE (18 tests, 52 assertions)
Tests cover:
- `subfiltering_handlers` is always persisted unconditionally
- All `$dao->set()` calls use the correct `id_project`
- Empty metadata only persists `subfiltering_handlers`
- `from_api` flag is persisted when true, omitted when false
- `xliff_parameters` is JSON-encoded when `XliffConfigTemplateStruct`, omitted otherwise
- `pretranslate_101` is passed through to DAO
- `mt_qe_workflow_parameters` are JSON-encoded when `mt_qe_workflow_enabled` is true
- `filters_extraction_parameters` are JSON-encoded when present, omitted when empty
- Engine extra keys (`mmt_glossaries`, `deepl_formality`, `lara_style`) from `$extraKeys` loop
- `sanitize_project_options` branch: invalid `segmentation_rule` stripped by sanitizer
- `sanitize_project_options` disabled: values pass through unchanged
- All metadata options are persisted via `set()`
- Combined scenario with multiple features together

Production changes for testability:
- `saveMetadata()` changed from `private` to `protected` (zero behavioral change)
- New `createProjectsMetadataDao(): ProjectsMetadataDao` factory method extracted (overridden in test subclass)

#### 0d. `saveJobsMetadata()` — ✅ DONE (25 tests, 74 assertions)
Tests cover:
- `subfiltering_handlers` is always persisted unconditionally (empty and non-empty values)
- All `$dao->set()` calls use the correct `$newJob->id` and `$newJob->password`
- Empty project structure only persists `subfiltering_handlers`
- `public_tm_penalty` is persisted when set, omitted when not set
- `character_counter_count_tags` truthy → `"1"`, falsy → `"0"`, omitted when not set
- Integer `1`/`0` also correctly coerced to `"1"`/`"0"` for character counter
- `character_counter_mode` is persisted when set, omitted when not set
- `tm_prioritization` truthy → `"1"` (int 1 coerced by DAO's `string` type-hint), falsy → `"0"`, omitted when not set
- String `"1"` also correctly produces `"1"` for tm_prioritization
- `dialect_strict` JSON-decoded, only matching language value persisted
- `dialect_strict` non-matching languages produce no DAO call
- `dialect_strict` trims whitespace on both key and `$newJob->target` for matching
- `dialect_strict` with empty JSON object `{}` produces no DAO call
- `dialect_strict` with multiple languages only persists the matching one
- Combined scenario with all options at once (6 DAO calls)
- DAO call order matches code order (public_tm_penalty → ... → subfiltering_handlers)

Production changes for testability:
- `saveJobsMetadata()` changed from `private` to `protected` (zero behavioral change)
- New `createJobsMetadataDao(): JobsMetadataDao` factory method extracted (overridden in test subclass)

#### 0e. Error recording — ✅ DONE (12 tests, 30 assertions)
Tests cover:
- `_validateUploadToken()`: error recorded with code -19 for missing/invalid token, no error for valid token, exception code/message verified
- `_validateXliffParameters()`: error recorded with code 400 for invalid type, DomainException re-thrown, no error for valid params
- `sanitizeProjectStructure()`: errors reset to `ArrayObject`, error propagated from invalid token, error propagated from invalid xliff params
- Error entry structure: exactly `code` + `message` keys, multiple errors appended correctly

---

## Step 1: Delete dead code in `saveMetadata()` — ✅ DONE

Removed redundant hardcoded blocks (old lines 564-605) for `mmt_glossaries`, `lara_style`, `lara_glossaries`, `deepl_formality`, `deepl_id_glossary`. These keys are already handled by the `$extraKeys` loop which reads them from each engine's `getConfigurationParameters()`.

---

## Step 2: Extract shared segment-processing logic from `_extractSegments()` — ✅ DONE

### What was duplicated
The seg-source branch and non-seg-source branch duplicated:
- Word counting + `show_in_cattool` flag
- `_strip_external()` calls on source content
- Target pre-translation check (`__isTranslated` + `fromRawXliffToLayer0`) and storage in `translations`
- `SegmentMetadataStruct` creation with sizeRestriction (4× duplicated check)
- `SegmentOriginalDataStruct` creation
- `createSegmentHash()` with sizeRestriction
- `SegmentStruct` construction
- Counter increments (`files_word_count`, `_fileCounter_Show_In_Cattool`)
- Calls to `__addNotesToProjectStructure()` and `__addTUnitContextsToProjectStructure()`

### What was done
1. Extracted `getSizeRestrictionValue(array $xliff_trans_unit): ?int` helper — eliminates 4× duplicated sizeRestriction check
2. Extracted `buildAndAppendSegment(...)` helper that handles the common tail:
   - Creates `SegmentMetadataStruct` with sizeRestriction
   - Creates `SegmentOriginalDataStruct` via `setMap($dataRefMap)` (consistently for both branches)
   - Creates segment hash via `createSegmentHash()`
   - Builds and appends `SegmentStruct`
   - Updates `files_word_count` counter
3. Both branches now normalize their specific data shape and delegate to `buildAndAppendSegment()`

### Bug fix in non-seg-source branch
The non-seg-source branch previously used `new SegmentOriginalDataStruct(['data' => $segmentOriginalData])` which was a no-op (`SegmentOriginalDataStruct` has no `data` property), and only appended when non-empty. Now both branches consistently use `(new SegmentOriginalDataStruct())->setMap($dataRefMap)` and always append, matching the seg-source behavior.

### Preserved differences between branches
- **seg-source** passes mrk-specific fields (`xliff_mrk_id`, `xliff_mrk_ext_prec_tags`, `xliff_mrk_ext_succ_tags`); non-seg-source passes `null` for these
- **seg-source** applies `features->filter('wordCount', ...)` and `features->filter('populatePreTranslations', true)` — unchanged, stays in the branch
- **seg-source** applies `restoreUnicodeEntitiesToOriginalValues` + `trimAndStripFromAnHtmlEntityDecoded` before `__isTranslated` — unchanged, stays in the branch
- Translation storage differs (`offsetSet` with mid key + position vs `append`) — unchanged, stays in the branches
- Notes/context-group placement and exception-code handling — unchanged, stays in the branches

---

## Step 3: Extract error-recording helper — ✅ DONE

### What was duplicated
19 occurrences of:
```php
$this->projectStructure['result']['errors'][] = [
    "code" => $e->getCode(),
    "message" => $e->getMessage()
];
```

### What was done
1. Added `addProjectError(int $code, string $message): void` method (protected, to allow test subclass access)
2. Replaced all 19 call sites across 6 methods:
   - `_validateUploadToken()` (1 site)
   - `_validateXliffParameters()` (1 site)
   - `createProject()` (13 sites — cache package, zip handling, file storage errors, segment extraction errors)
   - `_pushTMXToMyMemory()` (1 site)
   - `_loopForTMXLoadStatus()` (1 site)
   - `setPrivateTMKeys()` (1 site)
3. The only remaining `projectStructure['result']['errors'][]` is inside `addProjectError()` itself
4. All code remappings preserved (e.g., exception code `-3` → error code `-16`, exception code `-4` → error code `-7`)
5. All 43 tests pass (12 error recording + 31 extract segments)

---

## Step 4: Consolidate `saveJobsMetadata()` — ✅ DONE

### What was duplicated
Four `if (isset(...)) { $jobsMetadataDao->set(...) }` blocks for:
- `public_tm_penalty`
- `character_counter_count_tags` (coerced to `"1"`/`"0"`)
- `character_counter_mode`
- `tm_prioritization` (coerced to `1`/`0`)

### What was done
Replaced the four repetitive blocks with a data-driven `$simpleKeys` map + loop:
```php
$simpleKeys = [
    'public_tm_penalty'            => null,
    'character_counter_count_tags'  => fn($v) => $v ? "1" : "0",
    'character_counter_mode'        => null,
    'tm_prioritization'            => fn($v) => $v ? 1 : 0,
];

foreach ($simpleKeys as $key => $transformer) {
    if (isset($projectStructure[$key])) {
        $value = $transformer ? $transformer($projectStructure[$key]) : $projectStructure[$key];
        $jobsMetadataDao->set($newJob->id, $newJob->password, $key, $value);
    }
}
```

`dialect_strict` (per-language matching) and `SUBFILTERING_HANDLERS` (unconditional) remain unchanged.

All 86 tests (208 assertions) pass with zero changes to test expectations.

---

## Step 5: [Optional] Extract `_extractSegments()` into a dedicated class

After step 2 reduces internal duplication, consider extracting into a `SegmentExtractor` class for testability. Requires dependency injection of `$projectStructure`, `$filter`, `$features`, `$filesMetadataDao`, and counter state.

