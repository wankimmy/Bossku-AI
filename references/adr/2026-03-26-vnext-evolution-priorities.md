# ADR: BosskuAI vNext Evolution Priorities

- Date: 2026-03-26
- Status: Proposed

## Context

BosskuAI was compared against two adjacent repos in the same workspace:

- `agency-agents/`
- `everything-claude-code/`

The comparison shows three distinct strengths:

- BosskuAI is strongest at a curated cofounder-style operating posture across product, engineering, security, launch, marketing, sales, and model selection.
- Agency is strongest at persona breadth, lightweight specialist activation, and cross-tool conversion/install flows.
- Everything Claude Code is strongest at harness operations: commands, hooks, verification loops, maintenance workflows, learning systems, and packaging.

BosskuAI already has substantial expert-surface coverage:

- 25 skills
- 25 checklists
- 22 playbooks

The current weakness is not lack of expert lenses. The repo is relatively thin on operator ergonomics and harness mechanics:

- no install scripts
- no command layer
- no hook-based automation
- no automated skill/rule maintenance loop

## Decision

BosskuAI should evolve as a curated cofounder system plus a lightweight harness operating layer.

It should not try to compete with Agency or ECC by maximizing agent count. The next priority should be operational leverage:

1. command shortcuts for common workflows
2. installability across supported tools
3. verification and quality gates
4. project-scoped learning and promotion workflows
5. periodic skill and rule maintenance

## Why

### Why not prioritize more personas first

BosskuAI already covers the major decision surfaces a product team needs. Adding more roles now would increase overlap, maintenance cost, and activation ambiguity before the current system becomes easier to operate.

### Why borrow more from ECC than Agency

Agency offers useful inspiration for packaging and activation UX, but ECC provides the missing system behavior BosskuAI currently lacks:

- reusable commands
- verification entry points
- maintenance loops
- learning automation
- stronger install/update ergonomics

BosskuAI should still borrow selective ideas from Agency:

- easier expert activation language
- cross-tool install/export flow
- a clean orchestration pattern for multi-pass workflows

## Intended product position

BosskuAI should stay differentiated as:

- one cofounder-style assistant package
- minimal relevant skill loading instead of massive catalogs
- strong business plus engineering judgment
- portable across Codex, Claude, and Cursor

The repo should become easier to run and maintain without becoming a sprawling meta-framework.

## Phased roadmap

### Phase 1: Operator shortcuts

Goal: reduce friction for common high-value workflows.

Add next:

- `.claude/commands/plan.md`
- `.claude/commands/verify.md`
- `.claude/commands/quality-gate.md`
- `ai-assistant/skills/bosskuai-search-first/SKILL.md`
- `ai-assistant/references/checklists/verification-checklist.md`
- `ai-assistant/references/playbooks/verification-playbook.md`

Notes:

- Keep commands thin and repo-aligned.
- Reuse existing playbooks and skills where possible instead of duplicating guidance.
- Prefer commands that reinforce current BosskuAI posture: plan-first, evidence-first, review, verify.

### Phase 2: Installability and packaging

Goal: make the starter easier to apply to real workspaces.

Add next:

- `scripts/install.sh`
- `scripts/install.ps1`
- `scripts/apply-workspace.sh`
- `scripts/check-workspace.sh`

Update:

- `WORKSPACE-ONBOARDING.md`
- `README.md`

Notes:

- Keep the current portable, relative-path philosophy.
- Avoid machine-specific assumptions.
- Make cross-tool setup the default path instead of a manual copy flow.

### Phase 3: Learning and maintenance

Goal: make BosskuAI improve itself deliberately rather than only through manual edits.

Add next:

- `ai-assistant/skills/bosskuai-continuous-learning/SKILL.md`
- `ai-assistant/skills/bosskuai-skill-stocktake/SKILL.md`
- `ai-assistant/skills/bosskuai-rules-distill/SKILL.md`
- `ai-assistant/references/checklists/skill-health-checklist.md`
- `ai-assistant/references/checklists/rule-distillation-checklist.md`

Possible later automation:

- `.claude/hooks/`
- shared scripts under `scripts/` for observation and learning promotion

Notes:

- Prefer project-scoped learning over global spillover.
- Promote only durable patterns.
- Keep safety and prompt-injection concerns first-class when storing learned behavior.

### Phase 4: Guided orchestration

Goal: coordinate multi-pass work without turning BosskuAI into a giant agent zoo.

Add next only after Phases 1 to 3 are stable:

- `ai-assistant/skills/bosskuai-workflow-orchestrator/SKILL.md`
- `ai-assistant/references/playbooks/workflow-orchestration-playbook.md`

Notes:

- This should orchestrate existing BosskuAI skills, not replace them.
- Favor a small number of high-leverage orchestration workflows:
  - discovery to plan
  - plan to implementation
  - implementation to review and verify
  - launch-readiness sweeps

## Explicit non-goals

- Expanding into 100+ specialist personas in the near term
- Copying ECC wholesale
- Building tool-specific behavior that diverges from `AGENTS.md` as the shared source of truth
- Adding automation that writes unsafe memory or silently changes rules

## Success criteria

BosskuAI vNext is succeeding if:

- users can install or apply it faster
- common workflows require less prompt ceremony
- verification is easier to trigger and harder to skip
- durable learnings are promoted with less manual effort
- the repo remains understandable and curated

## Recommended next slice

Implement Phase 1 first.

The best next concrete slice is:

1. add a command layer for planning and verification in `.claude/commands/`
2. add `bosskuai-search-first`
3. add verification checklist and playbook support

This is the smallest package that meaningfully improves day-to-day usability without forcing a larger architecture change.
