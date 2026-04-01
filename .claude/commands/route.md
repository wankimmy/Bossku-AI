# /route — Mandatory Task-Start Ritual

Run before any non-trivial task. Do not plan or execute before this completes.

## Steps

1. **Read memory** (follow `memory-first-handoff-protocol.md` read order):
   - `active-continuation.md` → if non-empty: state it, ask continue or new
   - `agent-profile.md` → `project-understanding.md` → `learning-log.md` (last 3)

2. **Parse request**: goal (1 line) · deliverable · risk (low/medium/high) · ambiguity (clear/partial/unclear)

3. **Ambiguity gate**: unclear + risk ≥ medium → stop, ask numbered yes/no questions, wait for confirmation

4. **Run Task classifier** (`AGENTS.md`): activation → scope → intent → cluster → skill → evidence → output

5. **Load skill(s)**: read `ai-assistant/skills/bosskuai-<name>/SKILL.md`, state files opened

6. **Resolve deprecated aliases** via `skill-index.json` (`replaced_by`)

7. **Emit [TASK START]** — this closes /route:
```text
[TASK START]
Memory read: <files>
Skill(s): <name + path>
Phase: Plan/Opus 4.6
Type: <cluster — intent>
```

8. Proceed to plan. Do not execute until plan confirmed.

## Trivial tasks
Emit header with "trivial" in all fields. Skip steps 3–6.
