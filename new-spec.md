# Cursor Prompt: Enhance BosskuAI into a Self-Learning Developer AI Orchestrator

Repository:

```txt
https://github.com/wankimmy/Bossku-AI
```

Reference products:

```txt
Paperclip AI: https://github.com/paperclipai/paperclip
Hermes Agent: https://github.com/nousresearch/hermes-agent
```

## Product Direction

Transform BosskuAI into a serious developer AI orchestration product.

BosskuAI should become:

> A self-learning AI developer orchestrator that coordinates multiple LLMs, agents, skills, plugins, memory, feedback, model routing, audits, logs, and knowledge graphs into one observable AI engineering control plane.

Short tagline:

> One control plane for your AI coding team.

Public positioning:

> BosskuAI is your AI co-founder for planning, coding, auditing, learning, and shipping software products.

## Important Product Boundary

BosskuAI must not try to replace Cursor, Claude Code, Codex, OpenCode, or Ollama.

BosskuAI should become the layer above them:

```txt
Cursor / Claude Code / Codex / OpenCode / Ollama Cloud
        ↓
BosskuAI Orchestrator
        ↓
Plan → Execute → Audit → Learn → Improve next run
```

The core value is:

```txt
Run → Plan → Execute → Audit → Learn → Improve next run
```

Everything in this project must support that loop.

---

# 1. Core Product Philosophy

## BosskuAI Must Be

- Developer-first
- Observable
- Self-learning
- Skill-driven
- Memory-aware
- Model-agnostic
- Plugin-ready
- Audit-focused
- Safe by default
- Useful for solo founders and technical leads
- Practical, not gimmicky

## BosskuAI Must Not Be

- Generic chatbot
- Generic SaaS dashboard
- Fake AI magic UI
- A dashboard full of demo-only cards
- Overdesigned “AI slob” interface
- Raw hidden chain-of-thought viewer
- Complex before useful
- Platform before workflow

## Main User Story

As a developer/founder, I want to give BosskuAI a software task, so that it can:

1. Understand the task.
2. Detect the correct skill.
3. Load relevant memory.
4. Select the correct model.
5. Create a clear plan.
6. Execute safely.
7. Track changed files.
8. Run audit/review.
9. Show logs and usage.
10. Learn from the result.
11. Improve the next similar run.

---

# 2. Implementation Strategy

Implement this project in tiers.

Do not build everything as shallow UI.

Build usable core workflow first, then learning, then graphs.

## Tier 1: Core Orchestration Control Plane

Must work first.

Includes:

- Dashboard
- Runs
- Run Detail
- Agent timeline
- Agent messages
- Plan checklist
- Skill detection display
- Memory injection display
- Model routing display
- Tool call logs
- File change tracking
- Audit loop
- Usage/cost tracking
- Logs
- Settings
- Provider/model routing
- Basic feedback buttons
- Approval gates

## Tier 2: Self-Learning Brain

Build after Tier 1 foundation exists.

Includes:

- Brain page
- Memory health
- Learning inbox
- Feedback learning
- Auto skill candidate generation
- soul.md
- soul versioning
- Memory confidence
- Skill confidence
- Learning events
- Skill approval workflow

## Tier 3: Knowledge Graph and Skills Graph

Build after Tier 1 and Tier 2 are functional.

Includes:

- Knowledge Graph page
- Skills Graph page
- Node/edge storage
- Graph filters
- Graph inspector
- Conflict detection
- Skill quality scoring
- Duplicate skill detection
- Actionable graph nodes

Important:

Tier 3 should not be decorative only.
Every graph must support useful actions.

---

# 3. UI Direction

Build a polished developer control panel.

## Visual Style

- Dark-first interface
- IDE-like layout
- Compact but readable
- Developer tool feel
- Serious and clean
- No cartoon AI mascot
- No giant generic gradients
- No fake glassmorphism overload
- No empty marketing layout
- No meaningless “AI magic” wording
- Prefer tables, timelines, logs, diffs, badges, and graphs

## Layout

Use this layout:

```txt
┌────────────────────────────────────────────────────────────┐
│ Top Bar: Project Switcher | Active Run | Model Status      │
├───────────────┬───────────────────────────────┬────────────┤
│ Sidebar       │ Main Workspace                │ Inspector  │
│               │                               │            │
│ Dashboard     │ Run Timeline                  │ Agent Info │
│ Runs          │ Agent Messages                │ Memory     │
│ Agents        │ Plan Checklist                │ Skills     │
│ Skills        │ Diff Viewer                   │ Usage      │
│ Memory        │ Logs                          │ Risks      │
│ Brain         │ Graphs                        │            │
│ Logs          │                               │            │
│ Usage         │                               │            │
│ Settings      │                               │            │
├───────────────┴───────────────────────────────┴────────────┤
│ Bottom Drawer: Live logs, tool calls, SSE stream             │
└────────────────────────────────────────────────────────────┘
```

## Navigation

Sidebar:

```txt
Dashboard
Runs
Agents
Skills
Memory
Brain
Knowledge Graph
Skills Graph
Plugins
Logs
Usage
Feedback
Soul
Settings
```

Settings sub-navigation:

```txt
Providers
Model Routing
Governance
Approval Gates
Learning
Secrets
```

---

# 4. Safety and Transparency Rules

## Do Not Expose Hidden Chain-of-Thought

The user wants to see the AI thought process, but do not expose hidden private chain-of-thought.

Instead show safe structured reasoning:

```txt
Intent Summary:
What the agent understood.

Decision Summary:
Why this agent/model/skill was selected.

Plan:
Visible checklist of steps.

Evidence Used:
Memory, files, docs, skills, plugins.

Risk Summary:
What could go wrong.

Audit Summary:
What the auditor checked.

Next Action:
What happens next.
```

Add UI tooltip:

```txt
BosskuAI shows safe reasoning summaries, not hidden private chain-of-thought.
```

## Dangerous Operations Require Approval

Require approval before:

- Running terminal commands
- External HTTP calls
- Installing plugins
- Deleting files
- Modifying `.env`
- Modifying deployment config
- Modifying auth/security code
- Modifying payment gateway code
- Running database migrations
- Production deployment
- Rotating secrets
- Using high-cost model escalation

## Governance Rules

1. Never store raw API keys in memory.
2. Never store secrets in vector DB.
3. Never expose hidden chain-of-thought.
4. Never auto-approve generated skills for payment/security/deployment tasks.
5. Never mutate `soul.md` without user approval.
6. Never run destructive terminal commands without approval.
7. Never apply low-confidence memory without warning.
8. Never use stale memory if newer conflicting memory exists.
9. Always record provider/model per agent step.
10. Always record selected skill per run.
11. Always record memory used per run.
12. Always record `soul.md` version per run.
13. Always record changed files with responsible agent.
14. Never claim tests passed unless actually run.
15. Never invent repo facts.

---

# 5. Tier 1: Core Orchestration Control Plane

## Goal

Build the core workflow:

```txt
User task
  ↓
Skill detection
  ↓
Memory loading
  ↓
Model routing
  ↓
Plan generation
  ↓
Execution
  ↓
File/tool tracking
  ↓
Audit
  ↓
Review
  ↓
Usage/logging
  ↓
Feedback
```

This is the most important part of BosskuAI.

---

## 5.1 Dashboard Page

Route:

```txt
/dashboard
```

Purpose:

Show current BosskuAI orchestration health.

### Sections

#### KPI Strip

Show:

- Active Runs
- Completed Runs Today
- Failed Runs
- Token Usage Today
- Estimated Cost Today
- Estimated Cost This Month
- Active Skills
- Active Plugins
- Memory Items
- Pending Feedback
- Average Audit Score

#### Live Orchestrator Feed

Show recent events:

```txt
Orchestrator selected Laravel skill
Memory loaded 4 project facts
Executor started file changes
Auditor found 2 risks
Final reviewer approved output
Feedback saved as memory
```

#### Agent Team Status

Cards for:

- Orchestrator
- Executor
- Auditor
- Final Reviewer
- Memory Curator
- Skill Detector
- Model Router

Each card shows:

- Status: idle / running / failed / paused
- Provider
- Model
- Current task
- Last activity
- Tokens today
- Cost today
- Success rate
- Health status

#### Recent Runs

Table showing:

- Run title
- Status
- Skill used
- Agent route
- Model route
- Duration
- Cost
- Audit score
- Risk level
- Open button

---

## 5.2 Runs Page

Route:

```txt
/runs
```

Purpose:

List, search, and filter AI runs.

### Features

- Search runs
- Filter by status
- Filter by skill
- Filter by agent
- Filter by model
- Filter by provider
- Filter by risk level
- Filter by date
- Sort by newest
- Sort by cost
- Sort by duration
- Sort by failure
- Sort by audit score

### Run Card/Table Fields

- Run ID
- Prompt summary
- Status
- Agent sequence
- Selected skill
- Memory used yes/no
- Tool calls count
- Files changed count
- Token usage
- Estimated cost
- Audit score
- Risk level
- Created date

Clicking a run opens `/runs/{id}`.

---

## 5.3 Run Detail Page

Route:

```txt
/runs/{id}
```

This is the most important page.

Tabs:

```txt
Overview
Timeline
Agent Conversation
Plan
Tool Calls
File Changes
Memory
Audit
Usage
Feedback
```

### Overview Tab

Show:

- Original prompt
- Final answer
- Current status
- Agent route
- Skill used
- Models used
- Providers used
- Memory used
- Tools used
- Files changed
- Tokens and cost
- Risk level
- Audit score
- `soul.md` version used if available

### Timeline Tab

Vertical timeline:

```txt
Prompt received
Soul loaded
Skill detected
Memory searched
Context loaded
Model route selected
Plan generated
Executor started
Tool calls
File changes
Auditor review
Executor fix pass
Final reviewer
Completed / failed
```

Each item shows:

- Timestamp
- Agent
- Provider
- Model
- Skill
- Summary
- Status
- Token usage
- Cost
- Expandable metadata

### Agent Conversation Tab

Show clean agent messages.

Each message must include:

```txt
[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|reviewer|memory>
Model Role: <planner|coder|reviewer|memory-curator>
Memory Used: <yes|no>
Model: <provider/model>
```

Then show safe structured output:

```txt
Intent Summary
Decision Summary
Plan
Evidence Used
Risk Summary
Next Action
```

### Plan Tab

Show orchestrator plan as checklist.

Step states:

- Pending
- In Progress
- Done
- Blocked
- Failed
- Needs Approval

Actions:

- Approve step
- Pause run
- Rerun from step
- Send back to executor
- Send back to auditor
- Create skill from step
- Save step as memory

### Tool Calls Tab

Show every tool/plugin call:

- Tool name
- Plugin name
- Input summary
- Output summary
- Duration
- Status
- Error message
- Retry count
- Agent
- Model
- Timestamp

Use collapsible cards.

### File Changes Tab

Create a simple diff viewer.

Show:

- Changed files
- Added files
- Deleted files
- Patch preview
- Agent that changed it
- Reason for change
- Related step
- Auditor note
- Approval state

### Memory Tab

Show memory used in the run:

- Title
- Type
- Source
- Relevance score
- Confidence
- Summary
- Injected yes/no
- Related skill
- Related project

Actions:

- Pin
- Archive
- Delete
- Mark wrong
- Create skill from memory
- View in graph

### Audit Tab

Show auditor results:

- Correctness
- Security
- Performance
- Maintainability
- UX
- Testing
- Documentation
- Risk summary
- Required fixes
- Optional improvements
- Final audit score 0–100

### Usage Tab

Show:

- Prompt tokens
- Completion tokens
- Total tokens
- Estimated cost
- Model-by-model cost
- Provider-by-provider cost
- Agent-by-agent usage
- Expensive steps
- Token saving recommendations

### Feedback Tab

Allow feedback on:

- Whole run
- Agent message
- Plan step
- File change
- Auditor decision
- Skill selection
- Memory selection
- Model routing decision

---

## 5.4 Agents Page

Route:

```txt
/agents
```

Required agents:

- Orchestrator
- Executor
- Auditor
- Final Reviewer
- Memory Curator
- Skill Detector
- Model Router

Each agent card shows:

- Name
- Role
- Status
- Provider
- Model
- Fallback provider
- Fallback model
- Permissions
- Budget
- Skills allowed
- Plugins allowed
- Last run
- Success rate
- Average cost
- Average audit score

Actions:

- Edit model
- Edit permissions
- Pause agent
- Test agent
- View logs
- View usage

---

## 5.5 Skills Page

Route:

```txt
/skills
```

Purpose:

Manage reusable BosskuAI skills.

Features:

- List skills
- Search skills
- Filter by category
- Filter by status
- Import skill from markdown
- Import skill from folder
- Import skill from URL
- Validate skill
- Preview skill
- Enable/disable skill
- View usage history

Skill categories:

```txt
Laravel
Nuxt
Docker
Database
Security
Testing
Product
SEO/GEO/AEO
UI/UX
Payment Gateway
API
DevOps
Content
Research
AI Agent
Memory
Orchestration
```

Skill detail shows:

- Skill name
- Description
- Trigger keywords
- Instructions
- Examples
- Related playbooks
- Compatible agents
- Source
- Validation status
- Usage count
- Feedback score
- Related memory

Skill actions:

- Edit
- Validate
- Enable
- Disable
- Duplicate
- Export
- Deprecate

---

## 5.6 Memory Page

Route:

```txt
/memory
```

Purpose:

Human-readable memory browser.

Features:

- Search memory
- Filter by project
- Filter by source
- Filter by type
- Filter by confidence
- Filter stale memory
- Detect duplicates
- Mark memory as wrong
- Merge memory
- Pin memory
- Archive memory
- Delete memory
- Export memory

Memory card fields:

- Title
- Summary
- Source
- Type
- Tags
- Confidence
- Created date
- Updated date
- Last used
- Used by which run
- Embedding status
- Relevance score

Memory types:

```txt
project_fact
user_preference
architecture_decision
bug_fix
command
environment
credential_reference_without_secret
coding_standard
business_context
rejected_approach
feedback_learning
skill_candidate_source
soul_note
```

Memory Health panel:

- Total memories
- Stale memories
- Duplicate memories
- Low-confidence memories
- Missing embeddings
- Most-used memories
- Conflicting memories

---

## 5.7 Logs Page

Route:

```txt
/logs
```

Developer observability logs viewer.

Features:

- Live logs
- Log level filter
- Agent filter
- Run filter
- Model filter
- Provider filter
- Plugin filter
- Search
- Date range
- JSON expansion
- Copy log line
- Download logs

Log levels:

```txt
debug
info
warning
error
critical
```

Each log row:

- Timestamp
- Level
- Source
- Agent
- Run ID
- Message
- Metadata

Support live SSE streaming if existing stack supports it.

---

## 5.8 Usage Page

Route:

```txt
/usage
```

Purpose:

Track token and model cost.

### Usage Summary

Show:

- Total tokens today
- Total tokens this month
- Estimated cost today
- Estimated cost month
- Most expensive model
- Most expensive provider
- Most expensive agent
- Failed run cost
- Average cost per successful run

### Model Usage Table

Fields:

- Model
- Provider
- Role
- Tokens
- Requests
- Cost
- Average latency
- Failure rate

### Agent Usage Table

Fields:

- Agent
- Provider
- Model
- Runs
- Tokens
- Cost
- Success rate
- Average audit score

### Cost Alerts

Allow setting:

- Daily token limit
- Monthly cost limit
- Per-agent cost limit
- Per-run cost limit
- Auto-stop when exceeded

---

## 5.9 Settings: Providers

Route:

```txt
/settings/providers
```

Supported providers:

```txt
openai
codex
anthropic
ollama
ollama-cloud
openrouter
custom-openai-compatible
custom-anthropic-compatible
custom
```

Minimum required provider support:

```txt
anthropic
codex/openai
ollama-cloud
custom-openai-compatible
```

Provider fields:

- Provider name
- Provider type
- Base URL
- API key
- Default model
- Available models
- Timeout
- Max retries
- Rate limit
- Monthly budget
- Daily budget
- Enabled/disabled
- Health status
- Last checked

API key rules:

- Never show raw API key after save
- Store encrypted if saved in database
- Allow env variable fallback
- Show masked value only
- Allow test connection
- Allow rotate key
- Allow delete key

Example config:

```json
{
  "providers": [
    {
      "name": "Anthropic",
      "type": "anthropic",
      "base_url": "https://api.anthropic.com",
      "api_key_env": "ANTHROPIC_API_KEY",
      "default_model": "claude-opus",
      "enabled": true
    },
    {
      "name": "Codex/OpenAI",
      "type": "codex",
      "api_key_env": "OPENAI_API_KEY",
      "default_model": "gpt-5.5",
      "enabled": true
    },
    {
      "name": "Ollama Cloud",
      "type": "ollama-cloud",
      "base_url": "https://api.ollama.ai/v1",
      "api_key_env": "OLLAMA_API_KEY",
      "default_model": "qwen-coder-cloud",
      "enabled": true
    },
    {
      "name": "Custom OpenAI Compatible",
      "type": "custom-openai-compatible",
      "base_url": "https://example.com/v1",
      "api_key_env": "CUSTOM_LLM_API_KEY",
      "default_model": "custom-model",
      "enabled": false
    }
  ]
}
```

---

## 5.10 Settings: Model Routing

Route:

```txt
/settings/model-routing
```

User must be able to assign different provider/model per role.

Roles:

- Orchestrator
- Executor
- Auditor
- Final Reviewer
- Memory Curator
- Skill Generator

Routing table:

| Role | Primary Provider | Primary Model | Fallback Provider | Fallback Model | Budget | Approval Required |
|---|---|---|---|---|---|---|
| Orchestrator | Codex/OpenAI | selected model | Anthropic | selected model | daily/monthly | yes/no |
| Executor | Ollama Cloud | selected model | Custom | selected model | daily/monthly | no |
| Auditor | Anthropic | selected model | Codex/OpenAI | selected model | daily/monthly | yes |
| Final Reviewer | Codex/OpenAI | selected model | Anthropic | selected model | daily/monthly | yes |
| Memory Curator | Ollama Cloud | selected model | OpenAI | selected model | daily/monthly | no |
| Skill Generator | Ollama Cloud | selected model | Anthropic | selected model | daily/monthly | yes |

Routing rules:

```txt
If task is security-sensitive → force auditor.
If task touches payment gateway → force auditor + final reviewer.
If task changes env/config → require approval.
If task is planning only → orchestrator only.
If task is long code generation → executor model.
If task failed audit twice → escalate to stronger model.
If user gives negative feedback twice → route similar tasks to stronger model.
If token budget exceeded → use cheaper fallback or stop.
```

---

# 6. Tier 2: Self-Learning Brain

## Goal

BosskuAI should learn from runs, feedback, memory, repeated patterns, and project conventions.

Learning must produce visible behavior changes.

Bad:

```txt
BosskuAI says it learned but repeats the same mistake.
```

Good:

```txt
User marks output as too verbose.
BosskuAI saves preference.
Future code summaries are shorter.
```

---

## 6.1 Brain Page

Route:

```txt
/brain
```

Tabs:

```txt
Overview
Memory Streams
Learning Inbox
Skill Candidates
Feedback Learnings
Brain Health
Conflicts
```

### Brain Overview

Show:

- Total memories
- Project facts
- User preferences
- Architecture decisions
- Generated skills
- Pending learnings
- Stale memories
- Duplicate memories
- Confidence health
- Learning events this week
- Auto skill candidates

### Memory Streams

Group by:

- User Preferences
- Project Context
- Repo Knowledge
- Architecture Decisions
- Coding Standards
- Business/Product Context
- Model Preferences
- Skill Candidates
- Rejected Approaches
- Feedback Learnings
- Soul / Personality Notes

### Learning Inbox

Show extracted learnings waiting for user review:

- New user preference detected
- New project convention detected
- New skill candidate detected
- Possible contradiction detected
- Outdated memory detected
- Repeated workflow detected

Actions:

- Approve
- Reject
- Edit
- Merge
- Ask me later
- Turn into skill

### Brain Health

Show:

- Stale memories
- Conflicting memories
- Low-confidence memories
- Unused memories
- Overused memories
- Memories without source
- Memory items needing confirmation

### Microcopy

Use clear, practical microcopy:

```txt
New learning detected
BosskuAI noticed this pattern across 4 runs.

Skill candidate ready
This workflow appears reusable. Review before enabling.

Memory conflict found
A newer project rule may contradict an older memory.

Soul update suggested
Recent feedback suggests BosskuAI should be more concise for code summaries.

Model routing improved
Executor was switched to Ollama Cloud because this task was long but low-risk.
```

---

## 6.2 Self-Learning System

Learning lifecycle:

```txt
Run Completed
  ↓
Extract Learnings
  ↓
Classify Knowledge
  ↓
Save to Memory
  ↓
Detect Repeated Pattern
  ↓
Suggest New Skill
  ↓
User Reviews Skill
  ↓
Skill Approved
  ↓
Skill Added to Skill Library
  ↓
Future Runs Use Skill
```

BosskuAI should learn from:

- Successful runs
- Failed runs
- User feedback
- Repeated prompts
- Repeated fixes
- Repeated project decisions
- Repo conventions
- Coding standards
- Imported docs
- Playbooks
- Manual notes
- Audit results
- Rejected approaches
- Preferred models
- Preferred coding style
- Preferred output format

Important:

- Do not auto-approve generated skills.
- Do not auto-apply risky memories.
- Do not learn secrets.
- Do not store raw credentials.

---

## 6.3 Feedback System

Route:

```txt
/feedback
```

Feedback types:

```txt
thumbs_up
thumbs_down
wrong
useful
too_verbose
too_shallow
good_style
bad_style
unsafe
hallucinated
missed_context
save_to_memory
do_not_remember
create_skill_from_this
never_do_this_again
```

Feedback target types:

```txt
run
message
step
memory
skill
audit
file_change
model_route
agent_decision
```

Every feedback item must include:

- Target type
- Target id
- Run id if related
- Feedback type
- Optional comment
- Severity
- Learning status
- Created at
- Processed at

Feedback must update:

- Memory confidence
- Skill confidence
- Agent performance score
- Model routing score
- User preference profile
- Future output style
- Skill generation pipeline

---

## 6.4 Auto Skill Candidate Generation

Trigger skill suggestion when:

- Same task pattern appears 3+ times
- Same repo convention is repeated
- Same bug/fix appears more than once
- User gives positive feedback on a solution
- Auditor repeatedly approves the same approach
- Project-specific workflow becomes stable
- Imported knowledge has enough structure
- User manually selects “Create Skill from This Run”

Generated skill example:

```txt
Detected Pattern:
User often builds Laravel Docker apps with Nuxt frontend, Redis, PostgreSQL, and Fiuu payment gateway.

Generated Skill Draft:
laravel-nuxt-docker-fiuu-payment
```

Generated skill folder structure:

```txt
skills/
  laravel-nuxt-docker-fiuu-payment/
    SKILL.md
    checklist.md
    examples.md
    references.md
    tests.md
    metadata.json
```

`SKILL.md` structure:

```md
---
name: laravel-nuxt-docker-fiuu-payment
description: Use when implementing Fiuu sandbox or production payment gateway in Laravel/Nuxt projects with Docker.
category: payment-gateway
created_by: bosskuai-learning-engine
source: auto-generated-from-runs
confidence: 0.82
approval_status: pending
version: 0.1.0
---

## When to Use

## When Not to Use

## Required Context

## Steps

## Security Rules

## Common Mistakes

## Test Checklist

## Output Format
```

Approval statuses:

```txt
draft
pending_review
approved
rejected
archived
deprecated
```

Actions:

- Approve Skill
- Edit Skill
- Reject Skill
- Merge with Existing Skill
- Export Skill
- Use in Next Run
- Pin as Project Skill

---

## 6.5 soul.md

Add first-class `soul.md`.

Purpose:

`soul.md` defines BosskuAI’s long-term personality, working style, principles, boundaries, and relationship with the user.

It is the AI co-founder companion contract.

Create:

```txt
bossku/
  soul.md
  soul.schema.json
```

Default `soul.md`:

```md
# BosskuAI Soul

## Identity

BosskuAI is a practical AI co-founder companion for developers, founders, and product builders.

## Core Role

BosskuAI helps the user plan, build, audit, improve, and ship software products.

## Working Style

- Be direct.
- Be skeptical.
- Triple-check assumptions.
- Prefer working code over vague advice.
- Prefer structured plans.
- Prefer concise summaries.
- Explain tradeoffs.
- Avoid fake certainty.
- Do not expose hidden chain-of-thought.
- Show safe reasoning summaries only.

## Developer Principles

- Always understand the repo before editing.
- Never overwrite user work without warning.
- Prefer small, reversible changes.
- Run tests when possible.
- Audit security-sensitive changes.
- Track all file changes.
- Link decisions to source context.
- Use memory carefully.
- Ask for approval before dangerous operations.

## Co-Founder Principles

- Think about product, UX, business, and engineering.
- Challenge weak ideas respectfully.
- Suggest better product direction when useful.
- Remember user preferences only when useful.
- Learn from repeated feedback.
- Turn repeated workflows into skills.

## Boundaries

- Do not store secrets.
- Do not reveal hidden chain-of-thought.
- Do not perform destructive actions without approval.
- Do not invent repo facts.
- Do not pretend a task was tested if it was not.

## Output Marker

Every agent message should include:

[BOSSKUAI]
Skill: <detected-skill>
Agent: <orchestrator|executor|auditor|reviewer|memory|skill-engine>
Model Role: <planner|coder|reviewer|researcher|memory-curator>
Memory Used: <yes|no>
Model: <provider/model>
```

Soul page route:

```txt
/soul
```

Features:

- View soul.md
- Edit soul.md
- Version history
- Diff changes
- Approve changes
- Restore previous soul
- Detect contradiction with memory
- Show which runs used this soul version

Rules:

- User can edit soul.md manually.
- BosskuAI can suggest soul.md updates.
- BosskuAI cannot apply soul.md changes without user approval.
- soul.md must be versioned.
- Every run must record which soul.md version was used.

---

# 7. Tier 3: Knowledge Graph and Skills Graph

## Goal

Add visual graph intelligence, but keep it actionable.

Do not build decorative graphs only.

Each graph node should support useful actions like:

- Open run
- Open memory
- Open skill
- Resolve conflict
- Create skill
- Merge skill
- Archive memory
- Update routing rule
- View source

---

## 7.1 Knowledge Graph Page

Route:

```txt
/knowledge-graph
```

Purpose:

Visualize how BosskuAI understands the user, projects, repos, skills, agents, plugins, files, and decisions.

### Node Types

```txt
User
Project
Repository
File
Skill
Agent
Model
Provider
Plugin
Memory
Decision
Run
Tool Call
Feedback
Error
Fix
Audit Finding
Soul Version
```

### Edge Types

```txt
user_prefers
project_uses
repo_contains
skill_triggered_by
agent_used_skill
model_used_by_agent
provider_serves_model
plugin_called_by
memory_used_in_run
decision_created_from
feedback_adjusted
error_fixed_by
audit_flagged
run_changed_file
skill_generated_from_memory
memory_conflicts_with
soul_used_in_run
```

### Features

- Zoom
- Pan
- Search node
- Filter node type
- Filter project
- Filter confidence
- Click node to inspect
- Show confidence
- Show source
- Show last updated
- Show related runs
- Show conflicts
- Export graph as JSON

### Node Inspector

When clicking a node, show:

- Label
- Type
- Summary
- Confidence
- Source
- Related nodes
- Related runs
- Last updated
- Actions

Example actions:

```txt
Open source run
Open related memory
Open related skill
Resolve conflict
Create skill from node
Archive stale memory
```

Use a lightweight Nuxt-compatible graph library.
If dependency risk is high, implement a simple graph view first.

---

## 7.2 Skills Graph Page

Route:

```txt
/skills-graph
```

Purpose:

Visualize all BosskuAI skills and how they connect.

### Node Types

```txt
Skill
Category
Trigger Keyword
Project
Agent
Model
Memory Source
Plugin
Playbook
Checklist
```

### Edge Types

```txt
skill_depends_on
skill_extends
skill_conflicts_with
skill_triggered_by
skill_used_by_agent
skill_generated_from_memory
skill_used_in_project
skill_calls_plugin
skill_requires_model_capability
```

### Features

- Detect overloaded skills
- Detect duplicate skills
- Detect unused skills
- Detect weak descriptions
- Detect skills that should be merged
- Detect skills that need examples
- Show auto-generated skills awaiting approval

### Skill Quality Score

Score from 0–100 based on:

- Description clarity
- Trigger accuracy
- Usage success
- Audit pass rate
- User feedback score
- Token efficiency
- Recency
- Has examples
- Has checklist
- Has clear “When Not To Use”

### Skill Graph Actions

- Open skill
- Edit skill
- Merge duplicate skills
- Deprecate skill
- Approve generated skill
- Reject generated skill
- Add missing example
- Add checklist
- View related runs

---

# 8. Backend Architecture

Use existing Laravel conventions.

Do not rewrite the whole app if existing structure works.

## Suggested Services

```txt
app/
  Services/
    Orchestration/
      OrchestratorService.php
      RunService.php
      RunTimelineService.php
      AgentMessageService.php
      AuditService.php

    Llm/
      Contracts/
        LlmProviderInterface.php
      DTO/
        LlmRequest.php
        LlmResponse.php
        CostEstimate.php
        ProviderHealthStatus.php
      Providers/
        AnthropicProvider.php
        CodexProvider.php
        OllamaCloudProvider.php
        OpenAiCompatibleProvider.php
        CustomProvider.php
      ModelRouter.php
      ModelRegistry.php
      UsageTracker.php
      ProviderHealthChecker.php

    Skills/
      SkillDetector.php
      SkillImporter.php
      SkillValidator.php
      SkillCandidateGenerator.php
      SkillQualityScorer.php

    Memory/
      MemoryService.php
      MemorySearchService.php
      MemoryConfidenceService.php
      MemoryConflictDetector.php

    Learning/
      LearningEngine.php
      FeedbackLearningService.php
      RunLearningExtractor.php

    Graph/
      KnowledgeGraphBuilder.php
      SkillsGraphBuilder.php
      GraphConflictDetector.php

    Soul/
      SoulService.php
      SoulVersionService.php
      SoulSuggestionService.php

    Governance/
      ApprovalGateService.php
      RiskClassifier.php
```

## Provider Interface

```php
interface LlmProviderInterface
{
    public function complete(LlmRequest $request): LlmResponse;

    public function stream(LlmRequest $request): iterable;

    public function listModels(): array;

    public function healthCheck(): ProviderHealthStatus;

    public function estimateCost(LlmRequest $request): CostEstimate;
}
```

All agent LLM calls must go through `ModelRouter`.

Do not hardcode one provider everywhere.

---

# 9. Database Tables

Use existing tables if present. Add missing migrations only.

## runs

```txt
id
title
prompt
status
selected_skill
memory_used
current_agent
soul_version_id
started_at
completed_at
failed_at
total_tokens
estimated_cost
audit_score
risk_level
metadata_json
created_at
updated_at
```

## run_steps

```txt
id
run_id
sequence
agent
provider
model
skill
status
title
summary
safe_reasoning_summary
started_at
completed_at
input_tokens
output_tokens
cost
metadata_json
created_at
updated_at
```

## agent_messages

```txt
id
run_id
step_id
agent
provider
model
role
skill
memory_used
content
safe_reasoning_summary
metadata_json
created_at
updated_at
```

## tool_calls

```txt
id
run_id
step_id
tool_name
plugin_name
input_summary
output_summary
status
duration_ms
error_message
retry_count
metadata_json
created_at
updated_at
```

## file_changes

```txt
id
run_id
step_id
file_path
change_type
patch
reason
agent
audit_note
approval_status
created_at
updated_at
```

## skills

```txt
id
name
slug
category
description
trigger_keywords_json
content
source
version
enabled
validation_status
approval_status
confidence
usage_count
feedback_score
quality_score
last_used_at
metadata_json
created_at
updated_at
```

## skill_candidates

```txt
id
name
slug
category
description
generated_from
source_run_ids_json
source_memory_ids_json
draft_content
confidence
approval_status
reviewed_by
reviewed_at
metadata_json
created_at
updated_at
```

## plugins

```txt
id
name
type
manifest_json
status
permissions_json
config_json
last_heartbeat_at
last_error
created_at
updated_at
```

## memory_items

```txt
id
title
summary
content
type
source
tags_json
confidence
embedding_status
relevance_score
pinned
stale
last_used_at
metadata_json
created_at
updated_at
```

## usage_events

```txt
id
run_id
step_id
agent
model
provider
prompt_tokens
completion_tokens
total_tokens
estimated_cost
latency_ms
created_at
updated_at
```

## logs

```txt
id
level
source
agent
run_id
message
metadata_json
created_at
updated_at
```

## llm_providers

```txt
id
name
type
base_url
api_key_encrypted
api_key_env
default_model
enabled
health_status
last_health_check_at
metadata_json
created_at
updated_at
```

## model_routes

```txt
id
role
primary_provider_id
primary_model
fallback_provider_id
fallback_model
budget_daily
budget_monthly
approval_required
routing_rules_json
enabled
created_at
updated_at
```

## feedback_items

```txt
id
target_type
target_id
run_id
feedback_type
comment
severity
learning_status
processed_at
metadata_json
created_at
updated_at
```

## learning_events

```txt
id
source_type
source_id
event_type
summary
confidence
status
proposed_memory_id
proposed_skill_id
metadata_json
created_at
updated_at
```

## soul_versions

```txt
id
version
content
summary
change_reason
created_by
approved_by
approved_at
active
created_at
updated_at
```

## knowledge_graph_nodes

```txt
id
node_type
label
summary
source_type
source_id
confidence
metadata_json
created_at
updated_at
```

## knowledge_graph_edges

```txt
id
from_node_id
to_node_id
edge_type
weight
confidence
source_type
source_id
metadata_json
created_at
updated_at
```

---

# 10. API Endpoints

Add or update these endpoints.

## Dashboard

```txt
GET /api/dashboard
```

## Runs

```txt
GET    /api/runs
POST   /api/runs
GET    /api/runs/{id}
GET    /api/runs/{id}/timeline
GET    /api/runs/{id}/messages
GET    /api/runs/{id}/tool-calls
GET    /api/runs/{id}/file-changes
GET    /api/runs/{id}/audit
GET    /api/runs/{id}/usage
GET    /api/runs/{id}/feedback
POST   /api/runs/{id}/pause
POST   /api/runs/{id}/resume
POST   /api/runs/{id}/rerun-step
POST   /api/runs/{id}/send-to-auditor
POST   /api/runs/{id}/create-skill
```

## Agents

```txt
GET    /api/agents
PATCH  /api/agents/{id}
POST   /api/agents/{id}/test
```

## Skills

```txt
GET    /api/skills
GET    /api/skills/{id}
POST   /api/skills/import
POST   /api/skills/validate
PATCH  /api/skills/{id}
GET    /api/skills/candidates
POST   /api/skills/candidates/{id}/approve
POST   /api/skills/candidates/{id}/reject
POST   /api/skills/candidates/{id}/merge
```

## Plugins

```txt
GET    /api/plugins
POST   /api/plugins/import
POST   /api/plugins/{id}/test
PATCH  /api/plugins/{id}
```

## Memory

```txt
GET    /api/memory
PATCH  /api/memory/{id}
DELETE /api/memory/{id}
POST   /api/memory/{id}/pin
POST   /api/memory/{id}/archive
POST   /api/memory/{id}/mark-wrong
POST   /api/memory/merge
```

## Brain

```txt
GET    /api/brain
GET    /api/brain/health
GET    /api/brain/learning-inbox
POST   /api/brain/learning-events/{id}/approve
POST   /api/brain/learning-events/{id}/reject
POST   /api/brain/learning-events/{id}/create-skill
```

## Knowledge Graph

```txt
GET  /api/knowledge-graph
GET  /api/knowledge-graph/nodes
GET  /api/knowledge-graph/edges
POST /api/knowledge-graph/rebuild
```

## Skills Graph

```txt
GET  /api/skills-graph
POST /api/skills-graph/rebuild
```

## Soul

```txt
GET   /api/soul
PATCH /api/soul
GET   /api/soul/versions
POST  /api/soul/versions/{id}/activate
POST  /api/soul/suggest-update
POST  /api/soul/approve-suggestion
```

## Feedback

```txt
GET  /api/feedback
POST /api/feedback
POST /api/feedback/{id}/process
```

## Logs

```txt
GET /api/logs
GET /api/logs/stream
```

## Usage

```txt
GET /api/usage
```

## Providers

```txt
GET    /api/providers
POST   /api/providers
PATCH  /api/providers/{id}
DELETE /api/providers/{id}
POST   /api/providers/{id}/test
POST   /api/providers/{id}/sync-models
```

## Model Routing

```txt
GET   /api/model-routing
PATCH /api/model-routing/{role}
POST  /api/model-routing/test
```

## Settings

```txt
GET   /api/settings
PATCH /api/settings
```

---

# 11. Frontend Components

Create reusable Nuxt components.

```txt
components/
  app/
    AppShell.vue
    Sidebar.vue
    TopBar.vue
    CommandBar.vue
    InspectorPanel.vue
    BottomLogDrawer.vue

  dashboard/
    KpiCard.vue
    AgentStatusCard.vue
    LiveFeed.vue
    RecentRunsTable.vue
    BrainSnapshot.vue

  runs/
    RunCard.vue
    RunTimeline.vue
    RunStepCard.vue
    AgentMessageCard.vue
    PlanChecklist.vue
    ToolCallCard.vue
    FileDiffViewer.vue
    AuditScoreCard.vue
    UsageBreakdown.vue
    FeedbackPanel.vue

  agents/
    AgentCard.vue
    AgentConfigDrawer.vue

  skills/
    SkillCard.vue
    SkillImportDialog.vue
    SkillValidator.vue
    SkillCandidateCard.vue
    SkillQualityBadge.vue

  plugins/
    PluginCard.vue
    PluginImportDialog.vue
    PluginPermissionList.vue

  memory/
    MemoryCard.vue
    MemorySearch.vue
    MemoryHealthPanel.vue
    MemoryConflictCard.vue

  brain/
    BrainOverview.vue
    MemoryStreamList.vue
    LearningInbox.vue
    BrainHealthPanel.vue
    FeedbackLearningCard.vue

  graph/
    KnowledgeGraph.vue
    SkillsGraph.vue
    GraphNodeInspector.vue
    GraphFilterBar.vue
    GraphLegend.vue

  soul/
    SoulEditor.vue
    SoulVersionTimeline.vue
    SoulSuggestionCard.vue
    SoulDiffViewer.vue
    SoulUsageTable.vue

  feedback/
    FeedbackButtons.vue
    FeedbackDrawer.vue
    FeedbackTable.vue
    FeedbackTrendPanel.vue

  logs/
    LogViewer.vue
    LogFilterBar.vue

  usage/
    UsageSummary.vue
    ModelUsageTable.vue
    AgentUsageTable.vue
    CostAlertPanel.vue

  providers/
    ProviderCard.vue
    ProviderForm.vue
    ApiKeyInput.vue
    ProviderHealthBadge.vue
    ModelSelector.vue
    ModelRoutingTable.vue
    RoutingRuleBuilder.vue
```

Composables:

```txt
composables/
  useDashboard.ts
  useRuns.ts
  useRunStream.ts
  useAgents.ts
  useSkills.ts
  usePlugins.ts
  useMemory.ts
  useBrain.ts
  useKnowledgeGraph.ts
  useSkillsGraph.ts
  useLogs.ts
  useUsage.ts
  useFeedback.ts
  useSoul.ts
  useProviders.ts
  useModelRouting.ts
  useSettings.ts
```

Types:

```txt
types/
  dashboard.ts
  run.ts
  agent.ts
  skill.ts
  plugin.ts
  memory.ts
  brain.ts
  graph.ts
  usage.ts
  log.ts
  feedback.ts
  soul.ts
  provider.ts
  model-routing.ts
```

---

# 12. Command Palette

Add Ctrl/Cmd + K command palette.

Commands:

```txt
New Run
Open Current Run
Import Skill
Import Plugin
Search Memory
Open Brain
Open Knowledge Graph
Open Skills Graph
View Logs
Change Model
Pause All Agents
Run Audit
Create Skill from Run
Open Settings
Open soul.md
```

---

# 13. Status Badges

Use consistent badges:

```txt
Running
Waiting
Completed
Failed
Needs Approval
Auditing
Fixing
Paused
Learning
Skill Candidate
Memory Conflict
Provider Offline
Fallback Used
Low Confidence
High Risk
```

---

# 14. Agent Route Visual

Show route like:

```txt
Prompt
  ↓
Soul · v1.2
  ↓
Orchestrator · Codex/OpenAI
  ↓
Skill Detector · laravel
  ↓
Memory Engine · 4 facts
  ↓
Executor · Ollama Cloud/qwen-coder
  ↓
Auditor · Anthropic/Claude
  ↓
Final Reviewer · Codex/OpenAI
  ↓
Learning Engine · skill candidate created
```

---

# 15. Seed and Demo Data

Add realistic seed/demo data.

Seed examples:

## Runs

```txt
Add Fiuu sandbox payment to Laravel app
Audit Docker deployment config
Generate Nuxt dashboard UI
Refactor Laravel repository pattern
Create SEO/GEO content plan
```

## Skills

```txt
Laravel Docker
Fiuu Payment Gateway
Nuxt UI/UX
Security Audit
BosskuAI Memory Orchestration
```

## Plugins

```txt
Cursor
GitHub
Docker
Terminal
OpenCode
Claude Code
Codex
```

## Memories

```txt
User prefers concise implementation summaries.
BosskuAI repo uses Laravel backend and Nuxt frontend.
Payment gateway tasks require auditor and final reviewer.
Generated skills must be approved before use.
```

## Learning Events

```txt
Repeated Fiuu payment workflow detected.
User prefers short final summaries.
Docker deployment tasks often need security review.
```

## Skill Candidates

```txt
laravel-nuxt-docker-payment
bosskuai-provider-routing
bosskuai-memory-learning-loop
```

## Graph Data

```txt
User → prefers → concise summaries
Project → uses → Laravel
Project → uses → Nuxt
Run → used skill → Fiuu Payment Gateway
Run → changed file → PaymentController.php
Auditor → flagged → callback signature verification
Memory → generated skill candidate → laravel-nuxt-docker-payment
```

---

# 16. Documentation

Update README and add docs.

README must explain:

1. What BosskuAI is
2. Why it exists
3. How it differs from Cursor, Claude Code, Codex, Hermes, Paperclip
4. Core loop: Run → Plan → Execute → Audit → Learn
5. Agent roles
6. Skills
7. Memory
8. Brain
9. Feedback learning
10. Auto skill creation
11. soul.md
12. Knowledge Graph
13. Skills Graph
14. Model providers
15. Model routing
16. Usage/cost tracking
17. Approval gates
18. Safety rules
19. Docker setup
20. Local development
21. Running seed data
22. Testing
23. API key safety
24. Why BosskuAI does not expose hidden chain-of-thought

Add docs:

```txt
docs/
  architecture.md
  orchestration.md
  self-learning.md
  skills.md
  auto-skill-generation.md
  memory.md
  brain.md
  knowledge-graph.md
  skills-graph.md
  soul.md
  model-routing.md
  providers.md
  governance.md
  approval-gates.md
  usage-and-cost.md
```

---

# 17. Implementation Order

Follow this order strictly.

## Phase 0: Audit

1. Inspect current repo structure.
2. Identify existing backend/frontend conventions.
3. Identify existing Docker flow.
4. Identify existing routes, models, migrations, services, pages, and components.
5. Do not delete working functionality.

## Phase 1: Backend Foundation

1. Add/update migrations.
2. Add/update Eloquent models and relationships.
3. Add provider abstraction.
4. Add model router.
5. Add usage tracker.
6. Add logs service.
7. Add governance approval service.
8. Add core API endpoints.

## Phase 2: Tier 1 UI

1. Build app shell.
2. Build Dashboard.
3. Build Runs page.
4. Build Run Detail page.
5. Build Agents page.
6. Build Skills page.
7. Build Memory page.
8. Build Logs page.
9. Build Usage page.
10. Build Settings Providers and Model Routing.

## Phase 3: Tier 2 Learning

1. Add feedback system.
2. Add learning events.
3. Add Brain page.
4. Add auto skill candidate generation.
5. Add skill approval workflow.
6. Add soul.md and Soul page.
7. Add memory confidence/conflict handling.

## Phase 4: Tier 3 Graphs

1. Add knowledge graph tables/services.
2. Add skills graph services.
3. Add graph API endpoints.
4. Build Knowledge Graph UI.
5. Build Skills Graph UI.
6. Add graph node inspector.
7. Add graph actions.
8. Add duplicate/conflict detection.

## Phase 5: Polish and Docs

1. Add seed/demo data.
2. Add tests where existing setup allows.
3. Update README.
4. Add docs.
5. Run formatting.
6. Run linting.
7. Run tests.
8. Run Docker smoke test.
9. Summarize changes.

---

# 18. Acceptance Criteria

## Tier 1 Acceptance Criteria

1. Dashboard shows real or seeded orchestration data.
2. Runs page can list, search, filter, and sort runs.
3. Run Detail page shows overview, timeline, messages, plan, tool calls, file changes, memory, audit, usage, and feedback.
4. Agent messages show `[BOSSKUAI]` marker.
5. Raw hidden chain-of-thought is never shown.
6. Skill detection result is visible per run.
7. Memory loaded is visible per run.
8. Model/provider route is visible per run step.
9. Tool calls are logged.
10. File changes are tracked.
11. Audit score and findings are visible.
12. Usage/cost is tracked by agent/model/provider.
13. Provider settings support Anthropic, Codex/OpenAI, Ollama Cloud, and custom OpenAI-compatible endpoints.
14. Model routing can assign model per role.
15. API keys are encrypted or loaded from env and never displayed raw.
16. Dangerous operations require approval.
17. Logs page supports filtering.
18. Existing Docker setup still works.

## Tier 2 Acceptance Criteria

1. Brain page exists.
2. Learning inbox exists.
3. Feedback system works across runs/messages/memory/skills/audit/model routing.
4. Feedback can create or update memory.
5. Feedback can influence future output style.
6. Learning events can be approved/rejected.
7. Auto skill candidate generation exists.
8. Skill candidates can be approved/rejected/edited/merged.
9. `soul.md` exists.
10. Soul page can view/edit/version `soul.md`.
11. BosskuAI can suggest `soul.md` updates.
12. BosskuAI cannot apply `soul.md` updates without approval.
13. Memory confidence and conflicts are visible.
14. Generated skills are not auto-approved for risky tasks.

## Tier 3 Acceptance Criteria

1. Knowledge Graph page exists.
2. Knowledge graph shows user/project/run/memory/skill/agent/model/plugin relationships.
3. Knowledge graph nodes are inspectable.
4. Knowledge graph nodes have useful actions.
5. Skills Graph page exists.
6. Skills graph shows skill/category/trigger/agent/model/memory relationships.
7. Skill quality score exists.
8. Duplicate/unused/weak skills are detectable.
9. Graphs are useful, not decorative only.
10. Graph data can be rebuilt through API.

## General Acceptance Criteria

1. Seed/demo data makes local UI look complete.
2. README explains the product clearly.
3. Docs are added.
4. Tests are added where practical.
5. No secrets are committed.
6. Existing integrations/docs are not broken.
7. Final implementation summary is specific.

---

# 19. Final Output Required From Cursor

After implementation, provide:

```txt
1. Summary of changes
2. Files created
3. Files modified
4. Database migrations added
5. API endpoints added
6. Frontend pages added
7. Components added
8. Services added
9. Provider/model routing changes
10. Learning engine changes
11. Graph features added
12. How to run locally
13. How to run seed data
14. How to test
15. Docker smoke test result
16. Known limitations
17. Next recommended improvements
```

Do not provide vague summary only.
Be specific.

---

# 20. Final Product Statement

Ensure the README and UI communicate this clearly:

> BosskuAI is not just an AI coding dashboard. It is a self-learning developer AI co-founder: it remembers project context, learns user preferences, converts repeated workflows into approved skills, visualizes its memory through a brain and graphs, routes each agent role to the best available LLM, audits work, tracks cost, and shows developers exactly what happened.

Build BosskuAI as a serious developer tool.

Focus on the loop:

```txt
Plan → Execute → Audit → Learn → Improve next run
```
