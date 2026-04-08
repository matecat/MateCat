# FeatureSet Hooks — What Changed

> **Date:** 2026-04-08 | **Branch:** `context-review` | **Author:** Domenico Lupinetti

---

## TL;DR

- **20 unused hooks removed** — they had zero handler implementations across all plugins
- **5 hooks renamed** from `snake_case` to `camelCase`
- All remaining **44 hooks are now camelCase**
- **5 vendor SubFiltering hooks** (`fromLayer*`) are unchanged

---

## Removed Hooks (20)

These hooks were dispatched but **no plugin ever handled them**. The dispatch calls and `@method` annotations have been
deleted.

**Filter hooks removed (12):**

`filterCreationStatus`, `overrideConversionResult`, `filterGlobalWarnings`, `filterSegmentWarnings`,
`filterSetTranslationResult`, `prepareAllNotes`, `processExtractedJsonNotes`, `filterIsChunkCompletionUndoable`,
`doNotManageAlternativeTranslations`, `filter_team_for_project_creation`, `filterProjectDependencies`,
`filterFeaturesMerged`

**Run hooks removed (8):**

`project_password_changed`, `processZIPDownloadPreview`, `checkSplitAccess`, `afterTMAnalysisCloseProject`,
`fastAnalysisComplete`, `bootstrapCompleted`, `postProjectCommit`, `handleTUContextGroups`

### If you had a handler for any of these

You don't — we verified across uber, airbnb, translated, aligner, and all internal features. If you see a method with
one of these names in your code, it's dead code — delete it.

---

## Renamed Hooks (5)

| Old Name (snsake_case)                   | New Name (camelCase)                |
| ---------------------------------------- | ----------------------------------- |
| `filter_job_password_to_review_password` | `filterJobPasswordToReviewPassword` |
| `job_password_changed`                   | `jobPasswordChanged`                |
| `review_password_changed`                | `reviewPasswordChanged`             |
| `project_completion_event_saved`         | `projectCompletionEventSaved`       |
| `alter_chunk_review_struct`              | `alterChunkReviewStruct`            |

### What to do

Rename your handler method to match. Example:

```php
// Before:
public function job_password_changed(JobStruct $job, string $oldPassword): void

// After:
public function jobPasswordChanged(JobStruct $job, string $oldPassword): void
```

Already done in: `AbstractRevisionFeature`, `ProjectCompletion`, dispatch sites in `ChangePasswordController`,
`TranslatorsModel`, `CompletionEventController`, `EventModel`, `ProjectCompletionStatusModel`.

---

## Remaining Active Hooks (44)

All hooks listed in `FeatureSet.php` `@method` annotations are active and have at least one handler. Full list:

**Filter (29):** `isAnInternalUser`, `outsourceAvailableInfo`, `projectUrls`, `filterCreateProjectFeatures`,
`encodeInstructions`, `decodeInstructions`, `filterActivityLogEntry`, `filterContributionStructOnSetTranslation`,
`filterContributionStructOnMTSet`, `filterGetSegmentsResult`, `prepareNotesForRendering`,
`filterJobPasswordToReviewPassword`, `filterRevisionChangeNotificationList`, `filterMyMemoryGetParameters`,
`characterLengthCount`, `injectExcludedTagsInQa`, `checkTagMismatch`, `checkTagPositions`,
`analysisBeforeMTGetContribution`, `filterPayableRates`, `wordCount`, `populatePreTranslations`,
`sanitizeOriginalDataMap`, `correctTagErrors`, `appendFieldToAnalysisObject`, `handleJsonNotesBeforeInsert`,
`rewriteContributionContexts`, `appendInitialTemplateVars`, `fromLayer0ToLayer1` (vendor)

**Run (15):** `setTranslationCommitted`, `postAddSegmentTranslation`, `chunkReviewUpdated`, `jobPasswordChanged`,
`reviewPasswordChanged`, `projectCompletionEventSaved`, `tmAnalysisDisabled`, `postJobSplitted`, `postJobMerged`,
`validateJobCreation`, `validateProjectCreation`, `beforeProjectCreation`, `postProjectCreate`,
`filterProjectNameModified`, `alterChunkReviewStruct`

---

## Real Examples From the Codebase

**Filter hook — call site** (`JobCreationService.php`):

```php
// Before:
$rates = $this->featureSet->filter('filterPayableRates', $rates, $source, $target);

// After:
$event = $this->featureSet->dispatchFilter(new FilterPayableRatesEvent($rates, $source, $target));
$rates = $event->getRates();
```

**Filter hook — handler** (`Uber.php`):

```php
// Before:
public function filterPayableRates(array $rates, string $source, string $target): array {
    $rates['key'] = 'modified';
    return $rates;
}

// After:
public function filterPayableRates(FilterPayableRatesEvent $event): void {
    $rates = $event->getRates();
    $rates['key'] = 'modified';
    $event->setRates($rates);
}
```

**Run hook — call site** (`ChangePasswordController.php`):

```php
// Before:
$this->featureSet->run('jobPasswordChanged', $jStruct, $actual_pwd);

// After:
$this->featureSet->dispatchRun(new JobPasswordChangedEvent($jStruct, $actual_pwd));
```

**Run hook — handler** (`AbstractRevisionFeature.php`):

```php
// Before:
public function jobPasswordChanged(JobStruct $job, string $old_password): void { ... }

// After:
public function jobPasswordChanged(JobPasswordChangedEvent $event): void {
    $job = $event->job;
    $old_password = $event->oldPassword;
    ...
}
```

---

## How to Create a New Hook

New hooks use **typed Event DTOs**. Follow this process:

### 1. Create the event class

Pick the right base class:

- **`FilterEvent`** — handler transforms data (mutable subject)
- **`RunEvent`** — handler performs side effects (fire-and-forget)

Add `@see` pointing to where the hook is dispatched — this enables **one-click IDE navigation** from the event to its caller(s).

```php
<?php
// lib/Model/FeaturesBase/Hook/Event/Filter/MyNewHookEvent.php
namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;

/**
 * @see \Controller\API\App\SomeController::someMethod() — dispatch site
 */
final class MyNewHookEvent extends FilterEvent
{
    public static function hookName(): string { return 'myNewHook'; }

    public function __construct(
        private array $data,                    // mutable — the filtered subject
        private readonly string $context,       // readonly — observation context
    ) {}

    public function getData(): array { return $this->data; }
    public function setData(array $data): void { $this->data = $data; }
    public function getContext(): string { return $this->context; }
}
```

For run hooks — same pattern, readonly properties only:

```php
<?php
// lib/Model/FeaturesBase/Hook/Event/Run/SomethingHappenedEvent.php
namespace Model\FeaturesBase\Hook\Event\Run;

use Model\FeaturesBase\Hook\RunEvent;

/**
 * @see \Controller\API\App\SomeController::someMethod() — dispatch site
 */
final class SomethingHappenedEvent extends RunEvent
{
    public static function hookName(): string { return 'somethingHappened'; }

    public function __construct(
        public readonly int $entityId,
        public readonly string $action,
    ) {}
}
```

### 2. Dispatch from your code

```php
// Filter — transforms data:
$event = $this->featureSet->dispatchFilter(new MyNewHookEvent($data, $context));
$data = $event->getData(); // get the (possibly modified) result

// Run — fire-and-forget:
$this->featureSet->dispatchRun(new SomethingHappenedEvent($entityId, 'created'));
```

### 3. Handle in your plugin

Add a method on your `BaseFeature` subclass. Method name **must match `hookName()`**.

```php
// plugins/uber/lib/Features/Uber.php
public function myNewHook(MyNewHookEvent $event): void
{
    $data = $event->getData();
    $data['extra'] = 'added by Uber';
    $event->setData($data);
}
```

### IDE Navigation

Every event class has `@see` annotations pointing to its dispatch site(s). This gives you **full traceability** without searching:

```
Event class (@see) → Dispatch call site (Find Usages on Event) → Handler methods
```

- **From event → caller:** Ctrl+click the `@see` reference
- **From caller → handler:** "Find Usages" (Alt+F7) on the Event class → shows handler type-hints
- **From handler → caller:** "Find Usages" on the Event class in the parameter → shows `new Event(...)` at dispatch

Real example from the codebase:

```php
/**
 * @see \Model\ProjectCreation\JobCreationService::getPayableRates() — dispatch site
 */
final class FilterPayableRatesEvent extends FilterEvent { ... }

// JobCreationService.php — Ctrl+click @see lands here:
$event = $this->features->dispatchFilter(new FilterPayableRatesEvent($rates, $source, $target));

// Uber.php — "Find Usages" on FilterPayableRatesEvent shows this:
public function filterPayableRates(FilterPayableRatesEvent $event): void { ... }
```

### Rules

| Rule                           | Details                                                                                                            |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------ |
| **Naming**                     | camelCase only (PSR-1). `hookName()` return value = handler method name                                            |
| **Filter events**              | First constructor arg is the mutable subject (gets a setter). Remaining args are `readonly` context (getters only) |
| **Run events**                 | All constructor args are `readonly`                                                                                |
| **Handler signature**          | Single event parameter, `void` return. Mutate the event, don't return a value                                      |
| **One event class = one hook** | No reuse of event classes across different hooks                                                                   |
| **Event classes are `final`**  | Don't extend concrete events                                                                                       |
| **Location**                   | `lib/Model/FeaturesBase/Hook/Event/Filter/` or `.../Run/`                                                          |

### Checklist for a new hook

- [ ] Event class created in the right directory
- [ ] `hookName()` returns camelCase name
- [ ] `@see` annotation points to dispatch call site(s)
- [ ] Dispatch call uses `dispatchFilter()` or `dispatchRun()`
- [ ] Handler method name matches `hookName()` exactly
- [ ] Handler accepts the event object as sole parameter, returns `void`
- [ ] PHPStan passes at level 8

### Why `filter()` / `run()` still exist

The old string-based methods cannot be removed because `vendor/matecat/subfiltering` depends on them. That package declares `FeatureSetInterface::filter(string $method, mixed $filterable): mixed` and dispatches 5 pipeline hooks through it:

`fromLayer0ToLayer1`, `fromLayer1ToLayer2`, `fromLayer2ToLayer1`, `fromRawXliffToLayer0`, `fromLayer0ToRawXliff`

These live in vendor code we don't modify directly. Once the subfiltering package is updated to use typed dispatch, `filter()` and `run()` can be removed. Until then, they stay — but **do not use them for new hooks**.
