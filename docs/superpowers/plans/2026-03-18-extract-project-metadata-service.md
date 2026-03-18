# Extract `saveMetadata()` into `ProjectMetadataService` — Implementation Plan

> **Status:** ✅ Complete — all 4 tasks done, squashed into `031a9db2a6`
>
> **Test command:** `./vendor/bin/phpunit --exclude-group ExternalServices`
>
> **Result:** 1757 tests, 15279 assertions, 0 errors, 0 failures

**Goal:** Extract the 92-line `saveMetadata()` method from `ProjectManager` into a standalone `ProjectMetadataService` class with no behavior changes.

**Architecture:** New `ProjectMetadataService` receives `ProjectsMetadataDao` via constructor injection. Its single public method `save(ProjectStructure, FeatureSet)` contains the verbatim logic from `saveMetadata()`. `ProjectManager::saveMetadata()` becomes a thin delegation. Existing tests are repointed to test the service directly.

**Tech Stack:** PHP 8.3, PHPUnit 12.5

**Spec:** `docs/superpowers/specs/2026-03-18-extract-project-metadata-service-design.md`

---

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `lib/Model/ProjectCreation/ProjectMetadataService.php` | **CREATE** | Metadata persistence logic extracted from `ProjectManager` |
| `lib/Model/ProjectCreation/ProjectManager.php` | **MODIFY** | Thin delegation in `saveMetadata()`, new `getProjectMetadataService()` factory |
| `tests/unit/Model/ProjectCreation/SaveMetadataTest.php` | **MODIFY** | Repoint all 16 tests to instantiate `ProjectMetadataService` directly |
| `tests/unit/Model/ProjectCreation/TestableProjectManager.php` | **MODIFY** | Remove `callSaveMetadata()` wrapper |

---

## Chunk 1: Create `ProjectMetadataService` and Repoint Tests

### Task 1: Create `ProjectMetadataService` with verbatim logic

**Files:**
- Create: `lib/Model/ProjectCreation/ProjectMetadataService.php`

- [x] **Step 1: Create `ProjectMetadataService.php`**

```php
<?php

namespace Model\ProjectCreation;

use Exception;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Xliff\DTO\XliffRulesModel;
use Utils\Constants\EngineConstants;
use Utils\Engines\MyMemory;

class ProjectMetadataService
{
    public function __construct(
        private ProjectsMetadataDao $dao
    ) {
    }

    /**
     * Persist project-level metadata options.
     *
     * This is where, among other things, we put project options.
     *
     * Project options may need to be sanitized so that we can silently ignore impossible combinations,
     * and we can apply defaults when those are missing.
     *
     * @throws Exception
     */
    public function save(ProjectStructure $projectStructure, FeatureSet $features): void
    {
        $options = $projectStructure->metadata;

        // "From API" flag
        if ($projectStructure->from_api) {
            $options[ProjectsMetadataDao::FROM_API] = '1';
        }

        // xliff_parameters — only persist when the model contains actual rules.
        // Guard with instanceof: createProject() normalizes to XliffRulesModel,
        // but saveMetadata() is protected and may be called from other paths.
        if (
            $projectStructure->xliff_parameters instanceof XliffRulesModel
            && (
                !empty($projectStructure->xliff_parameters->getRulesForVersion(1))
                || !empty($projectStructure->xliff_parameters->getRulesForVersion(2))
            )
        ) {
            $options[ProjectsMetadataDao::XLIFF_PARAMETERS] = json_encode($projectStructure->xliff_parameters);
        }

        // pretranslate_101
        if (isset($projectStructure->pretranslate_101)) {
            $options[ProjectsMetadataDao::PRETRANSLATE_101] = (string)$projectStructure->pretranslate_101;
        }

        // mt evaluation => ice_mt already in metadata
        // adds JSON parameters to the project metadata as JSON string
        if ($options[ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED] ?? false) {
            $options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS] = json_encode($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        } else {
            // When MT QE workflow is disabled, remove the raw array to prevent
            // passing a non-string value to MetadataDao::set()
            unset($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        }

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $features->loadProjectDependenciesFromProjectMetadata($options);

        if ($projectStructure->filters_extraction_parameters) {
            $options[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS] = json_encode($projectStructure->filters_extraction_parameters);
        }

        $extraKeys = [];
        // MT extra config parameters
        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $extraKeys = array_merge(
                $extraKeys,
                (new $engineName(
                    new EngineStruct([
                        'type' => $engineName == MyMemory::class ? EngineConstants::TM : EngineConstants::MT,
                    ])
                ))->getConfigurationParameters()
            );
        }

        foreach ($extraKeys as $extraKey) {
            $engineValue = $projectStructure->$extraKey;
            if (!empty($engineValue)) {
                $options[$extraKey] = $engineValue;
            }
        }

        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $this->dao->set(
                    (int)$projectStructure->id_project,
                    $key,
                    (string)$value
                );
            }
        }

        /** Duplicate the JobsMetadataDao::SUBFILTERING_HANDLERS in project metadata for easier retrieval.
         * During the analysis of the project, there is no need to query the JobsMetadataDao.
         * Configuration about handlers can be changed later in the job settings.
         * But the analysis must everytime be performed with the current configuration.
         * @see JobCreationService::saveJobsMetadata()
         */
        if (!empty($projectStructure->subfiltering_handlers)) {
            $this->dao->set(
                (int)$projectStructure->id_project,
                JobsMetadataDao::SUBFILTERING_HANDLERS,
                $projectStructure->subfiltering_handlers
            );
        }
    }
}
```

- [x] **Step 2: Verify the file parses without errors**

Run: `php -l lib/Model/ProjectCreation/ProjectMetadataService.php`
Expected: `No syntax errors detected`

- [x] **Step 3: Commit**

```bash
git add lib/Model/ProjectCreation/ProjectMetadataService.php
git commit -m "Add ProjectMetadataService with logic extracted from ProjectManager::saveMetadata()"
```

### Task 2: Repoint `SaveMetadataTest` to test `ProjectMetadataService` directly

**Files:**
- Modify: `tests/unit/Model/ProjectCreation/SaveMetadataTest.php`

The test currently creates a `TestableProjectManager`, injects a mock DAO via `setProjectsMetadataDao()`, sets project structure values via `setProjectStructureValue()`, and calls `callSaveMetadata()`. We need to change it to:
- Create a `ProjectMetadataService` directly with a mock `ProjectsMetadataDao`
- Create a `ProjectStructure` directly and set values on it
- Create a stub `FeatureSet`
- Call `$service->save($projectStructure, $features)` instead of `$pm->callSaveMetadata()`

- [x] **Step 1: Rewrite `SaveMetadataTest` setUp and helpers**

Replace the class properties, `setUp()`, `tearDown()`, and helper methods. The key changes:
- Remove `$pm` (TestableProjectManager) — replace with `$service` (ProjectMetadataService) + `$projectStructure` (ProjectStructure) + `$features` (FeatureSet)
- Remove AppConfig / MateCatFilter / MetadataDao / MatecatLogger setup (those were only needed for TestableProjectManager::initForTest)
- `setProjectStructureValue(key, value)` becomes direct `$this->projectStructure->$key = $value`

New imports:
```php
use Model\ProjectCreation\ProjectMetadataService;
use Model\ProjectCreation\ProjectStructure;
```

Remove imports no longer needed:
```php
use Matecat\SubFiltering\MateCatFilter;
use Model\Files\MetadataDao;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
```

Updated class header, properties, setUp, tearDown:
```php
class SaveMetadataTest extends AbstractTest
{
    private ProjectMetadataService $service;
    private ProjectStructure $projectStructure;
    private FeatureSet $features;

    /** @var array<int, array{0: int, 1: string, 2: mixed}> */
    private array $daoSetCalls = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->daoSetCalls = [];
        $stubDao = $this->createStub(ProjectsMetadataDao::class);
        $stubDao->method('set')
            ->willReturnCallback(function (int $idProject, string $key, mixed $value): bool {
                $this->daoSetCalls[] = [$idProject, $key, $value];
                return true;
            });

        $this->service = new ProjectMetadataService($stubDao);

        $this->features = $this->createStub(FeatureSet::class);

        $this->projectStructure = new ProjectStructure([
            'id_project'      => 999,
            'source_language' => 'en-US',
            'target_language' => ['it-IT'],
            'metadata'        => [],
        ]);
        $this->projectStructure->subfiltering_handlers = '[]';
    }

    // tearDown can be removed entirely (no AppConfig to restore)
```

Helper methods stay the same (they only reference `$this->daoSetCalls`).

- [x] **Step 2: Update all 16 test methods**

In every test method, apply two mechanical replacements:
1. `$this->pm->setProjectStructureValue('key', $val)` → `$this->projectStructure->key = $val`  
   (for literal keys like `'metadata'`, `'mmt_glossaries'`, etc.)
2. `$this->pm->setProjectStructureValue(SomeDao::CONSTANT, $val)` → `$this->projectStructure->propertyName = $val`  
   (use the actual property name for readability, e.g. `->from_api` not `->{ProjectsMetadataDao::FROM_API}`)
3. `$this->pm->callSaveMetadata()` → `$this->service->save($this->projectStructure, $this->features)`

All assertions remain identical — they reference `$this->daoSetCalls` which is unchanged.

Concrete replacements per test method:

**`testSubfilteringHandlersIsAlwaysPersisted`** (line 117):
```php
$this->projectStructure->subfiltering_handlers = '["handler_a"]';
$this->service->save($this->projectStructure, $this->features);
```

**`testAllDaoSetCallsUseCorrectProjectId`** (line 131):
```php
$this->projectStructure->metadata = ['some_key' => 'some_value'];
$this->service->save($this->projectStructure, $this->features);
```

**`testEmptyMetadataOnlyPersistsSubfilteringHandlersAndDefaults`** (line 150):
```php
$this->service->save($this->projectStructure, $this->features);
```

**`testFromApiFlagIsPersistedWhenSet`** (line 168):
```php
$this->projectStructure->from_api = true;
$this->service->save($this->projectStructure, $this->features);
```

**`testFromApiFlagIsNotPersistedWhenFalse`** (line 179):
```php
$this->projectStructure->from_api = false;
$this->service->save($this->projectStructure, $this->features);
```

**`testXliffParametersIsJsonEncodedWhenStruct`** (line 194):
```php
$this->projectStructure->xliff_parameters = $model;
$this->service->save($this->projectStructure, $this->features);
```

**`testXliffParametersIsNotPersistedWhenNotStruct`** (line 223):
```php
$this->projectStructure->xliff_parameters = 'not-a-struct';
$this->service->save($this->projectStructure, $this->features);
```

**`testPretranslate101IsPersistedWhenSet`** (line 240):
```php
$this->projectStructure->pretranslate_101 = '1';
$this->service->save($this->projectStructure, $this->features);
```

**`testMtQeWorkflowParametersAreJsonEncodedWhenEnabled`** (line 254):
```php
$this->projectStructure->metadata = [
    ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED    => true,
    ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS => $params,
];
$this->service->save($this->projectStructure, $this->features);
```

**`testMtQeWorkflowParametersAreNotJsonEncodedWhenDisabled`** (line 270):
```php
$this->projectStructure->metadata = [
    ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED    => false,
    ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS => $params,
];
$this->service->save($this->projectStructure, $this->features);
```

**`testFiltersExtractionParametersAreJsonEncoded`** (line 292):
```php
$this->projectStructure->filters_extraction_parameters = $filterParams;
$this->service->save($this->projectStructure, $this->features);
```

**`testFiltersExtractionParametersNotPersistedWhenEmpty`** (line 308):
```php
$this->projectStructure->filters_extraction_parameters = null;
$this->service->save($this->projectStructure, $this->features);
```

**`testEngineExtraKeysArePersistedFromProjectStructure`** (line 326):
```php
$this->projectStructure->mmt_glossaries = 'glossary_123';
$this->projectStructure->deepl_formality = 'more';
$this->projectStructure->lara_style = 'formal';
$this->service->save($this->projectStructure, $this->features);
```

**`testEngineExtraKeysAreNotPersistedWhenEmpty`** (line 340):
```php
$this->service->save($this->projectStructure, $this->features);
```

**`testAllMetadataOptionsArePersistedViaSet`** (line 358):
```php
$this->projectStructure->metadata = [
    'custom_key_1' => 'value_1',
    'custom_key_2' => 'value_2',
    'custom_key_3' => 'value_3',
];
$this->service->save($this->projectStructure, $this->features);
```

**`testCombinedMetadataScenario`** (line 381):
```php
$this->projectStructure->from_api = true;
$this->projectStructure->pretranslate_101 = '0';
$this->projectStructure->mmt_glossaries = 'gl_abc';
$this->projectStructure->subfiltering_handlers = '[{"name":"handler1"}]';
$this->projectStructure->metadata = ['existing_option' => 'kept'];
$this->service->save($this->projectStructure, $this->features);
```

- [x] **Step 3: Run tests to verify all 16 pass**

Run: `php vendor/bin/phpunit tests/unit/Model/ProjectCreation/SaveMetadataTest.php`
Expected: `OK (16 tests, ...)`

- [x] **Step 4: Commit**

```bash
git add tests/unit/Model/ProjectCreation/SaveMetadataTest.php
git commit -m "Repoint SaveMetadataTest to test ProjectMetadataService directly"
```

### Task 3: Wire `ProjectManager` to delegate to `ProjectMetadataService`

**Files:**
- Modify: `lib/Model/ProjectCreation/ProjectManager.php:288-397`

- [x] **Step 1: Replace `saveMetadata()` body with delegation**

Replace lines 288-397 of `ProjectManager.php` (the docblock, `saveMetadata()`, and `getProjectsMetadataDao()`) with:

```php
    /**
     * Persist project-level metadata options via ProjectMetadataService.
     *
     * @throws Exception
     */
    protected function saveMetadata(): void
    {
        $service = $this->getProjectMetadataService();
        $service->save($this->projectStructure, $this->features);
    }

    /**
     * Get a ProjectMetadataService instance — overridable in tests.
     */
    protected function getProjectMetadataService(): ProjectMetadataService
    {
        return new ProjectMetadataService($this->getProjectsMetadataDao());
    }

    /**
     * Get a ProjectsMetadataDao instance — overridable in tests.
     */
    protected function getProjectsMetadataDao(): ProjectsMetadataDao
    {
        return new ProjectsMetadataDao();
    }
```

Also remove the now-unused imports from `ProjectManager.php` that were only needed by the old `saveMetadata()` body:
- `use Model\Engines\Structs\EngineStruct;`
- `use Utils\Constants\EngineConstants;`
- `use Utils\Engines\MyMemory;`

Before removing these imports, verify they are not used elsewhere in `ProjectManager.php` by searching for `EngineStruct`, `EngineConstants`, and `MyMemory` in the file. If any are used by other methods, keep them.

- [x] **Step 2: Verify syntax**

Run: `php -l lib/Model/ProjectCreation/ProjectManager.php`
Expected: `No syntax errors detected`

- [x] **Step 3: Run SaveMetadataTest to verify delegation works**

Run: `php vendor/bin/phpunit tests/unit/Model/ProjectCreation/SaveMetadataTest.php`
Expected: `OK (16 tests, ...)`

- [x] **Step 4: Run the full test suite to check for regressions**

Run: `php vendor/bin/phpunit`
Expected: All tests pass (same count as before)

- [x] **Step 5: Commit**

```bash
git add lib/Model/ProjectCreation/ProjectManager.php
git commit -m "Replace saveMetadata() body with delegation to ProjectMetadataService"
```

### Task 4: Clean up `TestableProjectManager`

**Files:**
- Modify: `tests/unit/Model/ProjectCreation/TestableProjectManager.php:136-160`

- [x] **Step 1: Remove `callSaveMetadata()` wrapper**

Remove lines 153-160 (the `callSaveMetadata()` method and its docblock):
```php
    /**
     * Public wrapper to invoke the protected saveMetadata().
     * @throws Exception
     */
    public function callSaveMetadata(): void
    {
        $this->saveMetadata();
    }
```

Keep `setProjectsMetadataDao()` and `getProjectsMetadataDao()` override — they are used by `SettersGettersConfigTest` for `saveFeaturesInMetadata()` tests.

- [x] **Step 2: Verify no remaining references to `callSaveMetadata`**

Run: `grep -r 'callSaveMetadata' tests/`
Expected: No matches

- [x] **Step 3: Run full test suite**

Run: `php vendor/bin/phpunit`
Expected: All tests pass

- [x] **Step 4: Commit**

```bash
git add tests/unit/Model/ProjectCreation/TestableProjectManager.php
git commit -m "Remove callSaveMetadata() from TestableProjectManager (no longer needed)"
```

---

## Verification Checklist

- [x] `php vendor/bin/phpunit tests/unit/Model/ProjectCreation/SaveMetadataTest.php` — 16 tests pass
- [x] `./vendor/bin/phpunit --exclude-group ExternalServices` — full suite passes (1757 tests, 0 failures)
- [x] `ProjectManager::saveMetadata()` is ≤5 lines
- [x] `ProjectMetadataService::save()` contains verbatim logic from old `saveMetadata()`
- [x] `callSaveMetadata()` removed from `TestableProjectManager`
- [x] `setProjectsMetadataDao()` retained in `TestableProjectManager`

---

## Next Steps: Remaining `ProjectManager` Extraction Candidates

The following methods/groups remain as candidates for extraction from `ProjectManager`. These are listed in rough priority order (largest impact first):

### 1. File insertion methods → `FileInsertionService` (~220 lines)

The largest remaining chunk. Methods that handle inserting converted files into the project — reading file contents, pushing them into the DB, and mapping insertion errors. High complexity, multiple loops and error-handling branches.

### 2. `finalizeProjectInTransaction()` cleanup (~30 lines)

Transaction wrapper that commits the project after all inserts are done. Small but self-contained — could be simplified or folded into a broader orchestration cleanup.

### 3. Job-file linking → move into existing `JobCreationService` (~36 lines)

Logic that associates files with jobs. Natural fit to move into the already-extracted `JobCreationService` since it operates on the same data structures and is called in the same phase of project creation.
