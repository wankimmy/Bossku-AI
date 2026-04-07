---
name: bosskuai-cross-model-escalation
description: Use this when the current model is stuck, low-confidence, missing a capability, or repeating failed attempts. It defines how to bring in another model, tool surface, or session for scoped assistance across Claude, Codex, and Cursor without losing ownership of the task.
---

# BosskuAI Cross-Model Escalation

Use this skill when the active model should **stop thrashing and get help** from another model. The goal is not to abandon ownership of the task, but to bring in the right second opinion or helper path before time and context are wasted.

## How this differs from nearby skills

- **`bosskuai-ai-model-selection`**: chooses the best model for a task profile. **This skill** decides when to escalate mid-task and how to frame the helper request.
- **`bosskuai-subagent-delegation`**: splits independent workstreams in parallel. **This skill** is for a blocked or uncertain main workstream, even when the task is still single-threaded.
- **`bosskuai-context-limit-continuation`**: stops and hands work to a new session because of context or quota pressure. **This skill** is proactive and can happen before limits are a problem.

## Auto-trigger logic

Escalate when **any** of the following are true:

| Signal | What it means | Action |
|--------|----------------|--------|
| Same path failed twice | The model is looping on the same fix, search, or explanation | Stop retrying and ask another model for a fresh angle |
| Low confidence on high-impact work | Architecture, security, business logic, migrations, billing, auth, or destructive ops still feel uncertain | Get a second opinion before finalizing |
| Capability mismatch | The task needs longer context, different modality, stronger reasoning, or better coding/tool behavior than the current model offers | Switch to a better-fit helper model |
| Conflicting evidence | Two plausible explanations remain after reading the local evidence | Ask another model to challenge the current hypothesis |
| Tool/vendor limitation | Current tool surface cannot browse, cannot edit safely, or is degraded/unavailable | Hand off to another tool/model with a compact brief |
| User asks for backup | The user explicitly asks to call another model, get a second opinion, or escalate | Do it without extra ceremony |

## Escalation workflow

1. **Name the blocker clearly** — one sentence: what is stuck, uncertain, or missing.
2. **Keep one owner** — the current session stays responsible for synthesis and final verification.
3. **Choose the helper mode**:
   - **Second-opinion consult**: read-only reasoning or review
   - **Scoped implementation assist**: concrete patch/test plan for a narrow file set
   - **Cross-tool handoff**: another IDE/model completes the next phase
4. **Use `bosskuai-ai-model-selection`** to choose the best helper model for the remaining question or phase.
5. **Write a tight helper brief** — include:
   - blocker
   - what was tried
   - exact question to answer
   - files or evidence to inspect
   - what must not change
   - expected output format
6. **Integrate skeptically** — do not trust the helper blindly. Re-check against local code, tests, and requirements.
7. **If switching sessions or tools**, update `../../memory/active-continuation.md` first so Claude, Codex, and Cursor can continue without chat history.

## Tool-specific patterns

### Claude Code

- If **Opus 4.6** is stuck on strategy or diagnosis, ask **Sonnet 4.6** for a concrete patch shape, simpler hypothesis, or diff review.
- If Claude is still blocked because the task needs a different model family or tool surface, package a cross-tool brief and continue in Codex or Cursor.
- Keep Claude as the synthesizer when it still owns the main task; use the helper model for a bounded question, not a broad rewrite.

### Codex

- If **`gpt-5.4-mini`** stalls during implementation, escalate first to **`gpt-5.4`** for a scoped re-plan or skeptical review.
- If the OpenAI path still feels blocked, hand the narrowed question to Claude or a Cursor plan model with a short brief.
- Prefer a separate planner/reviewer-style pass for read-only help before opening a new write-heavy branch of work.

### Cursor

- Keep **Composer 2** as the execution surface.
- If Composer edits are looping or unclear, open a **Plan mode** pass with **Claude Opus 4.6**, **GPT-5.4**, or the strongest available reasoning model for a second opinion.
- If one planning model is unhelpful, switch to another planning model rather than retrying the same prompt shape repeatedly.

## Helper brief template

```text
Need cross-model help.
Current owner/tool:
Blocked on:
What has already been tried:
Exact question for helper:
Files to inspect:
Constraints / do not change:
Expected output:
```

## Guardrails

- Do not escalate just because the task is large; use `bosskuai-subagent-delegation` when the work is parallelizable.
- Do not wait for context exhaustion if the real problem is model fit or repeated failure.
- Do not hand vague context to another model. Narrow the ask to one concrete question or file set.
- Do not let multiple models make unsynchronized broad edits to the same files.
- Do not use helper output as truth. The owning session still verifies code, logic, and security implications.

## References

- Pair with **`bosskuai-ai-model-selection`** to choose the helper model
- Pair with **`bosskuai-subagent-delegation`** when the helper work can run in parallel
- Pair with **`bosskuai-context-limit-continuation`** when the escalation requires a fresh session or tool switch
- `../../memory/active-continuation.md`
- `../../references/memory-first-handoff-protocol.md`
