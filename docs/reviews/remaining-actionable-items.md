# Remaining Actionable Items

> **Branch:** `context-review`
> **Created:** 2026-04-07
> **Source:** `.sisyphus/drafts/codebase-remediation.md`, feature spec §10.8, `old-tests-valuable-scenarios.md`

---

## B — Codebase Remediation

> Source document: `docs/reviews/2026-04-03-codebase-review.md`
> Validated: 2026-04-07 (3 parallel explore agents cross-checked all claims)

### Tier 1 — Quick Wins (hours each)

| #    | Item                           | Scope            | Detail                                                                                                                                                                                                      | Status  |
| ---- | ------------------------------ | ---------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| QW-1 | Delete `old_tests/`            | 102 files        | Scenarios extracted to `old-tests-valuable-scenarios.md`                                                                                                                                                    | ✅ Done |
| QW-2 | Fix `->escape()` calls         | 7 calls, 2 files | `SearchModel.php:209,239,245` + `UserDao.php:407-410` — replace with parameterized queries                                                                                                                  | ✅ Done |
| QW-3 | Fix `$_POST/$_GET/$_FILES`     | 9 files          | Replace `$_POST` with `$this->params` (3 files) + replace `$_FILES` with Klein `$this->request->files()` (6 files) + refactor `TMSService::uploadFile()` and `UploadHandler` to accept injected file arrays | ✅ Done |
| QW-4 | Type `VersionHandlerInterface` | 1 interface      | Add `@return array{...}` shape docblock to `propagateTranslation()`                                                                                                                                         | ✅ Done |

### Tier 2 — Targeted Fixes (days each)

| #    | Item                                            | Scope             | Detail                                                       | Status  |
| ---- | ----------------------------------------------- | ----------------- | ------------------------------------------------------------ | ------- |
| TF-1 | Fix `Database.php` bug line 319                 | 1 file, 2 callers | ON DUPLICATE KEY bind values lost — **data corruption risk** | ✅ Done |
| TF-2 | Fix `$_POST/$_GET` in moderate controllers      | 3 files           | More complex controllers requiring careful testing           | ⬜      |
| TF-3 | Extract `SetTranslationController::translate()` | 314-line method   | Worst single method in codebase                              | ⬜      |

### Tier 3 — Structural Refactors (weeks/months)

| #    | Item                           | Scope                             | Detail                                                        | Status |
| ---- | ------------------------------ | --------------------------------- | ------------------------------------------------------------- | ------ |
| SR-1 | `Database::obtain()` singleton | 311 calls in 99 files             | Replace with injected connection                              | ⬜     |
| SR-2 | Split `ProjectStructure`       | 83 properties, 6 lifecycle groups | God object decomposition                                      | ⬜     |
| SR-3 | Break up Controllers           | 4,221 total lines, 4 controllers  | `NewController` 1337 LOC, `SetTranslationController` 1030 LOC | ⬜     |
| SR-4 | Dissolve `CatUtils` + `Utils`  | 1,992 lines, 69 static methods    | Static utility god classes                                    | ⬜     |
| SR-5 | Type FeatureSet dispatch       | 52 untyped hook names             | Add typed hook signatures                                     | ⬜     |

---

## C — In-Context Review Feature

> Source: feature spec `docs/2026-03_26-11:16-in-context-review-feature-development-spec.md` §10.8

| #   | Item                                     | Status         | Detail                                                                      |
| --- | ---------------------------------------- | -------------- | --------------------------------------------------------------------------- |
| C.1 | Screenshot display                       | ❌ Not started | Backend stores `screenshot` field, no frontend consumer                     |
| C.2 | `id_content` / `id_order` rendering      | ❌ Not started | Backend serves fields, no frontend consumer                                 |
| C.3 | Review workflow states                   | ❌ Not started | No state machine or status transitions                                      |
| C.4 | `x-client_nodepath` strategy             | ⚠️ Stub        | Returns `null` — falls through to text matching                             |
| C.5 | File-level `<file>` attribute extraction | ⚠️ Future      | Parser doesn't extract `<file>` attributes; `FilesMetadataMarshaller` ready |

---

## D — Test Coverage Expansion

> Source: `docs/reviews/old-tests-valuable-scenarios.md`

23 test scenario families (56 test methods) extracted from `old_tests/` as candidates for reimplementation in the modern `tests/` suite. These are a reference catalog, not active tasks. Consult the document when expanding test coverage.

---

## Priority Matrix

| Priority       | Items                                    | Key risk                                 |
| -------------- | ---------------------------------------- | ---------------------------------------- |
| **High**       | TF-1 (Database bug)                      | Data corruption on ON DUPLICATE KEY      |
| **Medium**     | ~~QW-2~~, ~~QW-3~~, ~~QW-4~~, TF-2, TF-3 | Tech debt / SQL injection surface        |
| **Low / Team** | C.1–C.5 (frontend features)              | Depends on frontend team                 |
| **Long-term**  | SR-1 through SR-5                        | Architectural debt, needs appetite check |
