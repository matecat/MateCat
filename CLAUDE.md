# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Matecat is an enterprise-level web-based Computer-Assisted Translation (CAT) tool. PHP 8.3+ backend with a React/Vite frontend. Uses Redis for caching, MySQL for persistence, and ActiveMQ for async job processing.

## Commands

### Tests

```bash
# Full test suite (excludes tests needing external services)
vendor/bin/phpunit --exclude-group=ExternalServices --no-coverage

# Single test file
vendor/bin/phpunit tests/unit/Path/To/TestFile.php --no-coverage

# Single test method
vendor/bin/phpunit --filter testMethodName --no-coverage

# With coverage (requires XDEBUG_MODE=coverage)
XDEBUG_MODE=coverage vendor/bin/phpunit tests/unit/Path/To/TestFile.php --coverage-clover /tmp/coverage.xml
```

### Static Analysis

```bash
# Full codebase with baseline
vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=table

# Single file without baseline (must report 0 errors for clean files)
vendor/bin/phpstan analyse path/to/File.php --configuration=phpstan-no-baseline.neon --no-progress --error-format=table
```

### Frontend

```bash
yarn watch          # Dev server with HMR
yarn build:dev      # Development build
yarn build:production  # Production build
```

## Architecture

### PHP Autoloading

PSR-4 root is `lib/` with empty namespace prefix. Classes in `lib/Controller/API/App/FooController.php` have namespace `Controller\API\App`. Plugin classes in `plugins/*/lib/` follow the same pattern (e.g., `Features\Translated`).

### Directory Structure

- `lib/Controller/` — HTTP controllers. `Abstracts/` contains the base chain: `KleinController` → `BaseKleinViewController` → concrete controllers
- `lib/Model/` — Domain models, DAOs, structs. DAOs extend `AbstractDao` with `DaoCacheTrait` for Redis caching
- `lib/Utils/` — Engines (MT/TM integrations), async workers, LQA, subfiltering, task runner
- `lib/Plugins/Features/` — Internal features (ReviewExtended, TranslationVersions, SegmentFilter, ProjectCompletion)
- `plugins/` — External plugin submodules (translated, airbnb, uber). Each has `lib/Features/` with a class extending `BaseFeature`
- `lib/Model/FeaturesBase/` — Event system. `Hook/Event/Filter/` for data-transforming events, `Hook/Event/Run/` for side-effect events. `FeatureSet` dispatches events to registered features

### Engine Hierarchy

`AbstractEngine` → concrete engines (MyMemory, MMT, DeepL, Lara, Google, etc.) → `Results/` response classes → `EnginesFactory`. Widest inheritance tree in the codebase.

### Async Workers

Workers in `lib/Utils/AsyncTasks/Workers/` process queued jobs via ActiveMQ. Key workers: `TMAnalysisWorker`, `GetContributionWorker`, `SetContributionWorker`, `FastAnalysis`, `ProjectCreationWorker`. Daemon entry points in `daemons/`.

### DataAccess Layer

`AbstractDao` → concrete DAOs. `DaoCacheTrait` provides Redis-backed caching with XFetch early recomputation. Structs extend `AbstractDaoObjectStruct` with `ArrayAccessTrait`. `ShapelessConcreteStruct` for untyped data.

## PHPStan Configuration

- Level 8 with `phpstan-baseline.neon` for known errors
- `phpstan-no-baseline.neon` exists for verifying individual files are fully clean
- `checkTooWideThrowTypesInProtectedAndPublicMethods: true` — must use precise exception types in `@throws`
- `missingCheckedExceptionInThrows: true` — all thrown exceptions must be declared
- `UnknownPropertyException` is unchecked (used by struct `ArrayAccessTrait`)

## Git

Do not add Co-Authored-By trailers to commit messages.

Follow the project `.github/prompts/conventional-commit.prompt.md` for commit message formatting:
- Format: `<emoji> <type>(<scope>): <description>`
- Show commit message first, wait for user approval before committing
- Use `git commit -a` (lowercase), never `-A`
- 100 character line limit
- Imperative mood, no capitalization, no period

## MCP Tools: code-review-graph

**This project has a knowledge graph. Use code-review-graph MCP tools BEFORE Grep/Glob/Read to explore the codebase.** The graph is faster and gives structural context (callers, dependents, test coverage).

| Tool                        | Use when                                            |
|-----------------------------|-----------------------------------------------------|
| `detect_changes`            | Reviewing code changes — risk-scored analysis       |
| `get_review_context`        | Need source snippets for review — token-efficient   |
| `get_impact_radius`         | Understanding blast radius of a change              |
| `get_affected_flows`        | Finding which execution paths are impacted          |
| `query_graph`               | Tracing callers, callees, imports, tests            |
| `semantic_search_nodes`     | Finding functions/classes by name or keyword        |
| `get_architecture_overview` | Understanding high-level codebase structure         |

## Key Conventions

- When adding exceptions to PHPDoc, prefer `use` imports over FQCN
- Tests mirror source structure: `lib/Utils/Foo/Bar.php` → `tests/unit/Utils/Foo/BarTest.php`
- Plugin tests: `plugins/*/tests/`
- Predis `Client` uses `__call` magic for Redis commands — cannot be mocked with PHPUnit `createMock()`. Extend `Client` or mock `RedisHandler` instead
