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
