# Learning Log

Use this file for durable, cross-session learnings that matter in future work.
Treat it as shared memory that should improve behavior across Codex, Claude, Cursor, and any future compatible tool surface that reads this repo.

## What belongs here

- durable behavioral lessons
- explicit promotion decisions
- repo-level decisions that should be reviewed again later

Do not use this file for temporary debugging chatter, raw task transcripts, or a full duplicate of `CHANGELOG.md`.

## Entry format

Append new entries in this format:

```markdown
### [Short title] — YYYY-MM-DD

- **Source:** task / review / incident / repeated usage / audit
- **Signal:** what happened or what was observed
- **Decision / learning:** the durable lesson
- **Promote to:** memory / checklist / pitfall / playbook / skill / rule / ADR / defer
- **Status:** applied / proposed / deferred / superseded
- **Confidence:** high / medium / low
- **Last reviewed:** YYYY-MM-DD
```

## Active entries

### Context limits require an explicit handoff before truncation — 2026-03-16

- **Source:** repeated usage
- **Signal:** meaningful work can be cut off mid-task when context or usage limits are approached too late
- **Decision / learning:** BosskuAI should stop before truncation, preserve a compact continuation state, and pair the handoff with a model recommendation for the remaining work
- **Promote to:** skill + checklist + memory
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-03-30

### Operator leverage should win over persona expansion — 2026-03-26

- **Source:** audit
- **Signal:** BosskuAI already had strong cofounder-style breadth, while the bigger gaps were commands, installability, verification, maintenance, and learning loops
- **Decision / learning:** prioritize harness operations and curated quality over adding more overlapping expert personas
- **Promote to:** ADR + memory
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-03-30

### Setup and maintenance should be scriptable, deterministic, and proposal-first — 2026-03-26

- **Source:** audit + implementation
- **Signal:** onboarding and maintenance improved once the repo added install/check scripts, deterministic inventory scripts, and advisory hooks rather than relying on manual ceremony
- **Decision / learning:** prefer lightweight deterministic helpers and explicit review checkpoints over silent automation or repo-specific one-off workflows
- **Promote to:** scripts + playbooks + hooks guidance
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-03-30

### Continuous learning needs an explicit triage workflow — 2026-03-30

- **Source:** repo audit
- **Signal:** learning promotion existed mainly as policy, reminders, and maintenance notes; no dedicated workflow routed lessons into the strongest artifact consistently
- **Decision / learning:** BosskuAI should treat continuous learning as a first-class post-task workflow with explicit triage, freshness checks, and proposal-first promotion
- **Promote to:** skill + checklist + playbook + command + script
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-03-30

### Entry-point overlap should be intentional and token-efficient — 2026-03-30

- **Source:** audit + implementation
- **Signal:** duplicated instructions across entry points made startup context heavier and increased drift risk unless the overlap was explicit and controlled
- **Decision / learning:** keep root `AGENTS.md` as the tool-neutral contract, keep local entry points thinner, and duplicate only the minimum guidance needed for each tool to start in the right mode
- **Promote to:** rules + docs
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-03-30

### Skill quality should improve through depth, not only count — 2026-03-30

- **Source:** repo-wide skill improvement pass
- **Signal:** the biggest gains came from clarifying boundaries, adding concrete frameworks, output formats, and guardrails across existing skills rather than multiplying adjacent personas
- **Decision / learning:** when the roster grows, prefer deepening high-traffic skills and tightening boundaries before adding new surface area
- **Promote to:** ADR + skill-stocktake guidance
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-03-30

## 2026-04-01 — Codex

- **User goal (one line):** Test the BosskuAI skill setup in the repo itself.
- **What changed:** Updated `README.md`, `.codex/AGENTS.md`, and `.codex/config.toml` to align the documented Codex execution model with the root contract (`gpt-5.2`) and to correct the public skill-count copy from 35 to 39.
- **Commands run:** `./scripts/check-workspace.sh .`; `./scripts/verify-skill-references.sh`; `bash ./ai-assistant/scripts/learning-doctor.sh`; `bash ./ai-assistant/scripts/scan-skills.sh`; `rg -n "35 skills|39 skills|gpt-4\\.1|gpt-5\\.2" README.md .codex/AGENTS.md .codex/config.toml`; `git diff -- README.md .codex/AGENTS.md .codex/config.toml`
- **Verification:** Workspace check passed; skill-reference verification passed; learning doctor passed; skill inventory confirmed 39 skills; targeted grep confirmed stale `gpt-4.1` and `35 skills` strings were removed from the touched Codex/README entry points.
- **Open risks / unknowns:** This was a targeted consistency pass, not a full repo-wide language audit for every historical mention of older model names or earlier skill counts.
- **Next actions for the next model** (numbered, imperative, include paths or commands):
  1. Run the efficiency test prompt from `WORKSPACE-ONBOARDING.md` in a fresh Codex session to confirm the live assistant behavior matches the corrected docs.
  2. If future model defaults change again, update root `AGENTS.md`, `.codex/AGENTS.md`, and `.codex/config.toml` in the same commit to avoid drift.
- **Promotion note:** none
- **Memory files touched:** `learning-log.md` only
