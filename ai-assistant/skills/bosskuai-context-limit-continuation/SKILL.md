---
name: bosskuai-context-limit-continuation
description: Use this when a task risks hitting model context, token limits, or tight usage/quota mid-process. Stop cleanly, write progress into ai-assistant/memory/active-continuation.md, pair with bosskuai-ai-model-selection to recommend which model should complete the remaining work, and tell the user explicitly to open a fresh chat/session with that model. Also contains token-budget guidance for keeping agent sessions efficient.
---

# BosskuAI Context-Limit Continuation

Use this skill when a task is long enough that the current model session may run out of context budget, hit token limits, face usage or quota pressure, or otherwise risk being cut off before completion.

**Not covered here:** training or fine-tuning ML models. For choosing *which* commercial/chat model to use, load **`bosskuai-ai-model-selection`** in the same pass.

## Trigger thresholds — when to activate this skill

Activate this skill (stop and hand off) when **any** of the following are true:

| Signal | Threshold | Action |
|--------|-----------|--------|
| Files read this session | > 8 files | Trigger handoff check |
| Total lines read this session | > 1,500 lines | Trigger handoff check |
| Conversation turns | > 15 back-and-forth turns | Trigger handoff check |
| Remaining task phases | ≥ 3 phases still to go | Trigger handoff check |
| Single file size | > 400 lines before reading | Read targeted range only; warn if full read needed |

**Pre-task budget estimation (mandatory before any multi-file task):**
Before starting a task that touches ≥ 3 files or has ≥ 3 phases, estimate:
1. Count files to read × ~200 lines average = estimated lines consumed
2. Add skills to load × ~150 lines average
3. Add expected output size (~200–500 lines for meaningful tasks)
4. If total > 1,200 lines: warn the user upfront, propose a phased plan, and confirm scope before starting

If the estimate is tight (1,000–1,500 lines), explicitly note this at the start of the task and plan a clean stopping point.

## Continuation workflow

1. Check trigger thresholds above — activate this skill if any threshold is met or the pre-task budget estimate is tight.
2. **Before stopping to hand off: check if remaining work has ≥ 2 independent workstreams.** If so, consider delegating them in parallel using `bosskuai-subagent-delegation` instead — parallel subagents can complete independent work without consuming the main session context. Only stop and hand off if the remaining work is fundamentally serial or subagents cannot complete it independently.
3. Stop before truncation instead of letting the work end abruptly.
4. Summarize what has already been completed, what remains, and any key decisions already made.
5. **Update memory for the handoff** — Fill or overwrite `../../memory/active-continuation.md` with goal, done, remaining, key files, risks, and a **paste block** for the next session. This is the durable bridge other tools can read; clear that file when the task is fully done.
6. **Recommend the next model** — Apply **`bosskuai-ai-model-selection`** for the *remaining* work (not the whole original task if the phase changed). Give a **primary** and **fallback** model by concrete name when the tool exposes them, with one-line tradeoffs (reasoning vs speed vs cost vs context).
7. **Tell the user clearly** to **start a new chat or session** (or switch model in-product) using the **recommended model**, paste the continuation block, and point them at `active-continuation.md` if their workspace uses shared memory.
8. Provide a compact continuation state in the reply so the user can act even before opening the memory file.
9. Ask the user to retry, continue, or start a fresh prompt so the work can resume cleanly.
10. If files were changed, name them clearly so the next turn can verify the current state quickly.

## Continuation output

- what is completed
- what remains
- key files or artifacts touched
- important risks or open questions
- **recommended model(s)** for finishing the remainder (primary + fallback)
- explicit **user instruction**: new session/chat + which model + paste block
- confirmation that **`active-continuation.md`** was updated (or why skipping is justified)
- short retry / continue instruction for the user

---

## Token-budget guidance for efficient agent sessions

Apply these practices to keep sessions lean and avoid premature context exhaustion.

### Prompt hygiene

- **Load skills on demand** — only read a skill file when the task explicitly calls for it. Do not bulk-load all skills at the start of a session.
- **Read targeted file ranges** — when a file is large, read only the relevant section (use line offsets) rather than the entire file.
- **Prefer grep over full reads** — search for symbols, patterns, or function names before reading the surrounding context.
- **Summarize before appending** — when prior research output is large, summarize the relevant findings into a compact note before continuing rather than carrying the full raw output forward.

### Context preservation

- **Compact handoff state** — before stopping on a long task, write a 5–10 line summary of: goal, progress, next step, key file paths, open risks. This is the only content the next session needs; it avoids re-reading everything.
- **Avoid re-reading unchanged files** — if a file was read earlier in the session and has not changed, refer to your earlier notes instead of re-reading.
- **Inline evidence, not full files** — when citing code to support a finding or decision, quote the 3–5 relevant lines, not the whole function or file.

### Skill and instruction loading

- **Root CLAUDE.md** is always loaded — keep it lean (model assignment + core rules + skill routing pointer only).
- **bosskuAI/CLAUDE.md** — read at session start for meaningful tasks; skip for quick/trivial tasks.
- **Memory files** — read only the files relevant to the current task, not the entire memory directory.
- **Checklists** — load a checklist only when actively working through it; skip if you can apply the judgment directly.

### Model selection for token efficiency

- Use `claude-sonnet-4-6` for implementation and execution phases — it is faster and cheaper per token.
- Reserve `claude-opus-4-6` for planning, ambiguous analysis, and architecture decisions where deeper reasoning has clear payoff.
- Do not use Opus for tasks that are clearly mechanical (formatting, boilerplate generation, simple edits).

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not let context run out silently — stop and write the handoff state before truncation occurs.
- Do not carry full raw output forward — summarize large prior results into a compact note before continuing.
- Do not recommend a model without naming the primary and fallback with one-line tradeoffs.

## References

- `../../memory/active-continuation.md`
- `../../references/session-handoff-template.md`
- `../../references/checklists/context-limit-continuation-checklist.md`
- Pair with **`bosskuai-ai-model-selection`** for model recommendations at handoff.
