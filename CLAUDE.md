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

### Cache and transactions

`DaoCacheTrait` follows the transaction of the object using it, declared by `_cacheTransactionScope()`.
`AbstractDao` returns its own injected `IDatabase`; `Pager`, `UserStateStore` and
`SessionTokenStoreHandler` return null, because they do not write through a transaction and a token revocation must
never wait behind a commit.

- A read taken inside an open transaction is **not** written to cache — it is not public yet, and a rollback would leave
  a row that never existed readable for the whole TTL.
- An eviction issued inside an open transaction is **queued** on `IDatabase::onCommit()` and runs after the commit.
  Evicting before the commit is worse than not evicting: another connection misses the cache, reads the pre-commit row
  and caches it again, behind the eviction that just ran.

Callers do not schedule any of this. Do not wrap `destroyCacheXxx()` in `onCommit()` by hand.
`onCommit($callback, critical: true)` re-throws a failure once the rest of the queue has run — use it only where a
silent failure is a security problem, such as a credential sweep, not for a cache bust.

A transaction is opened only through `IDatabase::transaction(callable)`. The outermost scope owns it; a scope entered
inside an open transaction is a guest that opens and closes nothing, so it cannot commit its caller's work early. Any
throw aborts the whole tree — including one the caller catches, because the failing scope marks the transaction unable
to commit and `Database::commit()`
refuses it. Work deferred with `onCommit()` drains once, after the single real commit, and is discarded on rollback.

`begin()`, `commit()` and `rollback()` are not on `IDatabase`, so code holding the interface — which is all of it —
cannot reach them. They stay public on `Database` for the test harness, which opens a fixture scope in `setUp()` and
rolls it back in `tearDown()`. Those three and
`PDO::beginTransaction()`/`commit()`/`rollBack()` are also reported by a PHPStan rule
(`NoManualTransactionControlRule`), which covers the receivers the interface cannot: a `Database`, a subclass of it, and
a bare PDO handle out of `getConnection()`. A raw commit leaves the deferral queue undrained and the next `begin()`
discards it. Do not wrap transaction control in a helper class or a trait either: two such facades already had to be
removed, and a type-based rule cannot see through them.

`onCommit()` is for code that does not own the scope. A DAO write cannot tell whether it is the outermost scope or
nested five calls deep, so it defers and lets the owner's commit drain the queue. When you do own the scope, put the
statement after `transaction()` returns instead — same effect, and you get the exception if it fails, where a queued
callback only logs it.

### Cache eviction method names

A cache key is `md5(query . bind params)`. The query is half the address, so an eviction is named
after the **read whose entry it deletes**, never after the parameters alone: two reads can bind the
same values and differ only in their SQL, and a name built from the parameters cannot tell them
apart. Take the read's name and drop its `find`/`get` prefix. Declare the eviction next to the read.

```
findByProjectId()               ->  destroyCacheByProjectId()
findUserTeams()                 ->  destroyCacheUserTeams()
findChunkReviewsForSourcePage() ->  destroyCacheChunkReviewsForSourcePage()
isTOrR1OrR2()                   ->  destroyCacheIsTOrR1OrR2()
```

Three tiers, and the name says which one you are looking at:

| tier    | name                        | takes           | clears              | visibility                          |
|---------|-----------------------------|-----------------|---------------------|-------------------------------------|
| door    | `destroyCache`              | the struct      | every address       | public, one per DAO                 |
| fan-out | `destroyCaches<Address>`    | key components  | one address, N reads| private unless a caller is named     |
| leaf    | `destroyCache<ReadName>`    | bind values     | one read            | private by default                  |

The door is the only tier that takes a struct, which is what keeps it apart from the leaves at a
call site — outside code names the entity it already holds and never a key it cannot see. A fan-out
is plural because it is not a leaf; make it public only with a docblock naming the caller and the
value that caller holds which the struct does not (a retired password, an old email). Leaves return
`bool` from `_destroyObjectCache()`; a door returns `void`, because a fan-out's aggregate of
per-key booleans tells a caller nothing it can act on.

Two shapes are wrong and neither should be added: a `For` before the read name, which carries no
meaning the tier does not already give, and a leaf keeping the `find` its read was named with.

Not every DAO can have a door. It is expressible only when entity identity determines the whole
key: both `MetadataDao` classes bind a caller-supplied `key` column, so their evictions stay narrow
and public.

### Database character set

The character set of the database, its tables and the connection is infrastructure. Never set, change or work around it
from PHP.

MateCat is open source and every installation owns its own schema. `INSTALL/matecat.sql` ships
`utf8mb4`, which is what a fresh self-hosted install gets. Older installations are `utf8mb3`, and
`tests/inc/unittest_matecat_local.sql` matches those. Several tables — `project_templates`,
`filters_config_templates`, `xliff_config_templates`, `payable_rate_templates` — are created by migrations with no
charset clause at all and inherit the database default; the two `mt_qe_*` `name`
columns are explicitly `CHARACTER SET latin1`. All of these are legitimate. None is drift to be reconciled.

PHP knows nothing about any of it, and that is correct — it is the property that lets one codebase run on every
installation. Do not treat it as a gap to close: **never read, assume or encode a storage charset in application code.**
A rule written for three-byte storage is needlessly strict where four bytes are available; one written for four
truncates silently where they are not. Neither belongs in the code.

`Database::getConnection()` opens every connection with `SET NAMES utf8` (utf8mb3), and does it twice — once as
`MYSQL_ATTR_INIT_COMMAND` and once as a bare `exec()`. `Model\Conversion\Filters`
does the same on its own connection. Those lines are not a bug to tidy. Changing the connection charset alone, against
columns that are narrower, corrupts or truncates across every query with no error and nothing shown to the user.
Widening a column is an `ALTER TABLE` per column, plus the connection, plus the index key widths that follow from four
bytes per character — coordinated, in a migration window, owned by whoever runs that installation. Never a code edit,
never a rider on a feature PR.

Application code adapts to what the storage can hold rather than changing it.
`UserSuppliedName::assertNoAstral()` is the pattern: refuse on the narrower assumption where the user can see the 400,
strip where a throw would break the request (the OAuth callback, project creation), and explain the limit without naming
a charset.

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

## User-Facing Copy: Sentence Case

Every English string the app shows a user is **sentence case** — a capital on the first word
only. This covers UI labels, buttons, headings and page titles, API/AJAX error payloads,
exception messages, and email subjects and bodies.

Keep existing capitals for:

- **Proper nouns** — Matecat, Lara, DeepL, MyMemory, ModernMT, Google, Amazon S3, Intento,
  Apertium, AltLang, SmartMATE, XTRF. In prose the brand is **Matecat**; `MateCat` belongs
  only to identifiers such as `MateCatFilter`.
- **Acronyms** — API, ID, UID, URL, TM, MT, QA, QE, CSV, TMX, XML, JSON, XLIFF, DB, ZIP,
  MIME, JWT, SQL, HTTP, IP.
- **Code identifiers quoted in a message** — class, method and parameter names
  (`TeamStruct`, `getInstance()`, `id_job`, `batchSize`). A message that *opens* with one keeps
  its lowercase: `'id_job not valid'`, not `'Id_job not valid'`.

```
Volume analysis            not   Volume Analysis
Invalid upload token.      not   Invalid Upload Token.
Not authorized             not   Not Authorized
Wrong ID project provided  not   Wrong Id project provided
ZIP error:                 not   Zip error:
is mandatory               not   is MANDATORY
```

**Exception messages are user-facing.** `router.php` maps every exception class to an HTTP
status and serializes it through `View\API\Commons\Error`, which copies `getMessage()`
straight into `errors[0].message` of the JSON response — with no `PRINT_ERRORS` guard, and
including the fallback 500 branch.

**Do not re-case** — these are contracts or protocol, not copy:

- Data exports: the QA-report CSV headers in `lib/View/API/V2/Json/SegmentTranslationIssue.php`
  and the plain-text analysis report in `lib/Model/Analysis/XTRFStatus.php`.
- API response keys: the `$SUPPORTED_FILE_TYPES` group names in `lib/Utils/Registry/AppConfig.php`
  are emitted verbatim by `lib/Controller/API/V2/SupportedFilesController.php`.
- HTTP reason phrases (`header('HTTP/1.1 400 Bad Request')`) and User-Agent strings.
- Strings compared against a third-party API's own responses (see `lib/Utils/TMS/TMSService.php`,
  `lib/Controller/API/GDrive/GDriveController.php`).
- Internal `logger->debug()/error()` output and timing array keys.

When you add or change one of these strings:

- **Grep `tests/` for the old text before you finish.** Many tests pin exact messages, and
  `expectExceptionMessage()` / `assertStringContainsString()` match **substrings** — a
  full-literal grep misses an assertion on a fragment like `'Zip error'`. DB-backed tests
  (`*RealSqlTest.php` and others) only execute in CI, because host PHP has no `pdo_mysql`;
  a green local run does **not** mean they pass.
- **Edit `lib/View/templates/_*.html`, never `lib/View/*.html`** — the latter are git-ignored
  build artifacts regenerated from the templates by the Vite `htmlTemplatePlugin`.
- **Check for a CSS override.** `text-transform: capitalize` renders Title Case whatever the
  string says; that rule used to sit on `h1` and `.btn a` in `lib/View/Emails/skeleton.html`.
- **Grep beyond `throw new`.** Exceptions passed as an argument
  (`someCall($x, new DomainException("…"))`) span lines, and single mid-sentence capitals
  (`and Teams`, `the Assignee`) have no adjacent-capital pair to match on.

## Git

Do not add Co-Authored-By trailers to commit messages.

Do not add any reference to AI or AI tooling anywhere — commit messages, PR titles/bodies, code,
comments, or docs. This includes footers/signatures (`🤖 Generated with Claude Code`, `Co-Authored-By`
AI trailers), "generated/assisted by" lines, and tool names.

ONLY place references to AI it in the section designated for that purpose in the PR template's AI Disclosure section.

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
| build    | Builds                   | 🏗️    | Changes that affect the build system or external dependencies                                          | gulp, broccoli, npm                                           |
| chore    | Chores                   | 🔧    | Other changes that don't modify src or test files                                                      | scripts, config                                               |
| ci       | Continuous Integrations  | 👷    | Changes to our CI configuration files and scripts                                                      | Travis, Circle, BrowserStack, SauceLabs,github actions, husky |
| docs     | Documentation            | 📝    | Documentation only changes                                                                             | README, API                                                   |
| feat     | Features                 | ✨    | A new feature                                                                                          | user, payment, gallery                                        |
| fix      | Bug Fixes                | 🐛    | A bug fix                                                                                              | auth, data                                                    |
| security | Security Fixes           | 🔒    | A change that fixes a vulnerability or hardens against one                                             | auth, idor, xss, injection                                    |
| perf     | Performance Improvements | ⚡️    | A code change that improves performance                                                                | query, cache                                                  |
| refactor | Code Refactoring         | ♻️    | A code change that neither fixes a bug nor adds a feature                                              | utils, helpers                                                |
| revert   | Reverts                  | ⏪️    | Reverts a previous commit                                                                              | query, utils,                                                 |
| style    | Styles                   | 💄    | Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc) | formatting                                                    |
| test     | Tests                    | ✅    | Adding missing tests or correcting existing tests                                                      | unit, e2e                                                     |
| i18n     |                          | 🌐    | Internationalization                                                                                   | locale, translation                                           |
| merge    | Merges                   | 🔀    | Merges a branch into another; the emoji is optional here, since git and the forge write their own      | develop, master                                               |

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

<!-- code-review-graph MCP tools -->
## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes_tool` or `query_graph_tool` instead of Grep
- **Understanding impact**: `get_impact_radius_tool` instead of manually tracing imports
- **Code review**: `detect_changes_tool` + `get_review_context_tool` instead of reading entire files
- **Finding relationships**: `query_graph_tool` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview_tool` + `list_communities_tool`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

### Key Tools

| Tool | Use when |
| ------ | ---------- |
| `detect_changes_tool` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context_tool` | Need source snippets for review — token-efficient |
| `get_impact_radius_tool` | Understanding blast radius of a change |
| `get_affected_flows_tool` | Finding which execution paths are impacted |
| `query_graph_tool` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes_tool` | Finding functions/classes by name or keyword |
| `get_architecture_overview_tool` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

### Workflow

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes_tool` for code review.
3. Use `get_affected_flows_tool` to understand impact.
4. Use `query_graph_tool` pattern="tests_for" to check coverage.
