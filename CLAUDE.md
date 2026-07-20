# Memory-First Protocol
<!-- signet-first-version: 2.0.4 -->

These rules enforce memory-aware behavior for AI coding agents.
If `signet_memory_search` is available, use Signet as the primary memory system.
Otherwise, use your native memory capabilities (MEMORY.md, auto memory, etc.).

## Rules

1. **Search memory before running commands.** Before build/test/deploy/lint commands,
   search for the verified procedure. Use the stored version exactly.
   Skip for: single-line edits; commands the user gave you verbatim this turn.
   Preferred: `signet_memory_search(query, type, limit)`. Fallback: MEMORY.md or native recall.

2. **Search memory at session start.** Look for recent session summaries before touching files.
   Before searching explicitly, check whether memory context is already available in your session.
   If it covers recent summaries and project-relevant notes, skip the explicit search.
   Search explicitly for: continuation requests (daily-log by project scope), project-specific
   recall the available context lacks, or when no memory context is available at all.
   Skip for: self-contained tasks; memory context already covers the current project.

3. **Store conclusions BEFORE composing your answer.** After multi-step investigations, decisions,
   or debugging, store the synthesized conclusion in memory FIRST — before writing the user-facing
   response. Sequence: investigate → synthesize → store → answer. If you are writing a response
   that contains a novel conclusion and have not yet stored it, stop, store it, then continue.
   Search for duplicates first — update, don't duplicate.
   When the conclusion is a user-stated hard constraint or critical procedure, set
   `pinned: true` alongside `importance: 1.0` and tag `critical`.
   Skip for: trivial Q&A under 3 exchanges; single lookups with no novel finding.
   Preferred: `signet_memory_store(content, type, tags, importance, pinned)`. Fallback: native memory.

4. **Write a structured session handoff before ending non-trivial sessions.**
   Store a daily-log with: accomplishments, decisions made, unfinished work, blockers —
   task-oriented synthesis for the next session to resume without re-reading the transcript.
   Skip for: sessions with no investigation/decision/exploration; sessions under 3 exchanges.

5. **When memory returns no results, say so in one sentence and proceed.**
   `Memory returned no results for "<query>". Checking project files.`
   Memory gaps are normal. Do not retry with minor variations or distrust memory on subsequent searches.
   Then store the result so the gap fills over time.

6. **When memory conflicts with current code, trust the code.** Code is the artifact;
   memory is commentary. When they disagree, the artifact wins. Update or remove stale memory.
   Exception: if the memory records a `decision` or `rationale` type, flag the conflict
   to the user before updating — the code may have diverged intentionally.

7. **Use the correct memory type.** `procedural` for commands, `decision` for choices,
   `preference` for user habits. Do not default everything to `fact`.

---
<!-- Do not edit above this line -- managed by signet-first plugin -->
<!-- Add your project-specific rules below -->

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Matecat is an enterprise-level web-based Computer-Assisted Translation (CAT) tool. PHP 8.3+ backend with a React/Vite frontend. Uses Redis for caching, MySQL for persistence, and ActiveMQ for async job processing.

## Architecture

### PHP Autoloading

PSR-4 root is `lib/` with empty namespace prefix. Classes in `lib/Controller/API/App/FooController.php` have namespace `Controller\API\App`. Plugin classes in `plugins/*/lib/` follow the same pattern (e.g., `Features\Translated`).

### Directory Structure

- `lib/Controller/` — HTTP controllers. `Abstracts/` contains the base chain: `KleinController` → `BaseKleinViewController` → concrete controllers
- `lib/Model/` — Domain models, DAOs, structs. DAOs extend `AbstractDao` with `DaoCacheTrait` for Redis caching
- `lib/Utils/` — Engines (MT/TM integrations), async workers, LQA, subfiltering, task runner
- `lib/Plugins/Features/` — Internal features (ReviewExtended, TranslationVersions, SegmentFilter, ProjectCompletion)
- `plugins/` — External plugin submodules (translated, airbnb, uber, aligner, vite). Each has `lib/Features/` with a class extending `BaseFeature`
- `lib/Model/FeaturesBase/` — Event system. `Hook/Event/Filter/` for data-transforming events, `Hook/Event/Run/` for side-effect events. `FeatureSet` dispatches events to registered features

### Engine Hierarchy

`AbstractEngine` → concrete engines (MyMemory, MMT, DeepL, Lara, Google, etc.) → `Results/` response classes → `EnginesFactory`. Widest inheritance tree in the codebase.

### Async Workers

Workers in `lib/Utils/AsyncTasks/Workers/` process queued jobs via ActiveMQ. Key workers: `TMAnalysisWorker`, `GetContributionWorker`, `SetContributionWorker`, `FastAnalysis`, `ProjectCreationWorker`. Daemon entry points in `daemons/`.

### DataAccess Layer

`AbstractDao` → concrete DAOs. `DaoCacheTrait` provides Redis-backed caching with XFetch early recomputation. Structs extend `AbstractDaoObjectStruct` with `ArrayAccessTrait`. `ShapelessConcreteStruct` for untyped data.

## Testing

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

- Tests mirror source structure: `lib/Utils/Foo/Bar.php` → `tests/unit/Utils/Foo/BarTest.php`
- Plugin tests: `plugins/*/tests/`
- Predis `Client` uses `__call` magic for Redis commands — cannot be mocked with PHPUnit `createMock()`. Extend `Client` or mock `RedisHandler` instead

## Static Analysis

```bash
# Full codebase
vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=table

# Single file (must report 0 errors for clean files)
vendor/bin/phpstan analyse path/to/File.php --configuration=phpstan.neon --no-progress --error-format=table
```

### PHPStan Configuration

- Level 8, no baseline — every reported error must be fixed (there is no `phpstan-baseline.neon`)
- `checkTooWideThrowTypesInProtectedAndPublicMethods: true` — must use precise exception types in `@throws`
- `missingCheckedExceptionInThrows: true` — all thrown exceptions must be declared
- `UnknownPropertyException` is unchecked (used by struct `ArrayAccessTrait`)
- When adding exceptions to PHPDoc, prefer `use` imports over FQCN

## Frontend

```bash
yarn watch          # Dev server with HMR
yarn build:dev      # Development build
yarn build:production  # Production build
```

## Git

Do not add Co-Authored-By trailers to commit messages.

Do not add any reference to AI or AI tooling anywhere — commit messages, PR titles/bodies, code,
comments, or docs. This includes footers/signatures (`🤖 Generated with Claude Code`, `Co-Authored-By`
AI trailers), "generated/assisted by" lines, and tool names. The ONLY exception: when the user
explicitly requests it, place it solely in the section designated for that purpose and follow that
section's rules (for example, the PR template's AI Disclosure section).

Follow the `.github/PULL_REQUEST_TEMPLATE.md` AND the `.github/scripts/pr-readiness-check.js` when creating a Pull
Request.

Follow the project `.github/prompts/conventional-commit.prompt.md` for commit message formatting:

- Format: `<emoji> <type>(<scope>): <description>` (see emoji table below)
- Show commit message first, wait for user approval before committing
- Use `git commit -a` (lowercase), never `-A`
- 100 character line limit
- Imperative mood, no capitalization, no period

Valid emoji Type Reference

| Type     | Title                    | Emoji | Description                                                                                            | Example Scopes (non-exaustive)                                |
|----------|--------------------------|-------|--------------------------------------------------------------------------------------------------------|---------------------------------------------------------------|
| build    | Builds                   | 🏗️   | Changes that affect the build system or external dependencies                                          | gulp, broccoli, npm                                           |
| chore    | Chores                   | 🔧    | Other changes that don't modify src or test files                                                      | scripts, config                                               |
| ci       | Continuous Integrations  | 👷    | Changes to our CI configuration files and scripts                                                      | Travis, Circle, BrowserStack, SauceLabs,github actions, husky |
| docs     | Documentation            | 📝    | Documentation only changes                                                                             | README, API                                                   |
| feat     | Features                 | ✨     | A new feature                                                                                          | user, payment, gallery                                        |
| fix      | Bug Fixes                | 🐛    | A bug fix                                                                                              | auth, data                                                    |
| security | Security Fixes           | 🔒    | A change that fixes a vulnerability or hardens against one                                             | auth, idor, xss, injection                                    |
| perf     | Performance Improvements | ⚡️    | A code change that improves performance                                                                | query, cache                                                  |
| refactor | Code Refactoring         | ♻️    | A code change that neither fixes a bug nor adds a feature                                              | utils, helpers                                                |
| revert   | Reverts                  | ⏪️    | Reverts a previous commit                                                                              | query, utils,                                                 |
| style    | Styles                   | 💄    | Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc) | formatting                                                    |
| test     | Tests                    | ✅     | Adding missing tests or correcting existing tests                                                      | unit, e2e                                                     |
| i18n     |                          | 🌐    | Internationalization                                                                                   | locale, translation                                           |

### Creating worktrees

When creating worktrees, those commands MUST be used:

- `cd <project-root> && git branch --show-current`
- `git branch <branch-name> <current-branch-name>`
- `git worktree add ../matecat-<branch-name> <branch-name>`
- `cp composer.phar ../matecat-<branch-name>/composer.phar`
- `cd ../matecat-<branch-name>/ && php composer.phar install`
- `git submodule update --init --recursive`

## API Testing
When testing or calling HTTP/API endpoints, use the bruno-mcp MCP server first.
Workflow: list_collections → list_requests → run_collection.
Do not use curl or direct HTTP calls when Bruno collections exist.
Use `dev` environment for testing.

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