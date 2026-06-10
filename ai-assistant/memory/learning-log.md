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

### Install script can preserve memory and skills-only sync — 2026-04-03

- **Source:** task (batch install overwrote downstream `ai-assistant/memory/`)
- **Signal:** full `install.sh` / `install.ps1` copy replaces entire `ai-assistant/`, including memory; partial conflicts only back up overlapping paths
- **Decision / learning:** `scripts/install.sh` and `scripts/install.ps1` support `--preserve-memory` / `-PreserveMemory` (stash `ai-assistant/memory/` before replace, restore after) and `--skills-only` / `-SkillsOnly` (refresh `ai-assistant/skills`, `references`, `scripts` only)
- **Promote to:** memory + optional README/WORKSPACE note later
- **Status:** applied
- **Confidence:** high
- **Last reviewed:** 2026-04-03

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

## 2026-04-03 — Claude (Sonnet 4.6)

- **User goal (one line):** Browse external Claude Code skills marketplace and enhance BosskuAI with missing skills.
- **What changed:**
  - Created 3 new skills: `bosskuai-performance-profiling`, `bosskuai-integration-testing`, `bosskuai-incident-response`
  - Enhanced 5 existing skills: `devops-iac` (observability instrumentation + deployment verification), `analytics-metrics` (retention/lifecycle metrics), `cybersecurity-risk` (supply chain security), `software-architecture` (event sourcing/CQRS/horizontal scaling advanced patterns), `workspace-assistant` (3 new routing rows)
  - Updated `AGENTS.md`: skill roster (3 Engineering rows), quick reference table (3 rows), local skills table (3 rows), proactive skill use Engineering bullet, phased pipelines Build + Harden phases
- **Skill count:** 39 → 42
- **Key gaps filled:**
  - Performance profiling: flame graphs, `0x`/Clinic.js, `py-spy`, `pprof`, `EXPLAIN ANALYZE`
  - Integration testing: CDC/Pact contract testing, test double taxonomy, fixture management
  - Incident response: SEV-1–4, 5-phase response, blameless postmortem with 5-Whys
- **Verification:** Grepped new skill names in workspace-assistant routing table and AGENTS.md — all 3 present; new SKILL.md files have correct frontmatter names; spot-checked devops-iac for new sections.
- **Open risks / unknowns:** Skill count in README.md and `.codex/AGENTS.md` still says 39 — update those to 42 in a follow-up.
- **Next actions for the next model** (numbered, imperative, include paths or commands):
  1. Update skill count in `README.md` from 39 to 42.
  2. Update skill count in `.codex/AGENTS.md` from 39 to 42.
  3. Run `bash ./ai-assistant/scripts/scan-skills.sh` to confirm 42 skills are detected.
- **Promotion note:** new skills promoted to skill files directly; no additional checklist/playbook artifacts needed
- **Memory files touched:** `learning-log.md` only

## 2026-04-01 — Codex

- **User goal (one line):** Change the BosskuAI Codex execution default to `gpt-5.4-mini` and update the README.
- **What changed:** Updated `AGENTS.md`, `.codex/AGENTS.md`, `.codex/config.toml`, and `README.md` so the Codex execution model now consistently points to `gpt-5.4-mini` while planning stays on `gpt-5.4`.
- **Commands run:** `rg -n "gpt-5\\.2|gpt-5\\.4-mini|Execute/gpt-5|\\| Codex \\|" README.md AGENTS.md .codex/AGENTS.md .codex/config.toml`; `git diff -- README.md AGENTS.md .codex/AGENTS.md .codex/config.toml`
- **Verification:** Targeted grep confirmed the touched files now use `gpt-5.4-mini` for Codex execution and no stale `gpt-5.2` remains in those edited files; diff review matched the requested change only.
- **Open risks / unknowns:** This was a targeted repo-doc/config update only; it did not re-audit every historical model mention outside the touched entry points.
- **Next actions for the next model** (numbered, imperative, include paths or commands):
  1. Run a fresh Codex session against this workspace and confirm the live default model behavior matches `.codex/config.toml`.
  2. Keep future model-default changes synchronized across `AGENTS.md`, `.codex/AGENTS.md`, `.codex/config.toml`, and `README.md`.
- **Promotion note:** none
- **Memory files touched:** `learning-log.md` only

## 2026-04-03 — Codex

- **User goal (one line):** Add a BosskuAI skill that tells Claude, Codex, and Cursor to bring in another AI model when the current one is stuck or needs help.
- **What changed:** Added `ai-assistant/skills/bosskuai-cross-model-escalation/SKILL.md`; updated `AGENTS.md`, `README.md`, `CLAUDE.md`, `.codex/AGENTS.md`, `.cursor/rules/bosskuai.mdc`, `.claude/rules/bosskuai.md`, `ai-assistant/skills/bosskuai-workspace-assistant/SKILL.md`, `ai-assistant/skills/bosskuai-ai-model-selection/SKILL.md`, `ai-assistant/skills/bosskuai-context-limit-continuation/SKILL.md`, and `ai-assistant/skills/bosskuai-subagent-delegation/SKILL.md` so cross-model escalation is now a first-class routed workflow; refreshed `ai-assistant/memory/project-understanding.md` to reflect the new 43-skill surface and durable cross-model capability.
- **Commands run:** `sed -n '1,220p' ai-assistant/memory/active-continuation.md`; `sed -n '1,220p' ai-assistant/memory/agent-profile.md`; `sed -n '1,260p' ai-assistant/memory/project-understanding.md`; `tail -n 120 ai-assistant/memory/learning-log.md`; `sed -n '1,260p' ai-assistant/references/memory-first-handoff-protocol.md`; `sed -n '1,220p' ai-assistant/skills/bosskuai-workspace-assistant/SKILL.md`; `sed -n '1,240p' ai-assistant/skills/bosskuai-subagent-delegation/SKILL.md`; `sed -n '1,220p' ai-assistant/skills/bosskuai-ai-model-selection/SKILL.md`; `sed -n '1,220p' .codex/AGENTS.md`; `sed -n '1,220p' CLAUDE.md`; `sed -n '1,220p' .cursor/rules/bosskuai.mdc`; `sed -n '1,220p' .claude/rules/bosskuai.md`; `sed -n '1,240p' scripts/install.ps1`; `rg --files ai-assistant/skills | sort`; `find ai-assistant/skills -name SKILL.md | wc -l`; `find ai-assistant/references/checklists -type f | wc -l`; `find ai-assistant/references/playbooks -type f | wc -l`; `rg -n "42 skills|43 skills|cross-model-escalation|gpt-5\\.2|gpt-5\\.4-mini" README.md AGENTS.md CLAUDE.md .codex/AGENTS.md .claude/rules/bosskuai.md .cursor/rules/bosskuai.mdc ai-assistant/skills ai-assistant/memory/project-understanding.md`; `git diff -- AGENTS.md README.md CLAUDE.md .codex/AGENTS.md .cursor/rules/bosskuai.mdc .claude/rules/bosskuai.md ai-assistant/skills/bosskuai-cross-model-escalation/SKILL.md ai-assistant/skills/bosskuai-workspace-assistant/SKILL.md ai-assistant/skills/bosskuai-ai-model-selection/SKILL.md ai-assistant/skills/bosskuai-context-limit-continuation/SKILL.md ai-assistant/skills/bosskuai-subagent-delegation/SKILL.md ai-assistant/memory/project-understanding.md`; `bash ./ai-assistant/scripts/scan-skills.sh`; `./scripts/verify-skill-references.sh`; `./scripts/check-workspace.sh .`; `bash ./ai-assistant/scripts/learning-doctor.sh`
- **Verification:** `scan-skills.sh` reported 43 skills and listed `bosskuai-cross-model-escalation`; `verify-skill-references.sh` passed; `check-workspace.sh .` passed; `learning-doctor.sh` passed and confirmed `project-understanding.md` counts now match the repo; targeted grep confirmed the new skill is wired into the root contract plus Claude/Codex/Cursor entry points and that `.claude/rules/bosskuai.md` no longer refers to `gpt-5.2`.
- **Open risks / unknowns:** This is an instruction-layer capability, not an always-on runtime hook, so downstream behavior still depends on each tool following the updated BosskuAI rules and skill routing protocol.
- **Next actions for the next model** (numbered, imperative, include paths or commands):
  1. Exercise the new workflow in fresh Claude, Codex, and Cursor sessions with a prompt like `bossku if you get stuck, escalate to another model and show me the assist brief` to confirm the live behavior matches the new skill.
  2. If future default models change, update `AGENTS.md`, `CLAUDE.md`, `.codex/AGENTS.md`, `.cursor/rules/bosskuai.mdc`, `.claude/rules/bosskuai.md`, and `ai-assistant/skills/bosskuai-ai-model-selection/SKILL.md` together so cross-model escalation stays consistent.
- **Promotion note:** promoted repeated “model got stuck / needs backup” behavior into a dedicated reusable skill plus rule updates across all tool entry points
- **Memory files touched:** `learning-log.md`, `project-understanding.md`
## 2026-06-03 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** learning
- **Captured at:** 2026-06-03T03:36:14+00:00

```text
Added 'Know Your User' profile + smarter chat to Bossku-AI. Profile is a singleton bossku_ai_memories row type='user' (matches type:user format), no migration. Backend: UserProfileService (get/save/generate via plannerModel), UserProfileController, routes GET/PUT/POST /user-profile(+/generate). Orchestrator now always prepends active user profile to memPayload (know-your-user grounding). UI: MemoryUserProfileCard pinned atop MemoryInspectorPanel with Edit+Regenerate, useUserProfile composable. Smart-chat: DeterministicTaskClassifier early-returns direct_answer for deliberative/advisory prompts (should i, what do you think, brainstorm) even when a code verb co-occurs; catch-all stops unmatched no-code-verb prompts defaulting to orchestrator_executor; PromptRouteClassifier::applyRiskPolicy no longer escalates talk-only routes into security pipelines. Verified: 15/15 standalone classifier cases pass, all official BosskuRoutingClassifierTest prompts still route correctly (couldn't run phpunit locally — needs Docker pgsql), nuxi typecheck clean for new files (2 pre-existing errors in JsonViewer/Skeleton unrelated). Next: run app tests inside Docker; optionally enrich profile generation with learning-log files.
```
## 2026-06-03 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** learning
- **Captured at:** 2026-06-03T16:21:35+00:00

```text
YouTube caption ingestion: YouTube now blocks caption baseUrl downloads from server/datacenter IPs — tracks ARE listed (watch page + InnerTube) but downloading the caption content returns HTTP 200 with 0 BYTES without a PO token (BotGuard). Verified empirically 2026-06-04: json3/srv3/xml/vtt + Referer/Origin all return 0 bytes for dQw4w9WgXcQ. InnerTube ANDROID client now 400s; WEB client returns 0 caption tracks. So 'lightweight captions-only, no key, no whisper' YouTube transcription does NOT work from cloud IPs — only from residential IPs or with a PO-token provider / transcript API / local whisper. YoutubeTranscriptService was hardened (InnerTube+HTML fallback, ASR ordering, tlang=en translation) and is correct, but blocked by YouTube server-side. Also FIXED a pre-existing chunkText infinite-tail bug: when end>=len, start=end-overlap stayed <len and re-emitted the final chunk up to maxChunks (was 60, raised to 300) — polluting memory with duplicate trailing chunks on every learned URL. Added break when end>=len. WebSearchService (DuckDuckGo, no key) verified working live.
```
## 2026-06-10 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** learning
- **Captured at:** 2026-06-10T01:54:34+00:00

```text
Executor quality upgrade shipped (P1+P2+apply-feedback), all opt-in flags default OFF: executor_strict_validation (plan-aware gate in ExecutorResponseParser rejects success-without-content, hallucinated no-op success on write-intent tasks, placeholder-elided diffs), executor_apply_feedback (file auto-apply errors/skips fold into known_issues + status downgrade via ExecutorEvidenceSupport::mergeApplyReport), executor_revision_escalation (first audit-failed revision escalates to high_risk profile via OrchestratorService::revisionProfileKey; old round-2+ condition was dead code at default max_revision_rounds=1). maxRevisionRounds now reads config('bossku.max_revision_rounds') fallback (default reconciled 3->1 to preserve behavior). Verified: 30 targeted tests + full suite (only the 3 known pre-existing failures: AnthropicProvider, ApiAuthMiddleware, ProjectFiles path traversal). Next candidates: P3 risk-aware first-pass profile, P4 deterministic patch pre-check, P5 same-model retry with raised max_tokens on JSON truncation.
```
## 2026-06-10 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** learning
- **Captured at:** 2026-06-10T06:33:27+00:00

```text
Executor quality batch 2 shipped (P3-P5 + critical bugfix). CRITICAL: FileWriteApplier::applyUnifiedDiff was broken pre-existing — returned null on any standard '@@' hunk header (all modify-via-diff silently skipped) and would corrupt mid-file hunks; rewritten as hunk-aware context-verified patcher with fuzzy offset recovery (stale LLM line numbers located by content), 13 golden tests. P4: ExecutorPatchPreflight (flag executor_patch_precheck) validates patches deterministically pre-audit (missing content, placeholders, conflict markers, dry-run diff apply) -> deterministicPatchPrecheckFailed needs_revision verdict with zero LLM cost. P3: firstPassProfileKey (flag executor_risk_aware_profile) — planner cannot downgrade router high_risk; high risk_level or plan confidence <0.5 runs FIRST pass on high_risk profile. P5: truncation retry boost (flag llm_truncation_retry_boost) — invalid_json_parse retry doubles max_tokens (cap 32000) on the SAME model before fallback; note LlmJsonParser repairs truncated objects so only brace-less responses fail parse. All 6 flags now: executor_strict_validation, executor_apply_feedback, executor_revision_escalation, executor_risk_aware_profile, executor_patch_precheck, llm_truncation_retry_boost — all default OFF, in Settings API BOOL_KEYS + allPublic. Suite: 443 tests, only the 3 known pre-existing failures.
```
## 2026-06-10 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** learning
- **Captured at:** 2026-06-10T06:52:41+00:00

```text
ECC integration v1.11.0 shipped: 7 skills ported from /Users/safwanyacob/Documents/Safwan/ECC into ai-assistant/skills/ — bosskuai-agent-architecture-audit (12-layer agent diagnostic, BosskuAI-grounded: AgentPersonaService/ModelFallbackService/LearningEngine), bosskuai-agent-introspection (capture-diagnose-recover for stuck/degraded runs incl. near-empty fallback responses), bosskuai-council (4-voice decision council), bosskuai-context-budget (context overhead audit incl. runtime-core persona cost), plus manual-only Laravel trio bosskuai-laravel-security/tdd/verification for app/ backend. Instinct model from ECC continuous-learning-v2 folded into bosskuai-continuous-learning (atomic confidence-weighted instincts at ai-assistant/memory/instincts/, promote at >=0.8, delete <0.3). skill-index.json v1.11.0 = 103 skills; AGENTS.md gained 'Agent-stack & decision skills' + 'Laravel stack specialists' rosters; plugin.json 1.2.0. All validators PASS: skill-index, references, workspace full, evals 18/18+8/8+3/3+4/4. greptile-skills upstream verified already in sync (edited-comments/GitLab/Perforce included since 2026-05-29 port). Uncommitted, layered over unrelated executor-batch-2 app/ changes.
```
## 2026-06-10 — claude — remember

- **Tool:** claude
- **Event:** remember
- **Source:** manual-remember
- **Kind:** learning
- **Captured at:** 2026-06-10T18:41:51+00:00

```text
v1.12.0 shipped: ported final 2 ECC skills + agent layer expansion. bosskuai-autonomous-loops (loop ARCHITECTURE catalogue: sequential claude -p pipeline, infinite agentic loop, continuous PR loop, de-sloppify, Ralphinho RFC-DAG; NanoClaw section replaced with BosskuAI runtime revise loop docs: max_revision_rounds default 1, ExecutorStuckDetector, near-empty fallback warning). bosskuai-prompt-optimizer (advisory-only prompt diagnosis, 6-phase pipeline remapped to bosskuai skill/agent roster, Laravel example). 5 new editor-mode agents in agents/: loop-operator (exit conditions mandatory, context bridge, stall detection, burn guard), performance-optimizer (baseline->profile->one-variable->re-measure ratchet, flat=revert), database-reviewer (down() verified, migrate --pretend SQL read, tenant-scope blocking finding, EXPLAIN evidence), code-simplifier (de-sloppify after green in separate context from author, 3-pass cap, behavior-preserving only), incident-responder (stabilize->verify->prevent, mitigation cap 3, closed only when prevention items exist). Core agents enhanced: orchestrator has Flows table (10 task-shape->chain->loop-owner routes) + council/introspection in runtime-core; planner has DAG decomposition rules + tier table (trivial/small/medium/large pipeline depth); executor: introspection-on-cap, de-sloppify handoff, laravel-tdd/verification wiring; security-reviewer: +laravel-security, prompt-injection-defense, tenant-isolation; auditor: +agent-architecture-audit for pipeline diffs, laravel-verification gate, database-reviewer delegation. skill-index v1.12.0 = 105 skills, plugin.json 1.3.0. All validators PASS, evals 18/18+8/8+3/3+4/4 unchanged. NOTE: only pipeline agents carry runtime-core blocks; the 5 new agents are editor-layer only (no DB persona, no sync impact). Still uncommitted.
```
