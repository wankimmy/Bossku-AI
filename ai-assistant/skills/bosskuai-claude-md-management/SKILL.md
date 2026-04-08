---
name: bosskuai-claude-md-management
description: Use this to audit, maintain, and improve CLAUDE.md and Claude-specific rule files, including instruction quality, session learning capture, overlap control, and cross-tool consistency with BosskuAI memory.
---

# BosskuAI CLAUDE.md Management

Use this skill when the task is to **audit or improve Claude instructions**: `CLAUDE.md`, `.claude/rules/`, Claude commands, session learnings, or Claude-specific entry-point drift.

## How this differs from nearby skills

- **`bosskuai-rules-distill`**: promotes repeated lessons into shared rules; this skill maintains Claude-facing rule surfaces.
- **`bosskuai-continuous-learning`**: triages durable learning; this skill captures learning into Claude instruction artifacts when that is the right destination.
- **`bosskuai-agent-security-hardening`**: reviews agent trust boundaries; this skill applies those concerns to Claude configuration and instructions.
- **`bosskuai-workspace-assistant`**: coordinates across tools; this skill focuses on Claude-specific instruction hygiene.

## Mindset

- `CLAUDE.md` should be useful at session start, not an archive of every decision ever made.
- Instruction overlap is allowed only when it is intentional and short.
- Claude-specific guidance must not contradict root `AGENTS.md` or shared memory.
- Session learnings should become durable artifacts only when they are stable, recurring, and actionable.
- The best rule is short enough to be followed under pressure.

## Surfaces to check

**Root `CLAUDE.md`**
- Startup protocol, Definition of Done, model split, memory behavior, and links to deeper references.
- Keep concise enough that a new Claude session can absorb it quickly.

**`.claude/rules/`**
- Domain-specific rules that should load reliably for Claude Code.
- Check for stale mirrors of root rules and missing links back to shared references.

**`.claude/commands/`**
- Repeatable workflows that benefit from explicit command prompts.
- Check inputs, outputs, verification steps, and whether the command duplicates a skill.

**Shared BosskuAI files**
- `AGENTS.md` remains the tool-neutral contract.
- `ai-assistant/memory/` remains the durable cross-tool coordination layer.
- `ai-assistant/references/` holds longer checklists and playbooks.

## Quality checks

- **Actionability**: each rule tells the agent what to do or avoid.
- **Placement**: shared rules live in shared files; Claude-only details live in Claude files.
- **Freshness**: model names, commands, paths, and tool assumptions still match the repo.
- **Brevity**: repeated or explanatory prose is collapsed into links where possible.
- **Safety**: secret handling, approvals, and untrusted content behavior remain explicit.
- **Continuity**: session learnings route through shared memory before tool-specific mirrors.

## Workflow

### Phase 1 - Inventory Claude surfaces

1. Read root `AGENTS.md`, `CLAUDE.md`, `.claude/rules/`, `.claude/commands/`, and relevant shared memory.
2. Identify which file is canonical for each behavior: tool-neutral contract, Claude-specific startup, rule mirror, command workflow, or memory.
3. Note duplicated rules and whether the duplication helps local startup or creates drift.

### Phase 2 - Audit instruction quality

4. Check whether instructions are specific, actionable, and testable.
5. Remove or rewrite vague directives like "be better" unless they encode a concrete behavior.
6. Check for stale model names, tool references, obsolete workflows, or contradictory priorities.
7. Check for instruction bloat: long narrative, repeated definitions, and rules that should be references instead.
8. Check safety: no secrets, no unsafe auto-mutation, no trust of external content without verification.

### Phase 3 - Capture session learnings

9. Extract only durable lessons from chat history, diffs, failures, or repeated friction.
10. Decide the right destination:
    - `ai-assistant/memory/learning-log.md` for a durable note
    - `CLAUDE.md` for Claude startup behavior
    - `.claude/rules/*.md` for persistent Claude rule surfaces
    - `ai-assistant/references/` for longer checklist or playbook guidance
    - no change for one-off chatter
11. Preserve cross-tool consistency: shared behavior should live in shared files first, then be mirrored lightly in Claude-specific files if needed.

### Phase 4 - Apply and verify

12. Make the smallest edit that improves behavior.
13. Re-read nearby sections to ensure no contradictions were introduced.
14. Verify links, headings, and references still resolve.
15. Summarize what changed, why it belongs there, and what was intentionally not promoted.

## Guardrails

- Do not make Claude-specific files the only source of a rule that must apply to Codex or Cursor too.
- Do not promote private session details, secrets, tokens, or user-sensitive facts.
- Do not duplicate large blocks from `AGENTS.md` into `CLAUDE.md`; use concise mirrors and links.
- Do not delete existing rules just because they overlap; confirm whether overlap is intentional first.
- Do not update model names or tool requirements without checking current project conventions or user intent.

## Output format

```text
CLAUDE.md management summary:
  Surfaces read: [files]
  Canonical source: [what owns the rule]
  Drift found: [yes/no + details]

Recommended or applied changes:
  [file] - [change] - [reason]

Learning promotion:
  Promoted: [artifact/path]
  Deferred: [reason]
  Not promoted: [reason]

Verification:
  Links/headings: [checked / not checked]
  Cross-tool consistency: [checked / risk]
```

## References

- `../../references/checklists/claude-md-management-checklist.md`
- `../../references/checklists/learning-promotion-checklist.md`
- `../../references/memory-first-handoff-protocol.md`
