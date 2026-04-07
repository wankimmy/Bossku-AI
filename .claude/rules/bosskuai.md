# BosskuAI Rules — always active in this workspace

Source of truth: `AGENTS.md`. Full DoD: `CLAUDE.md`. Skill roster + quick reference: `AGENTS.md`.

## Per-turn protocol (mandatory — every response)

**Every non-trivial response MUST begin with [TASK START] and end with [TASK END].**

### Step 1 — Read memory (before acting)
Order: `ai-assistant/memory/active-continuation.md` → `agent-profile.md` → `project-understanding.md` → `learning-log.md` (last 3 entries)

### Step 2 — Load skill
Open `AGENTS.md` → Quick reference → read `ai-assistant/skills/bosskuai-<name>/SKILL.md`

### Step 3 — Emit [TASK START]
```text
[TASK START]
Memory read: <files, or "trivial">
Skill(s): <name + path, or "trivial">
Phase: <Plan/Opus 4.6 | Execute/Sonnet 4.6 | Trivial>
Type: <cluster/intent>
```

### Step 4 — Act (plan before execute on non-trivial tasks)

### Step 5 — Emit [TASK END]
```text
[TASK END]
Meaningful: <yes|no>
Memory: <paths updated, or "none">
Learning: <artifact+path, or "deferred: reason">
```
Trivial tasks: emit both blocks with "trivial/no". No silent skips — a missing header is a violation.

## Model split (mandatory)
| Phase | Claude | Codex | Cursor |
|-------|--------|-------|--------|
| Plan | Opus 4.6 | gpt-5.4 | strongest available |
| Execute | Sonnet 4.6 | gpt-5.4-mini | Composer 2 |

Never skip planning on non-trivial tasks.
If the active model is stuck, low-confidence, or missing a capability, load `bosskuai-cross-model-escalation` and escalate before repeating the same failed path.

## Memory (mandatory — all tools, every turn)
`ai-assistant/memory/` is shared across Claude, Codex, Cursor. Read before acting. Write before done.
Protocol: `ai-assistant/references/memory-first-handoff-protocol.md`
If 'Memory read:' is blank on a non-trivial turn — protocol was skipped.

## Task routing (mandatory — never skip)
1. Classify: trivial or non-trivial
2. Open `AGENTS.md` → Quick reference → pick cluster + skill
3. Read `ai-assistant/skills/bosskuai-<name>/SKILL.md`
4. State which skills loaded in [TASK START] header
5. Plan then execute

Load minimum relevant skills. When ambiguous, pick closest and note assumption.

## Bossku keyword
Standalone `bossku` in any prompt → activate BosskuAI mode, auto-load minimum relevant skills.

## Continuous learning (post-task)
Meaningful = ANY true: file changed / decision made / bug found / skill applied non-generically / pattern 2+ times / gap surfaced.
Trivial = ALL true: no files changed + no repo conclusion + pure lookup.
Always emit [TASK END]. Run `/continuous-learning` or explicitly defer after meaningful tasks.

## Definition of Done
See `CLAUDE.md` § Definition of Done for the full checklist. Always verify before declaring done.

## References
- Skill roster + quick reference: `AGENTS.md`
- Full DoD: `CLAUDE.md`
- Memory protocol: `ai-assistant/references/memory-first-handoff-protocol.md`
- Skills: `ai-assistant/skills/bosskuai-<name>/SKILL.md`
