# Extract `saveMetadata()` into `ProjectMetadataService`

**Date:** 2026-03-18
**Branch:** `project-manager-refactory`
**Status:** Approved

## Problem

`ProjectManager::saveMetadata()` is 92 lines with nesting depth 3, 6 branches, 3 loops, and engine instantiation logic. It is the highest-complexity method remaining in `ProjectManager` (1426 lines total). Extracting it reduces `ProjectManager` by ~90 lines and isolates metadata persistence into a focused, independently testable service.

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Extraction scope | Move logic as-is, no behavior changes | Keep PR focused; engine loop simplification is a separate concern |
| Service shape | Standalone class with constructor-injected DAO | Follows existing patterns (`TmKeyService`, `JobCreationService`) |
| FeatureSet dependency | Method parameter on `save()` | Service doesn't own the FeatureSet; keeps construction simple |
| Test strategy | Repoint existing `SaveMetadataTest` to test service directly | More direct; avoids redundant dual test layers |

## New Class: `ProjectMetadataService`

**File:** `lib/Model/ProjectCreation/ProjectMetadataService.php`
**Namespace:** `ProjectCreation`

### Constructor

```php
public function __construct(
    private ProjectsMetadataDao $dao
)
```

### Public API

```php
/**
 * Persist project-level metadata options.
 *
 * @throws Exception
 */
public function save(ProjectStructure $projectStructure, FeatureSet $features): void
```

### Dependencies

- `ProjectsMetadataDao` — constructor injection (for `set()` calls)
- `ProjectStructure` — method parameter (data source)
- `FeatureSet` — method parameter (`loadProjectDependenciesFromProjectMetadata()`)
- Static/const references (unchanged): `EngineConstants`, `MyMemory`, `EngineStruct`, `XliffRulesModel`, `JobsMetadataDao::SUBFILTERING_HANDLERS`, `ProjectsMetadataDao::*` constants

### Logic (verbatim from current `saveMetadata()`)

1. Read `$projectStructure->metadata` as initial `$options` array
2. Add `FROM_API` flag if `$projectStructure->from_api` is truthy
3. JSON-encode `xliff_parameters` if it's an `XliffRulesModel` with rules
4. Persist `pretranslate_101` if set
5. JSON-encode or remove `MT_QE_WORKFLOW_PARAMETERS` based on `MT_QE_WORKFLOW_ENABLED`
6. Call `$features->loadProjectDependenciesFromProjectMetadata($options)`
7. JSON-encode `filters_extraction_parameters` if present
8. Collect engine configuration keys by instantiating all engines and calling `getConfigurationParameters()`
9. Persist engine config values from `$projectStructure` dynamic properties
10. Persist all `$options` via individual `$dao->set()` calls
11. Persist `subfiltering_handlers` separately if non-empty

## ProjectManager Changes

### `saveMetadata()` — thin delegation (~4 lines)

```php
protected function saveMetadata(): void
{
    $service = $this->getProjectMetadataService();
    $service->save($this->projectStructure, $this->features);
}
```

### New factory method

```php
protected function getProjectMetadataService(): ProjectMetadataService
{
    return new ProjectMetadataService($this->getProjectsMetadataDao());
}
```

- `getProjectsMetadataDao()` remains on `ProjectManager` (used by the factory)
- Follows the established testable-subclass pattern for dependency injection

## Test Changes

### `SaveMetadataTest.php` — repoint to `ProjectMetadataService`

- Replace `TestableProjectManager` usage with direct `ProjectMetadataService` instantiation
- Pass mock `ProjectsMetadataDao` to constructor
- Set up `ProjectStructure` and mock `FeatureSet` the same way as today
- Call `$service->save($projectStructure, $features)` instead of `$pm->callSaveMetadata()`
- All 16 test methods preserved with identical assertions
- File moves from testing through `ProjectManager` to testing `ProjectMetadataService` directly

### `TestableProjectManager.php` — cleanup

- Remove `callSaveMetadata()` public wrapper (no longer needed)
- `setProjectsMetadataDao()` **must be retained** — also used by `SettersGettersConfigTest` for `saveFeaturesInMetadata()` tests

## File Impact Summary

| File | Change |
|------|--------|
| `lib/Model/ProjectCreation/ProjectMetadataService.php` | **NEW** — ~100 lines |
| `lib/Model/ProjectCreation/ProjectManager.php` | `saveMetadata()` reduced from 92 to ~4 lines; new `getProjectMetadataService()` factory |
| `tests/unit/Model/ProjectCreation/SaveMetadataTest.php` | Repointed to test `ProjectMetadataService` directly |
| `tests/unit/Model/ProjectCreation/TestableProjectManager.php` | Remove `callSaveMetadata()`, possibly `setProjectsMetadataDao()` |

## What Is NOT Changing

- No behavioral changes to metadata persistence logic
- Engine instantiation loop preserved as-is (simplification is a follow-up)
- `createProject()` orchestrator untouched
- `extractSegmentsCreateProjectAndStoreData()` still calls `saveMetadata()` the same way
- Other `ProjectManager` methods unchanged

## Success Criteria

- All 16 `SaveMetadataTest` tests pass against `ProjectMetadataService`
- `ProjectManager::saveMetadata()` is <=5 lines
- Full test suite (`php vendor/bin/phpunit`) passes with no regressions
- No behavioral changes in metadata persistence
