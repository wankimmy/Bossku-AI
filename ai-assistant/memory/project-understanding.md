# Project Understanding

Use this file to store durable understanding about a specific codebase or product after reading its source.

Treat it as shared memory across supported tool surfaces.

## What to store

- what the project is
- who it appears to serve
- the main workflows or business purpose
- the stack and architecture style
- the likely source-of-truth files
- the most relevant expert skills for future work

## What not to store

- temporary debugging notes
- one-off task chatter
- guesses that were never confirmed

## BosskuAI

- Project type: Confirmed: Public reusable AI cofounder starter for product-building teams.
- Core purpose: Confirmed: Package one assistant setup that can reason across product, planning, project management, engineering, design, security, business logic, launch, marketing, sales, SEO/GEO, and AI model selection.
- Primary users: Confirmed: Founders, product teams, and operators who want one assistant package rather than many disconnected prompt files.
- Tool posture: Confirmed: Tool-agnostic starter with specific entry points for Codex, Claude, and Cursor.
- Source-of-truth files: Confirmed: `AGENTS.md` is the root instruction source; `.codex/AGENTS.md` adds Codex-specific guidance; `README.md` and `WORKSPACE-ONBOARDING.md` are the main public onboarding docs.
- Knowledge layout: Confirmed: `ai-assistant/skills/` contains the expert surfaces; `ai-assistant/references/` contains checklists, playbooks, pitfalls, and ADRs; `ai-assistant/memory/` is shared durable memory across tools.
- Current expert surface size: Confirmed: 28 skills, 29 checklists, and 26 playbooks are present in the repo.
- Codex support shape: Confirmed: `.codex/config.toml` enables multi-agent mode with read-only `planner`, `explorer`, `reviewer`, `security_reviewer`, `docs_researcher`, and `tdd_guide` roles.
- Current strength: Inferred: BosskuAI is stronger at curated cofounder-style breadth and cross-functional judgment than at harness automation.
- Current gap: Confirmed: The repo now has an initial Claude command layer, starter install/check scripts, maintenance workflows for skill and rule review, and optional advisory hook-ready scripts, but it still has no fully integrated always-on hook system and remains much lighter on operational glue than `everything-claude-code`.
- Strategic direction worth preserving: Inferred: BosskuAI should stay curated instead of expanding into a very large agent catalog. The next gains likely come from commands, installability, verification flows, and continuous learning rather than adding many more expert personas.
- Most relevant skills for future BosskuAI work: Confirmed: `bosskuai-workspace-assistant`, `bosskuai-project-understanding`, `bosskuai-search-first`, `bosskuai-skill-stocktake`, `bosskuai-rules-distill`, `bosskuai-planning-execution`, `bosskuai-engineering-delivery`, `bosskuai-agent-security-hardening`, and `bosskuai-ai-model-selection`.
- Install flow: Confirmed: `scripts/install.sh` and `scripts/install.ps1` apply the BosskuAI workspace layer into a target project; `scripts/check-workspace.sh` validates that the expected files are present.
- Maintenance flow: Confirmed: BosskuAI now includes skill stocktake and rules distillation workflows, matching Claude commands, and deterministic helper scripts under `ai-assistant/scripts/` for collecting maintenance context safely.
- Hook posture: Confirmed: BosskuAI now includes optional hook-ready reminder scripts under `ai-assistant/hooks/`, but they are advisory only and intentionally avoid automatic memory or rule mutation.
