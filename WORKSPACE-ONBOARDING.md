# Workspace Onboarding

Use this guide after cloning BosskuAI.

It is the main setup path for applying BosskuAI inside a real project workspace and making it behave consistently in Cursor, Claude, and Codex.

## What gets applied

- `AGENTS.md`: root source-of-truth behavior for the workspace
- `CLAUDE.md`: root Claude-facing instruction file
- `.claude/rules/bosskuai.md`: Claude rule mirror for repo-wide behavior
- `.cursor/rules/bosskuai.mdc`: Cursor rule file with `alwaysApply: true`
- `.codex-assistant/skills/`: reusable expert workflows
- `.codex-assistant/memory/`: durable shared memory for the workspace
- `.codex-assistant/references/`: checklists, playbooks, pitfalls, and handoff templates

## Target workspace shape

Use BosskuAI as a layer inside the actual project you want to work on.

Example target:

```text
my-product/
├── AGENTS.md
├── CLAUDE.md
├── .claude/
├── .cursor/
└── .codex-assistant/
```

## Fast path after `git clone`

1. Clone BosskuAI.
2. Copy these into the root of your actual project workspace:
   - `AGENTS.md`
   - `CLAUDE.md`
   - `.claude/`
   - `.cursor/`
   - `.codex-assistant/`
3. Open the target project root in Cursor, Claude, or Codex.
4. Run the workspace onboarding prompt from this file.
5. Let the assistant draft:
   - `.codex-assistant/memory/agent-profile.md`
   - `.codex-assistant/memory/project-understanding.md`
6. Review the draft and correct anything marked `Inferred:` or `Unknown`.

## Alternative: explore the starter itself

Use this only if you want to inspect the starter repo before applying it to a real product workspace.

1. Clone the repo.
2. Open the `bosskuAI/` folder as the workspace root.
3. Read [README.md](README.md) and this file.
4. Either fill in [agent-profile.md](.codex-assistant/memory/agent-profile.md) manually or let project understanding draft it first.
5. Start with the onboarding prompt in the prompts section below.

## How rules and skills are picked up in each tool

### Cursor

- Open the target project root that contains `.cursor/rules/`.
- Cursor will see `.cursor/rules/bosskuai.mdc`.
- Because the rule has `alwaysApply: true`, it should apply throughout the repo.
- The rule points the assistant back to `AGENTS.md` and `.codex-assistant/`.

### Claude

- Open the target project root that contains `CLAUDE.md` and `.claude/rules/`.
- Claude should use `CLAUDE.md` at the workspace root.
- The mirrored rule in `.claude/rules/bosskuai.md` reinforces the same behavior.
- Claude should then follow the local skills under `.codex-assistant/skills/`.

### Codex

- Open the target project root that contains `AGENTS.md`.
- Codex uses `AGENTS.md` as the repo instruction source.
- The repo instructions tell Codex to use the relevant local skill instead of loading everything.
- Durable repo knowledge should be stored under `.codex-assistant/memory/`.

## First customization to make

Use project understanding to draft [agent-profile.md](.codex-assistant/memory/agent-profile.md), then confirm or correct:

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

Then create or update [project-understanding.md](.codex-assistant/memory/project-understanding.md) after the first repo analysis pass.

## Suggested onboarding flow

1. Ask the assistant to recommend the best model profile for the task.
2. Ask it to use `bosskuai-workspace-assistant` and `bosskuai-project-understanding`.
3. Make it read the nearest README, manifests, and source files first.
4. Ask it to separate confirmed facts from inference.
5. Ask it to draft both `agent-profile.md` and `project-understanding.md`.
6. Review and correct any `Inferred:` or `Unknown` fields.
7. Only then move into implementation, review, planning, or strategy prompts.

## Copy-paste prompts

### 1. Workspace onboarding prompt

Use this right after opening the project workspace in Cursor, Claude, or Codex.

```text
Use the workspace instructions in AGENTS.md, CLAUDE.md, .claude/rules/, .cursor/rules/, and the relevant skills under .codex-assistant/skills/.

Start with bosskuai-workspace-assistant. First classify this task, recommend the best AI model profile for it with a short tradeoff note, and then use bosskuai-project-understanding if the repo context is still unclear.

Read the nearest README, manifests, docs, and enough source code to understand the project before making recommendations. Distinguish confirmed facts from inference.

Then give me:
1. what this project is
2. the stack and architecture
3. key source-of-truth files
4. which BosskuAI skills should be used most often in this workspace
5. a draft for .codex-assistant/memory/agent-profile.md using Confirmed, Inferred, or Unknown where appropriate
6. what should be written into .codex-assistant/memory/project-understanding.md
```

### 2. Project understanding prompt

Use this when the workspace is new or the codebase is still unfamiliar.

```text
Use bosskuai-project-understanding and bosskuai-codebase-analysis.

Read the codebase first and explain the real project purpose, likely users, architecture style, main entry points, code organization, and technical risks. Only claim what is supported by the source code or nearby docs.

Separate:
- confirmed facts
- inference
- unknowns that still need confirmation

Then recommend the next 3 most relevant skills from .codex-assistant/skills/, draft a concise update for .codex-assistant/memory/project-understanding.md, and draft .codex-assistant/memory/agent-profile.md. Mark any unsupported business details as Inferred or Unknown instead of guessing.
```

### 3. Efficiency test prompt

Use this to test whether the setup is actually following the rules instead of answering generically.

```text
I want to test whether this workspace setup is working correctly.

Follow the repo rules first. Classify the task, recommend the best model profile with tradeoffs, and explicitly name which local skills you are using before you do the work.

Then review this project in three passes:
1. project understanding
2. risk review across product, UX, business logic, security, and code quality
3. smallest high-confidence next action

Constraints:
- read nearby evidence before claiming anything
- separate confirmed facts from inference
- do not load every skill, only the relevant ones
- findings first if you discover issues
- end with a compact handoff note suitable for .codex-assistant/memory/ or session continuation
```

## What a good response should look like

If the setup is working well, the assistant should:

- identify the task type first
- recommend a model profile before execution
- mention the specific skills it is loading
- read local evidence before making claims
- separate confirmed facts from inference
- avoid generic boilerplate advice
- suggest a memory update or handoff when useful

If it skips those behaviors, the rules are not being applied strongly enough in that tool.

## Quick compatibility check

Use this quick test in any tool:

```text
Before doing anything else, tell me:
1. which workspace rule files you are following
2. which local skills you will use
3. whether repo context is clear or whether project-understanding must run first
4. which model profile you recommend for this task and why
```

If the answer does not explicitly reflect the local workspace files and skills, reopen the correct workspace root and retry.
