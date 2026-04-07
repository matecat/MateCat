# SR-5: Type FeatureSet Hook Dispatch — Roadmap

> **Created:** 2026-04-07
> **Phase 1:** @method annotations on FeatureSet.php (IMPLEMENTING)
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

## Inconsistencies to Address

1. **`rewriteContributionContexts`** — receives different arg types at different call sites:
   - SetTranslationController: `($segmentsList, $this->data)` — data is array
   - GetContributionController: `($segmentsList, $request)` — request is object
   - Resolution: Define union type or standardize caller

2. **Hook naming conventions are mixed:**
   - camelCase: `filterGetSegmentsResult`, `postProjectCreate`
   - snake_case: `filter_team_for_project_creation`, `job_password_changed`
   - Resolution: Document but don't rename (BC break)

3. **`filterProjectNameModified` ignores return value** — caller doesn't use the filtered result, making it effectively a run hook masquerading as filter

4. **`handleTUContextGroups` ignores return value** — same pattern as above
