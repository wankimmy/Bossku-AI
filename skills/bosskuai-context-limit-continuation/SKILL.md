---
name: bosskuai-context-limit-continuation
description: "Use when context, token, or quota limits threaten to cut off a task mid-way. A handoff requested while context is healthy belongs to bosskuai-handoff."
---

# BosskuAI Context-Limit Continuation

Use this skill when a task is long enough that the current model session may run out of context budget, hit token limits, face usage or quota pressure, or otherwise risk being cut off before completion.

**Not covered here:** training or fine-tuning ML models. For choosing *which* commercial/chat model to use, load **`bosskuai-ai-model-selection`** in the same pass. If the problem is that the current model is blocked but context is still healthy, use **`bosskuai-cross-model-escalation`** instead of this skill.

If the user simply *asks* for a handoff while context is still healthy, use **`bosskuai-handoff`**; this skill is the budget-forced variant, and both write the same `.bossku/memory/handoff.md`.

## When to activate

Activate when any is true: the host reports context usage near its limit (about 80%) or warns that auto-compaction is imminent; a compaction already dropped details the task still needs; a usage, quota, or rate-limit warning appeared; or the user says they are running out. File, line, and turn counts are not signals on 200K-1M windows.

Before a task you expect to outgrow the window, name a clean stopping point in the plan. Do not stop to confirm scope for budget reasons alone.

## Continuation workflow

1. Confirm one of the signals in "When to activate" is present; if context is still healthy and the user wants a handoff, use `bosskuai-handoff` instead.
2. **Before stopping to hand off: check if remaining work has ≥ 2 independent workstreams.** If so, consider delegating them in parallel using `bosskuai-subagent-delegation` instead — parallel subagents can complete independent work without consuming the main session context. Only stop and hand off if the remaining work is fundamentally serial or subagents cannot complete it independently.
3. Stop before truncation instead of letting the work end abruptly.
4. Summarize what has already been completed, what remains, and any key decisions already made.
5. **Update memory for the handoff** — Fill or overwrite `.bossku/memory/handoff.md` with goal, done, remaining, key files, risks, and a **paste block** for the next session. This is the durable bridge other tools can read; clear that file when the task is fully done.
6. **Recommend the next model** — Apply **`bosskuai-ai-model-selection`** for the *remaining* work (not the whole original task if the phase changed). Give a **primary** and **fallback** model by concrete name when the tool exposes them, with one-line tradeoffs (reasoning vs speed vs cost vs context).
7. **Tell the user clearly** to **start a new chat or session** (or switch model in-product) using the **recommended model**, paste the continuation block; `.bossku/memory/handoff.md` is the file the next session reads first.
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
- confirmation that **`.bossku/memory/handoff.md`** was updated (or why skipping is justified)
- short retry / continue instruction for the user

## Guardrails

- Do not let context run out silently — stop and write the handoff state before truncation occurs.
- Do not carry full raw output forward — summarize large prior results into a compact note before continuing.
- Do not recommend a model without naming the primary and fallback with one-line tradeoffs.

## References

- `.bossku/memory/handoff.md`
- `../../references/session-handoff-template.md`
- `../../references/checklists/context-limit-continuation-checklist.md`
- Pair with **`bosskuai-ai-model-selection`** for model recommendations at handoff.
- Pair with **`bosskuai-cross-model-escalation`** when the issue is model fit or repeated failure rather than context pressure.
- Pair with **`bosskuai-handoff`** when the user requests a handoff without context pressure.
