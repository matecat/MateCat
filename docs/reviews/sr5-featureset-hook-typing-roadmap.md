# SR-5: Type FeatureSet Hook Dispatch — Roadmap

> **Created:** 2026-04-07
> **Phase 1:** @method annotations on FeatureSet.php + inconsistency fixes (COMPLETE)
> **Phase 2:** HookRegistry runtime validation (FUTURE)
> **Phase 3:** Interface contracts per domain (FUTURE)

---

## Current State

`FeatureSet::filter()` and `FeatureSet::run()` dispatch hooks via string names and `func_get_args()` + `call_user_func_array()`. Zero type safety at dispatch time.

### Inventory

| Metric                      | Count              |
| --------------------------- | ------------------ |
| Unique hook names           | 62                 |
| Filter hooks                | 40 (52 call sites) |
| Run hooks                   | 22 (23 call sites) |
| Hooks with handlers in repo | 11 (18%)           |
| Hooks with NO handler       | 51 (82%)           |
| Plugin classes              | 5 active           |

### Plugin Hierarchy

```
IBaseFeature (interface)
  └─ BaseFeature (abstract)
       ├─ AbstractRevisionFeature
       │    ├─ ReviewExtended
       │    └─ SecondPassReview
       ├─ ProjectCompletion
       ├─ TranslationVersions
       └─ UnknownFeature
```

### Handler Typing State (existing handlers only)

- Parameter types: 85% covered
- Return types: 100% covered
- PHPDoc: 43% covered
- Dispatch-time validation: **0%**

---

## Phase 2 — HookRegistry Runtime Validation (FUTURE)

### Goal

Add opt-in runtime validation that catches signature mismatches during development without impacting production.

### Design

```php
class HookRegistry
{
    /** @var array<string, HookSignature> */
    private static array $signatures = [];

    public static function register(string $hookName, HookSignature $signature): void { ... }
    public static function validate(string $hookName, array $args): void { ... }
}

class HookSignature
{
    public function __construct(
        public readonly array $paramTypes,    // ['array', JobStruct::class]
        public readonly string $returnType,   // 'array' | 'void' | 'bool'
        public readonly HookType $type,       // HookType::FILTER | HookType::RUN
    ) {}
}
```

### Integration Point

In `FeatureSet::filter()` and `FeatureSet::run()`, add:

```php
if (AppConfig::$DEBUG_HOOKS) {
    HookRegistry::validate($method, $args);
}
```

### Effort

- **1-2 days**
- 2-3 new files (HookRegistry, HookSignature, HookType enum)
- Modify FeatureSet::filter() and FeatureSet::run() (+4 lines each)
- Risk: **Low** (behind debug flag)

---

## Phase 3 — Interface Contracts per Domain (FUTURE)

### Goal

Replace `method_exists()` discovery with `instanceof` checks against typed interfaces. Plugins implement domain-specific interfaces instead of matching method names by convention.

### Domain Grouping

| Interface                        | Hooks                                                                                                                                                                                                                              | Count |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----- |
| `TranslationHooksInterface`      | setTranslationCommitted, postAddSegmentTranslation, filterSetTranslationResult, rewriteContributionContexts, filterContributionStructOnSetTranslation, filterContributionStructOnMTSet                                             | 6     |
| `ProjectLifecycleHooksInterface` | validateProjectCreation, beforeProjectCreation, postProjectCreate, postProjectCommit, filterCreateProjectFeatures, filter_team_for_project_creation, handleJsonNotesBeforeInsert, handleTUContextGroups, filterProjectDependencies | 9     |
| `JobManagementHooksInterface`    | validateJobCreation, postJobSplitted, postJobMerged, checkSplitAccess, job_password_changed, review_password_changed, project_password_changed                                                                                     | 7     |
| `AnalysisHooksInterface`         | analysisBeforeMTGetContribution, afterTMAnalysisCloseProject, tmAnalysisDisabled, fastAnalysisComplete, filterPayableRates, wordCount                                                                                              | 6     |
| `QAValidationHooksInterface`     | checkTagMismatch, checkTagPositions, injectExcludedTagsInQa, characterLengthCount, filterGlobalWarnings, filterSegmentWarnings                                                                                                     | 6     |
| `ReviewHooksInterface`           | chunkReviewUpdated, alter_chunk_review_struct, filterIsChunkCompletionUndoable, filter_job_password_to_review_password, project_completion_event_saved, filterRevisionChangeNotificationList                                       | 6     |
| `SegmentHooksInterface`          | filterGetSegmentsResult, prepareNotesForRendering, prepareAllNotes, processExtractedJsonNotes, populatePreTranslations, doNotManageAlternativeTranslations                                                                         | 6     |
| `ProjectCreationHooksInterface`  | sanitizeOriginalDataMap, correctTagErrors, appendFieldToAnalysisObject, decodeInstructions, encodeInstructions                                                                                                                     | 5     |
| `UIHooksInterface`               | appendInitialTemplateVars, filterActivityLogEntry, filterCreationStatus, filterProjectNameModified, outsourceAvailableInfo, projectUrls, isAnInternalUser, overrideConversionResult                                                | 8     |
| `InternalHooksInterface`         | filterFeaturesMerged, bootstrapCompleted, processZIPDownloadPreview, filterMyMemoryGetParameters                                                                                                                                   | 4     |

### Migration Strategy

1. Create interfaces with typed method signatures
2. Make `BaseFeature` implement all interfaces with default no-op methods
3. In `FeatureSet`, add `instanceof` check before `method_exists()` — both paths work during migration
4. Gradually remove `method_exists()` fallback as all plugins are migrated
5. External/proprietary plugins get a migration window

### Effort

- **1-2 weeks**
- 10+ new interface files
- Modify BaseFeature, all plugin subclasses
- Modify FeatureSet dispatch logic
- Risk: **Medium** (external plugin compatibility)

### Key Constraint

51 of 62 hooks have no handler in this repository — they're implemented by external/proprietary plugins. Any Phase 3 change MUST maintain backward compatibility with those unseen plugins via the `method_exists()` fallback path during migration.

---

## Hook Naming Convention

### Current State

Hook names follow two conventions with no clear migration path:

| Convention     | Count | Examples                                                                                          |
| -------------- | ----- | ------------------------------------------------------------------------------------------------- |
| **camelCase**  | ~45   | `filterGetSegmentsResult`, `postProjectCreate`, `handleTUContextGroups`, `outsourceAvailableInfo` |
| **snake_case** | ~17   | `filter_team_for_project_creation`, `job_password_changed`, `alter_chunk_review_struct`           |

### Domain Clustering

Snake_case hooks cluster around specific domains:

- **Password lifecycle:** `job_password_changed`, `review_password_changed`, `project_password_changed`
- **Team/project creation:** `filter_team_for_project_creation`
- **Review/completion:** `alter_chunk_review_struct`, `filter_job_password_to_review_password`, `project_completion_event_saved`

### Decision: Document, Don't Rename

Renaming hooks is a **backward-incompatible change** — every plugin implementing the hook by method name would break. Since 51 of 62 hooks are implemented by external/proprietary plugins outside this repository, renaming is not viable without a coordinated migration.

**Policy:**

- **Existing hooks:** Keep current naming as-is (camelCase or snake_case)
- **New hooks:** Use **camelCase** (aligns with PHP PSR-1 method naming and the 73% majority)
- **Phase 3 interfaces:** Will normalize naming through typed interface method names, decoupling the string hook name from the PHP method name

---

## Inconsistencies Found & Resolved

1. **`rewriteContributionContexts`** — `@method` annotation param named `$contextData`, handlers use `$postInput`, call sites pass `$this->data`/`$request`. All carry `context_before`/`context_after` keys.
   - **Resolution:** ✅ Renamed annotation param to `$requestData` (reflects both call sites accurately)

2. **Hook naming conventions are mixed** — ~45 camelCase vs ~17 snake_case.
   - **Resolution:** ✅ Documented in "Hook Naming Convention" section above. Policy: keep existing, new hooks use camelCase.

3. **`filterProjectNameModified` ignores return value** — caller uses `filter()` but discards result, making it effectively a run hook.
   - **Resolution:** ✅ Changed call site from `filter()` to `run()`, moved `@method` annotation to run section with `void` return.

4. **`handleTUContextGroups` ignores return value** — same pattern. Object mutation via reference makes filter unnecessary.
   - **Resolution:** ✅ Changed call site from `filter()` to `run()`, moved `@method` annotation to run section with `void` return.

5. **`alter_chunk_review_struct` ignores return value** — handler in `AbstractRevisionFeature` mutates `CompletionEventStruct` properties directly via reference.
   - **Resolution:** ✅ Changed call site from `filter()` to `run()`, moved `@method` annotation to run section with `void` return.
