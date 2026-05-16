# Old Tests — Valuable Scenario Reference

> **Purpose**: This document preserves test scenarios extracted from the legacy `old_tests/` directory before its deletion.
> These are **not runnable tests** — they are a reference catalog of behaviors that were once tested and should be
> covered by modern integration tests when the relevant subsystems are modified.
>
> **Date**: 2026-04-07
> **Source**: `old_tests/` (deleted in same commit as this document)

---

## How to Read This Document

Each entry lists:

- **Origin file** — the deleted test file path
- **What it tested** — plain-English description of the behavior under test
- **Test methods** — number of distinct test cases
- **Tier** — `A` (high value, complex workflow, no modern equivalent) or `B` (moderate value, simpler assertions)

---

## Tier A — High Value

These test complex, multi-step workflows with no modern coverage. Prioritize these when writing new integration tests.

### 1. Split & Merge with Review Preservation

| | |
|---|---|
| **Origin** | `old_tests/Features/ReviewImproved/SplitAndMergeTest.php` |
| **Tests** | 3 |
| **Tier** | A |

**Scenarios**:
- Splitting a job preserves review passwords on both resulting chunks
- Splitting a job preserves penalty points on both resulting chunks
- Merging split chunks recombines review state correctly

**Why it matters**: Split/merge is a destructive operation on job structure. Losing review passwords or penalty points silently would corrupt the review workflow.

---

### 2. QA Review Record Lifecycle

| | |
|---|---|
| **Origin** | `old_tests/Features/ReviewImproved/CreateRecordInQaJobReviewsTest.php` |
| **Tests** | 4 |
| **Tier** | A |

**Scenarios**:
- Creating a QA review record inserts into `qa_chunk_reviews` with correct initial state
- Splitting a job creates review records for each resulting chunk
- Merging chunks consolidates review records
- Review record totals (penalty points, reviewed word count) update correctly after split/merge

**Why it matters**: `qa_chunk_reviews` is the source of truth for revision quality metrics. Incorrect records after split/merge would produce wrong quality scores.

---

### 3. Translation Versioning on Change

| | |
|---|---|
| **Origin** | `old_tests/Features/TranslationVersions/setTranslationWithVersioningEnabledTest.php` |
| **Tests** | 4 |
| **Tier** | A |

**Scenarios**:
- Setting a translation creates a new version record with the previous text
- Setting the same translation text again does NOT create a duplicate version (no-op guard)
- Propagated translations also create version records in target segments
- Version number increments correctly across multiple edits

**Why it matters**: Translation versioning is the backbone of revision history. Silent version loss means reviewers cannot see what changed.

---

### 4. Project Completion State Machine

| | |
|---|---|
| **Origin** | `old_tests/API/V2/ProjectCompletionStatusTest.php` |
| **Tests** | 4 |
| **Tier** | A |

**Scenarios**:
- A newly created project reports `DOING` status
- Translating all segments transitions project to translate-complete
- Completing revision transitions project to revise-complete
- Split jobs: completion requires ALL chunks to be complete

**Why it matters**: Project completion drives notifications, billing, and delivery workflows. A stuck or premature completion state breaks the entire delivery pipeline.

---

### 5. Project Creation Option Defaults

| | |
|---|---|
| **Origin** | `old_tests/CreateProjectController/defaultProjectOptionTest.php` |
| **Tests** | 10 |
| **Tier** | A |

**Scenarios**:
- `speech2text` defaults to `true` for supported language pairs, `false` otherwise
- `lexiqa` defaults to `true` for supported language pairs, `false` otherwise
- `tag_projection` defaults to `true` for supported language pairs, `false` otherwise
- Each option can be explicitly overridden to `true` or `false` regardless of language pair
- Options are stored correctly in `job_metadata`
- Unsupported language pairs do not silently enable features

**Why it matters**: Feature flags per job affect translation editor behavior. Wrong defaults mean translators see broken UI panels or miss available tools.

---

## Tier B — Moderate Value

Simpler assertions, often API contract tests. Useful as a checklist when modifying the relevant endpoints.

### 6. Version Number Increment & Propagation

| | |
|---|---|
| **Origin** | `old_tests/Features/TranslationVersions/increaseVersionNumberTest.php` |
| **Tests** | 2 |
| **Tier** | B |

**Scenarios**:
- Version number increments by 1 on each translation change
- Propagated segments maintain consistent version numbers with their source

---

### 7. Versioning Disabled — No Records Created

| | |
|---|---|
| **Origin** | `old_tests/Features/TranslationVersions/setTranslationWithVersioningDisabledTest.php` |
| **Tests** | 2 |
| **Tier** | B |

**Scenarios**:
- When versioning is disabled, setting a translation does NOT insert version records
- Propagated translations also skip version creation when disabled

---

### 8. QA Model Assignment

| | |
|---|---|
| **Origin** | `old_tests/Features/ReviewImproved/AssignQualityModelToProjectTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- Assigning a QA model to a project stores the model ID in project metadata

---

### 9. Segment Notes Extraction

| | |
|---|---|
| **Origin** | `old_tests/Features/SegmentNotes/SegmentNotesCreationTest.php` |
| **Tests** | 3 |
| **Tier** | B |

**Scenarios**:
- Notes are extracted from SDLXLIFF `<note>` elements and stored per-segment
- Notes are extracted from XLIFF files with `<seg-source>` elements
- Empty or missing notes do not create spurious records

---

### 10. Job Merge API

| | |
|---|---|
| **Origin** | `old_tests/API/V2/JobMergeTest.php` |
| **Tests** | 2 |
| **Tier** | B |

**Scenarios**:
- `POST /api/v2/jobs/{id}/merge` merges split chunks back into a single job
- Chunk-specific options (speech2text, etc.) are cleaned up after merge

---

### 11. Translation Versions API

| | |
|---|---|
| **Origin** | `old_tests/API/V2/SegmentVersionTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- `GET /api/v2/jobs/{id}/segments/{sid}/translation-versions` returns version array with correct structure

---

### 12. Chunk Options API

| | |
|---|---|
| **Origin** | `old_tests/API/V2/ChunkOptionsControllerTest.php` |
| **Tests** | 3 |
| **Tier** | B |

**Scenarios**:
- `GET` returns current speech2text, tag_projection, lexiqa settings
- `POST` updates individual options
- Invalid option names are rejected

---

### 13. Project Update API

| | |
|---|---|
| **Origin** | `old_tests/API/V2/ProjectUpdateTest.php` |
| **Tests** | 3 |
| **Tier** | B |

**Scenarios**:
- Setting project assignee via API
- Unsetting project assignee (null)
- Changing project name

---

### 14. Team Creation API

| | |
|---|---|
| **Origin** | `old_tests/API/V2/CreateTeamTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- Creating a team with member UIDs returns the new team with members attached

---

### 15. Project URLs API

| | |
|---|---|
| **Origin** | `old_tests/API/V2/ProjectUrlsTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- `GET /api/v2/projects/{id}/urls` returns translate and revise URLs for all jobs

---

### 16. API Key Authentication (V1)

| | |
|---|---|
| **Origin** | `old_tests/API/V1/NewWithOwnershipTest.php` |
| **Tests** | 3 |
| **Tier** | B |

**Scenarios**:
- Valid API key creates project owned by the key's user
- Invalid API key returns 401
- No API key creates project under anonymous/default assignment

---

### 17. Project Status API (V1)

| | |
|---|---|
| **Origin** | `old_tests/API/V1/StatusTest.php` |
| **Tests** | 3 |
| **Tier** | B |

**Scenarios**:
- `/api/status` on a new project returns `DOING` with 0% progress
- `/api/status` on a fully translated project returns appropriate completion percentage
- Response includes `analyze`, `create`, and `translate` phase information

---

### 18. Language Code Validation (V1)

| | |
|---|---|
| **Origin** | `old_tests/API/V1/ValidateSourceAndTargetLanguagesTest.php` |
| **Tests** | 2 |
| **Tier** | B |

**Scenarios**:
- Valid RFC 5646 language codes are accepted
- Invalid language codes return a validation error

---

### 19. Project Type Metadata (V1)

| | |
|---|---|
| **Origin** | `old_tests/API/V1/NewWithRevisionTypeTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- `project_type` parameter is stored in project metadata

---

### 20. Personal Team Assignment (V1)

| | |
|---|---|
| **Origin** | `old_tests/API/V1/NewWithTeamTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- Project created via API is assigned to the user's personal team

---

### 21. TM Key Association (V1)

| | |
|---|---|
| **Origin** | `old_tests/API/V1/NewWithPrivateTMKeyTest.php` |
| **Tests** | 1 |
| **Tier** | B |

**Scenarios**:
- Providing a private TM key during project creation associates it with the project

---

### 22. TM Key via UI Flow

| | |
|---|---|
| **Origin** | `old_tests/CreateProjectController/setPrivateTMKeyTest.php` |
| **Tests** | 2 |
| **Tier** | B |

**Scenarios**:
- TM key set through the project creation form is stored
- Multiple TM keys can be associated

---

### 23. Language Validation via UI Flow

| | |
|---|---|
| **Origin** | `old_tests/CreateProjectController/sourceAndTargetLangValidationTest.php` |
| **Tests** | 2 |
| **Tier** | B |

**Scenarios**:
- Invalid source language in the creation form returns error
- Invalid target language in the creation form returns error

---

## Support Infrastructure Notes

The deleted `old_tests/` also contained helper code worth noting for anyone writing future integration tests:

- **`old_tests/functions.php`** (324 lines) — Helper functions `integrationCreateTestProject()`, `splitJob()`, `mergeJob()` that encode real workflow sequences (create project → wait for analysis → split → translate → merge). These sequences document the correct API call order for end-to-end testing.

- **`old_tests/FixturesLoader.php`** — A YAML-to-DAO fixture insertion pattern. File paths were broken but the pattern (load YAML, hydrate structs, insert via DAO) is worth considering for future test data setup.

- **All data/fixture files** (`old_tests/resources/`, `old_tests/support/`) — 100% redundant with `tests/resources/`. No unique test data was lost.

---

## Summary

| Tier | Scenarios | Test Methods |
|---|---|---|
| A (High Value) | 5 | 25 |
| B (Moderate Value) | 18 | 31 |
| **Total** | **23** | **56** |

Three additional test files (`GetSegmentsTest`, `Functions_Test`, `FixturesLoader`) were classified as **dead code** — empty classes, broken fixtures, or utility-only with no assertions — and are not included above.
