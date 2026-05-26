# Memory-First Protocol
<!-- signet-first-version: 1.2.0 -->

These rules enforce memory-aware behavior for AI coding agents.
If `signet_memory_search` is available, use Signet as the primary memory system.
Otherwise, use your native memory capabilities (MEMORY.md, auto memory, etc.).
The principles below apply regardless of which memory backend is available.

## 1. Search Memory Before Acting

- ALWAYS search memory BEFORE reading files, firing agents, or running commands.
- This includes session start: search for recent session summaries to orient before touching files.
- **Certainty is the trigger, not doubt.** If you think "I already know this," that is EXACTLY when you must search.
- Preferred: `signet_memory_search(query, type, limit)` when available.
- Fallback: read MEMORY.md or use native recall.

## 2. Pre-Action Gate

- Before running ANY build, test, lint, deploy, or project-specific command, search memory for the verified procedure.
- Use the stored version exactly. Do not add/remove flags from recall.
- If not found in memory, check project files (Makefile, package.json, CI config). If still not found, ask the user.
- NEVER skip to execution because you "remember" the command.

## 3. Fallback Warning

When memory search returns insufficient results and you fall back to reading files, inform the user:

```
MEMORY FALLBACK: Memory returned insufficient results for "<query>".
Falling back to project files. Consider storing the result for future sessions.
```

This keeps the user aware of memory gaps so they can be filled over time.

## 4. Store Conclusions

- After any investigation, analysis, decision, debugging finding, or discovery, store the conclusion in memory.
- Store synthesized conclusions, NOT raw data or transcripts.
- Preferred: `signet_memory_store(content, type, tags, importance)` when available.
- Fallback: use your native memory system.
- Always check for duplicates before storing. Update existing entries rather than creating contradictions.

## 5. Session Lifecycle

- **Start**: Search memory for session summaries and recent context before touching files.
- **End**: Store a summary of what was accomplished, decisions made, and unfinished work.

---
<!-- Do not edit above this line -- managed by signet-first plugin -->
<!-- Add your project-specific rules below -->

## Git

Do not add Co-Authored-By trailers to commit messages.

<!-- code-review-graph MCP tools -->

## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

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

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.
