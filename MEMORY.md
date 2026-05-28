# Memory-First Protocol
<!-- signet-first-version: 2.0.4 -->

Rules enforce memory-aware behavior for AI coding agents.
If `signet_memory_search` available, use Signet as primary memory system.
Otherwise, use native memory capabilities (MEMORY.md, auto memory, etc.).

## Rules

1. **Search memory before running commands.** Before build/test/deploy/lint commands,
   search for verified procedure. Use stored version exactly.
   Skip: single-line edits; commands user gave verbatim this turn.
   Preferred: `signet_memory_search(query, type, limit)`. Fallback: MEMORY.md or native recall.

2. **Search memory at session start.** Look for recent session summaries before touching files.
   Check whether memory context already available in session first.
   If covers recent summaries and project-relevant notes, skip explicit search.
   Search explicitly for: continuation requests (daily-log by project scope), project-specific
   recall available context lacks, or when no memory context available.
   Skip: self-contained tasks; memory context already covers current project.

3. **Store conclusions BEFORE composing answer.** After multi-step investigations, decisions,
   or debugging, store synthesized conclusion in memory FIRST — before user-facing
   response. Sequence: investigate → synthesize → store → answer. If writing response
   with novel conclusion not yet stored, stop, store it, then continue.
   Search duplicates first — update, don't duplicate.
   When conclusion is user-stated hard constraint or critical procedure, set
   `pinned: true` alongside `importance: 1.0` and tag `critical`.
   Skip: trivial Q&A under 3 exchanges; single lookups with no novel finding.
   Preferred: `signet_memory_store(content, type, tags, importance, pinned)`. Fallback: native memory.

4. **Write structured session handoff before ending non-trivial sessions.**
   Store daily-log with: accomplishments, decisions made, unfinished work, blockers —
   task-oriented synthesis for next session to resume without re-reading transcript.
   Skip: sessions with no investigation/decision/exploration; sessions under 3 exchanges.

5. **When memory returns no results, say so in one sentence and proceed.**
   `Memory returned no results for "<query>". Checking project files.`
   Memory gaps normal. Do not retry with minor variations or distrust memory on subsequent searches.
   Store result so gap fills over time.

6. **When memory conflicts with current code, trust code.** Code is artifact;
   memory is commentary. When they disagree, artifact wins. Update or remove stale memory.
   Exception: if memory records `decision` or `rationale` type, flag conflict
   to user before updating — code may have diverged intentionally.

7. **Use correct memory type.** `procedural` for commands, `decision` for choices,
   `preference` for user habits. Do not default everything to `fact`.

---
## Git

Do not add Co-Authored-By trailers to commit messages.

Follow project .github/prompts/conventional-commit.prompt.md for commit message formatting.

MANDATORY: READ IT. When you think you know, it's the moment you are failing.

## MCP Tools: code-review-graph

**IMPORTANT: Project has knowledge graph. ALWAYS use
code-review-graph MCP tools BEFORE Grep/Glob/Read to explore
codebase.** Graph faster, cheaper (fewer tokens), gives
structural context (callers, dependents, test coverage) file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when graph doesn't cover what you need.

### Key Tools

| Tool                        | Use when                                               |
|-----------------------------|--------------------------------------------------------|
| `detect_changes`            | Reviewing code changes — gives risk-scored analysis    |
| `get_review_context`        | Need source snippets for review — token-efficient      |
| `get_impact_radius`         | Understanding blast radius of a change                 |
| `get_affected_flows`        | Finding which execution paths are impacted             |
| `query_graph`               | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes`     | Finding functions/classes by name or keyword           |
| `get_architecture_overview` | Understanding high-level codebase structure            |
| `refactor_tool`             | Planning renames, finding dead code                    |

### Workflow

1. Graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.