---
name: bosskuai-subagent-delegation
description: Use this to automatically delegate heavy, parallelizable, or risky tasks to subagents instead of running them serially in the main session. Covers auto-trigger decision logic and tool-specific patterns for Claude Code (Agent tool + worktree isolation), Cursor (parallel Composer tabs), and Codex (parallel runs).
---

# BosskuAI Subagent Delegation

Use this skill when a task is large enough, parallel enough, or risky enough that splitting it across multiple agents is more effective than running it serially in the main session.

## How this differs from nearby skills

- **`bosskuai-context-limit-continuation`**: stops and hands off to a *new serial session* when context is nearly exhausted. **This skill** delegates work to *parallel subagents before* context runs out — it is proactive, not reactive.
- **`bosskuai-workspace-assistant`**: orchestrates skill selection within one session. **This skill** extends that orchestration to multi-agent execution.

## Auto-trigger logic (apply without being asked)

Delegate to subagents automatically when **any** of the following are true:

| Signal | Threshold | Action |
|--------|-----------|--------|
| Independent files | ≥ 5 files that can be changed without shared state | Delegate to parallel subagents |
| Parallel workstreams | ≥ 2 fully independent tasks | One subagent per workstream |
| Context budget | Pre-task estimate > 1,200 lines | Delegate heavy subtasks; keep main session lean |
| Risky or destructive scope | Any task with irreversible side effects | Isolate in a worktree-scoped subagent |
| Batch operations | Same operation repeated across N targets (N ≥ 3) | Parallelize with N subagents |

**Pre-delegation check:** Before delegating, verify subtasks are truly independent — shared state, ordering dependencies, or write conflicts between parallel subagents will cause failures or corruption.

## Tool-specific patterns

### Claude Code — Agent tool

Use the built-in `Agent` tool to spawn subagents. Each runs autonomously with its own tool access.

**Key parameters:**

| Parameter | Value | When to use |
|-----------|-------|-------------|
| `subagent_type` | `"general-purpose"`, `"Explore"`, `"Plan"` | Match to subtask type |
| `run_in_background` | `true` | Independent subtasks that don't block the next step |
| `isolation` | `"worktree"` | Any subagent that writes to disk — gives an isolated git branch; auto-cleaned if no changes; returns branch path if changes made |
| `model` | `"opus"` / `"sonnet"` | Opus for planning/analysis subagents; Sonnet for execution |

**Common patterns:**

```
# Parallel read-only research (background)
→ Launch Explore agents for each domain area with run_in_background: true
→ Continue with other work while they run
→ Synthesize findings in the main session when all complete

# Parallel write tasks (worktree isolation)
→ Launch general-purpose agents with isolation: "worktree" for each independent file group
→ After all complete, review each worktree branch before merging

# Sequential with dependency
→ Launch first subagent foreground (no run_in_background), wait for result
→ Use result to inform the next subagent's prompt
```

**Subagent prompt quality rules:**
- Include the **full task description** — subagents have no main session context
- Specify **exact file paths**, expected outputs, and success criteria
- Tell the subagent explicitly whether to **write code or only research**
- Include relevant constraints (`"do not modify X"`, `"use the existing Y pattern"`)
- State the **model to use** if it matters for the subtask

### Cursor — Parallel Composer tabs

Cursor does not have a native Agent tool. Use multiple Composer sessions to parallelize:

1. **Open a new Composer tab** for each independent workstream (Cmd+T or equivalent)
2. **Give each tab a scoped prompt** — include only the files, goal, and constraints for that workstream
3. **Run tabs simultaneously** — Cursor allows multiple Composers to run in parallel
4. **Synchronize manually** — after all tabs complete, review each set of changes before applying
5. **Conflict check** — if two tabs touch the same file, run them sequentially instead

**When to use parallel Composer tabs:**
- Same transformation across N files (migration, rename, lint pass)
- Independent feature areas (e.g., auth module + dashboard + API layer)
- Parallel research (one tab reads docs, another reads code, another reads tests)

### Codex — Parallel runs

Codex supports multiple simultaneous runs. Use the agent role system:

1. **Launch parallel runs** for independent subtasks — one run per workstream
2. **Scope each run tightly** — minimum context, one goal per run
3. **Use the right agent role per run:**
   - `explorer`: read-only research and codebase mapping
   - `planner`: architecture and implementation planning
   - `reviewer`: correctness and security review
   - `tdd-guide`: test-first development pass
4. **Collect outputs** — after all runs complete, synthesize in a final aggregator run if needed

## Delegation output format

When delegating, state the plan before launching:

```
Delegating to subagents:

Subagent 1 — [description] ([tool pattern: Agent | Composer tab | Codex run])
  Goal: [what it will do]
  Scope: [files or domains it touches]
  Type: [read-only | writes to disk | risky/destructive]
  Background: [yes | no]
  Isolation: [worktree | none]

Subagent 2 — ...

Synthesis plan: [how results will be combined after all agents complete]
Dependency check: [shared state, write conflicts, or ordering dependencies to watch]
```

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not delegate tasks with shared mutable state or ordering dependencies — parallel subagents on shared state cause conflicts.
- Do not launch background subagents for tasks whose output is needed before the next step — use foreground (await result) for sequential dependencies.
- Do not skip worktree isolation for subagents that write to disk — without isolation, parallel writes corrupt the working tree.
- Do not write vague subagent prompts — each prompt must be self-contained with full context, file paths, goals, and constraints.
- Do not use subagent delegation as a substitute for context-limit-continuation — if context is already exhausted, use context-limit-continuation first.

## References

- Pair with **`bosskuai-context-limit-continuation`** when context budget is the delegation trigger
- Pair with **`bosskuai-workspace-assistant`** for orchestration and skill routing decisions
- Pair with **`bosskuai-ai-model-selection`** to choose the right model per subagent role
