# Workspace Onboarding

Use this guide after cloning the BosskuAI starter.

It is the main setup path for applying this starter inside a real project workspace and making it behave consistently in Cursor, Claude, and Codex.

## What gets applied

- `AGENTS.md`: root source-of-truth behavior for the workspace
- `.codex/`: Codex-specific config and engineering agent roles
- `CLAUDE.md`: root Claude-facing instruction file
- `.claude/commands/`: optional Claude shortcuts for planning, verification, and quality gates
- `.claude/rules/bosskuai.md`: Claude rule mirror for repo-wide behavior
- `.cursor/rules/bosskuai.mdc`: Cursor rule file with `alwaysApply: true`
- `ai-assistant/skills/`: reusable expert workflows
- `ai-assistant/memory/`: durable shared memory for the workspace
- `ai-assistant/references/`: checklists, playbooks, pitfalls, and handoff templates
- `ai-assistant/scripts/`: deterministic helper scripts for maintenance workflows
- `ai-assistant/hooks/`: optional advisory hook-ready reminders with no automatic state mutation
- `scripts/`: install and workspace validation helpers in the starter repo

## First time? Start here

1. Read **AGENTS.md** → **Skill roster (when to use which)** and **Quick reference: what to ask for** so you know which expertise exists and how to ask for it (including "work as the X").
2. Run the **workspace onboarding prompt** from the "Prompts" section below in this file.
3. Let the assistant draft `ai-assistant/memory/agent-profile.md` and `ai-assistant/memory/project-understanding.md`, then review and fix anything marked `Inferred:` or `Unknown`.
4. Use `bossku` anywhere in a prompt when you want the assistant to auto-activate BosskuAI rules and skill routing for that request.
5. Optionally try explicit activation: e.g. "work as the security reviewer" or "focus on launch commercialization" for a specific expert lens.

## Target workspace shape

Use this starter as a layer inside the actual project you want to work on.

Example target:

```text
my-product/
├── AGENTS.md
├── .codex/
├── CLAUDE.md
├── .claude/
├── .cursor/
└── ai-assistant/
```

## Fast path after `git clone`

1. Clone the BosskuAI starter and `cd` into it.
2. Apply the workspace layer into your actual project workspace (install **runs the workspace check** by default):

```bash
./scripts/install.sh /path/to/your/project
```

Use `./scripts/install.sh /path/to/your/project --skip-check` only if you intentionally want to skip validation.

On Windows PowerShell:

```powershell
.\scripts\install.ps1 C:\path\to\your\project
```

If `bash` is not available, run `./scripts/check-workspace.sh` from Git Bash or WSL with the same target path.

3. Open the target project root in Cursor, Claude, or Codex.
4. Run the workspace onboarding prompt from this file.
5. Let the assistant draft:
   - `ai-assistant/memory/agent-profile.md`
   - `ai-assistant/memory/project-understanding.md`
6. Review the draft and correct anything marked `Inferred:` or `Unknown`.

## Alternative: explore the starter itself

Use this only if you want to inspect the starter itself before applying it to a real product workspace.

1. Clone the repo.
2. Open the `bosskuAI/` folder as the workspace root.
3. Read [README.md](README.md) and this file.
4. Either fill in [agent-profile.md](ai-assistant/memory/agent-profile.md) manually or let project understanding draft it first.
5. Start with the onboarding prompt in the prompts section below.

## How rules and skills are picked up in each tool

Once the workspace is opened correctly, the simplest trigger is the keyword `bossku` in your prompt. That tells the assistant to apply BosskuAI rules and auto-pick the right skills for the request.

### Cursor

- Open the target project root that contains `.cursor/rules/`.
- Cursor will see `.cursor/rules/bosskuai.mdc`.
- Because the rule has `alwaysApply: true`, it should apply throughout the repo.
- The rule points the assistant back to `AGENTS.md` and `ai-assistant/`.

### Claude

- Open the target project root that contains `CLAUDE.md` and `.claude/rules/`.
- Claude should use `CLAUDE.md` at the workspace root.
- The mirrored rule in `.claude/rules/bosskuai.md` reinforces the same behavior.
- If `.claude/commands/` is present, use those commands as shortcuts for common workflows such as planning and verification.
- Claude should then follow the local skills under `ai-assistant/skills/`.

### Codex

- Open the target project root that contains `AGENTS.md`.
- Keep `.codex/AGENTS.md`, `.codex/config.toml`, and `.codex/agents/` in the same workspace root.
- Codex uses `AGENTS.md` as the repo instruction source.
- The `.codex/` folder adds Codex-specific engineering roles for planning, exploration, review, security, docs verification, and TDD guidance.
- The repo instructions tell Codex to use the relevant local skill instead of loading everything.
- Durable repo knowledge should be stored under `ai-assistant/memory/`.

## First customization to make

Use project understanding to draft [agent-profile.md](ai-assistant/memory/agent-profile.md), then confirm or correct:

- product name
- company or team
- industry
- primary users
- buyer or economic decision-maker
- main problem solved
- constraints
- risk tolerance
- priorities
- competitors

Then create or update [project-understanding.md](ai-assistant/memory/project-understanding.md) after the first repo analysis pass.

## Suggested onboarding flow

1. Let the assistant choose model/phase internally unless you need Debug or Handoff visibility.
2. Ask it to use `bosskuai-workspace-assistant` and `bosskuai-project-understanding`.
3. Make it read the nearest README, manifests, and source files first.
4. Ask it to separate confirmed facts from inference.
5. Ask it to draft both `agent-profile.md` and `project-understanding.md`.
6. Review and correct any `Inferred:` or `Unknown` fields.
7. For implementation-heavy work, ask it to use `bosskuai-engineering-delivery`.
8. For agent workspace or instruction security, ask it to use `bosskuai-agent-security-hardening`.
9. After adding enough custom guidance, use `bosskuai-skill-stocktake` or `bosskuai-rules-distill` to keep the setup clean and reusable.
10. If you enable hooks, keep them advisory and opt-in; avoid automatic memory or rule edits.
11. Only then move into implementation, review, planning, or strategy prompts.

## Refreshing understanding after BosskuAI changes

If you improve BosskuAI itself and want the assistant to re-learn the repo without wiping memory:

```bash
bash ./ai-assistant/scripts/relearn-project-understanding.sh
```

This will:

- snapshot the current `ai-assistant/memory/project-understanding.md`
- preserve the rest of `ai-assistant/memory/`
- generate a ready-to-paste prompt that tells the assistant to refresh project understanding and codebase analysis from source

If you also want to prepare an ephemeral handoff for the next session, use:

```bash
bash ./ai-assistant/scripts/relearn-project-understanding.sh --write-active-continuation
```

## Copy-paste prompts

### 1. Workspace onboarding prompt

Use this right after opening the project workspace in Cursor, Claude, or Codex.

```text
bossku

Use the workspace instructions in AGENTS.md, CLAUDE.md, .claude/commands/, .claude/rules/, .cursor/rules/, and the relevant skills under ai-assistant/skills/.

Start with bosskuai-workspace-assistant. Classify this task internally, choose the right model/phase without verbose routing output, and then use bosskuai-project-understanding if the repo context is still unclear.

Read the nearest README, manifests, docs, and enough source code to understand the project before making recommendations. Distinguish confirmed facts from inference.

Then give me:
1. what this project is
2. the stack and architecture
3. key source-of-truth files
4. which local skills should be used most often in this workspace
5. a draft for ai-assistant/memory/agent-profile.md using Confirmed, Inferred, or Unknown where appropriate
6. what should be written into ai-assistant/memory/project-understanding.md
```

### 2. Project understanding prompt

Use this when the workspace is new or the codebase is still unfamiliar.

```text
bossku

Use bosskuai-project-understanding and bosskuai-codebase-analysis.

Read the codebase first and explain the real project purpose, likely users, architecture style, main entry points, code organization, and technical risks. Only claim what is supported by the source code or nearby docs.

Separate:
- confirmed facts
- inference
- unknowns that still need confirmation

Then recommend the next 3 most relevant skills from ai-assistant/skills/, draft a concise update for ai-assistant/memory/project-understanding.md, and draft ai-assistant/memory/agent-profile.md. Mark any unsupported business details as Inferred or Unknown instead of guessing.
```

### 3. Efficiency test prompt

Use this to test whether the setup is actually following the rules instead of answering generically.

```text
bossku

I want to test whether this workspace setup is working correctly.

Follow the repo rules first. Use Debug mode for this test: classify the task, name the model/phase and local skills you are using, then do the work.

Then review this project in three passes:
1. project understanding
2. risk review across product, UX, business logic, security, and code quality
3. smallest high-confidence next action

Constraints:
- read nearby evidence before claiming anything
- separate confirmed facts from inference
- do not load every skill, only the relevant ones
- findings first if you discover issues
- end with a compact handoff note suitable for ai-assistant/memory/ or session continuation
```

## What a good response should look like

If the setup is working well, the assistant should:

- identify the task type first
- recommend a concrete model name before execution
- mention the specific skills it is loading
- read local evidence before making claims
- separate confirmed facts from inference
- avoid generic boilerplate advice
- suggest a memory update or handoff when useful

If it skips those behaviors, the rules are not being applied strongly enough in that tool.

## Quick compatibility check

Use this quick test in any tool:

```text
bossku

Before doing anything else, tell me:
1. which workspace rule files you are following
2. which local skills you will use
3. whether repo context is clear or whether project-understanding must run first
4. which concrete model name you recommend for this task and why
```

If the answer does not explicitly reflect the local workspace files and skills, reopen the correct workspace root and retry.

## Troubleshooting

- **I want to verify the layer is installed correctly** — Run `./scripts/check-workspace.sh /path/to/project` from the starter repo.
- **The starter is accumulating too many overlapping skills or rules** — Run a skill stocktake or rules distillation pass using the sample prompts so you can tighten the guidance before adding more.
- **Assistant doesn’t follow the roster or quick reference** — Point it to `AGENTS.md` → Skill roster and Quick reference: what to ask for. Ask: "Which skills are you loading for this task?"
- **I want a simple way to trigger the setup** — Start the prompt with `bossku` or include `bossku` naturally in the request, for example: `bossku review this PR for security and business-logic risks`.
- **Repo or product context is unclear** — Run project understanding first: "Use bosskuai-project-understanding. Read the repo and draft agent-profile and project-understanding."
- **Rules or skills seem ignored** — Confirm the workspace root you opened contains `AGENTS.md`, and for Cursor `.cursor/rules/`, for Claude `CLAUDE.md` and `.claude/rules/`. Reopen that root and retry the quick compatibility check above.
