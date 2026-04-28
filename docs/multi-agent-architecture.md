# Multi-Agent Architecture (Claude Code, Codex, Cursor)

## TL;DR

BosskuAI runs as **single-call routing** by default on every surface. On Claude Code only, three opt-in commands (`/audit`, `/decide`, `/implement`) run **multi-agent flows** for high-stakes work. On Codex and Cursor, these commands are documented here as patterns the user can apply manually but are not natively dispatched.

This is the honest architecture. "Every skill is an agent, cofounder is the orchestrator" sounds better in a diagram than it works in practice — see [Why not full multi-agent](#why-not-full-multi-agent) below.

## Surface capabilities

| Capability | Claude Code | Codex CLI | Cursor |
|---|---|---|---|
| Single-call skill loading | ✅ via skill-index.json | ✅ via AGENTS.md | ✅ via .cursor/rules |
| Slash commands | ✅ `.claude/commands/*.md` | ❌ (use prompt patterns) | ❌ (use prompt patterns) |
| Sub-agent dispatch (Task tool) | ✅ native | ⚠️ workaround via shell + API | ❌ not supported |
| Parallel sub-agent execution | ✅ | ⚠️ user-implemented | ❌ |
| Review/critique as separate call | ✅ via `/decide`, `/implement` | ⚠️ manual prompt chaining | ❌ |
| Workspace-wide rules | ✅ `.claude/rules/` + `CLAUDE.md` | ✅ `.codex/AGENTS.md` | ✅ `.cursor/rules/*.mdc` |

The same workspace serves all three surfaces. The slash commands are a Claude Code superpower; on Codex and Cursor the same workflows exist but the user invokes them by hand.

## Default flow (every surface, every request)

```
User request
   │
   ▼
Cofounder skill (or direct specialist if domain is obvious)
   │
   ├─ Reads request, picks 1 primary specialist + at most 1 secondary
   │
   ▼
Single model call with the loaded skill content
   │
   ▼
Answer
```

Cost: 1× call. Latency: 5–15s. Use for the vast majority of requests.

This is what you already had. Nothing changed about it.

## Deep-mode flows (Claude Code only, opt-in)

### `/audit` — fan-out parallel review

```
User: /audit checkout flow
   │
   ▼
Cofounder frames the audit + picks 2-4 specialists
   │
   ├──► Sub-agent: laravel-development reviews backend
   ├──► Sub-agent: cybersecurity-risk reviews threat surface
   ├──► Sub-agent: database-engineering reviews schema/queries
   │      (parallel, each loads ONE skill in isolation)
   │
   ▼
Cofounder synthesizes findings, forces decisions on disagreements
   │
   ▼
Combined audit report
```

Cost: 3–5× call. Latency: 30–90s. Use for cross-domain audits where a single specialist would miss things.

**Why this works**: Each sub-agent has focused context (one skill, one playbook, the artifact). One model trying to play 3 roles in one prompt has divided attention. Three focused calls produce better coverage.

**Why this fails if misused**: synthesis is the hard part. If the cofounder doesn't force a decision when sub-agents disagree, the report becomes an unprioritized list of complaints. The `/audit` command explicitly requires the synthesis to call out disagreements and decide.

### `/decide` — propose-then-critique

```
User: /decide should we add team workspaces?
   │
   ▼
Cofounder generates recommendation (standard answer contract)
   │
   ▼
Sub-agent: skeptical critique against failure-modes table
   │      (separate call, separate role, separate prompt)
   ▼
Cofounder revises (or defends with evidence)
   │
   ▼
Final recommendation + one-line note on what changed
```

Cost: 2× call. Latency: +10–25s. Use for decisions that are hard to undo.

**Why this works**: a single model critiquing its own output is sycophantic. A separate call with the failure-modes table from `cofounder-decision-quality-playbook.md` gives a real second opinion. Cost is low because it's only 1 extra call, not parallel fan-out.

### `/implement` — write-then-review

```
User: /implement <task>
   │
   ▼
/plan first if non-obvious
   │
   ▼
Implementer call writes the diff + tests
   │
   ▼
Sub-agent: rigorous-code-review against the relevant specialist playbook's anti-patterns
   │
   ▼
Implementer revises (fix or defend each finding)
   │
   ▼
Verification step from the framing
```

Cost: 2× call. Latency: +15–40s. Use for non-trivial diffs touching payments, auth, multi-tenancy, migrations, queues, webhooks.

**Why this works**: same code-author / code-reviewer separation that humans practice. The reviewer call uses `bosskuai-rigorous-code-review` rules and the relevant specialist playbook's worked anti-patterns. Catches things the author missed because the author has author bias.

## Why not full multi-agent

"Every skill becomes an agent, cofounder dispatches everything in parallel" sounds appealing. It produces worse results in practice for most requests. Concretely:

1. **Latency multiplies.** 5 parallel sub-agents + synthesis = 30–60s. Founders abandon flows that slow.
2. **Cost multiplies.** 5–10× tokens per question.
3. **Synthesis is the bottleneck.** Combining 5 specialist outputs into one coherent recommendation is itself hard. Most implementations split-the-difference, which is worse than picking one specialist and committing.
4. **Error compounds.** If each specialist is 90% reliable, all-of-5-correct is 0.9⁵ = 59%. One bad sub-agent poisons the synthesis.
5. **Specialist agents = same model reading different docs.** There's no separate "Laravel expert model." It's the same Claude with different context. The gain is focused-context reasoning, which is real but small for single-domain questions.
6. **Debugging gets harder.** Single-call: prompt is the only variable. Multi-agent: routing, each specialist, synthesis, rubric — many ways to fail.

The math only works when:
- Cross-domain coverage genuinely matters (audit case).
- Single-call sycophancy is the failure mode (critique case).
- Author bias is the failure mode (review case).

The three slash commands target exactly those cases. Every other request is single-call.

## Patterns for Codex and Cursor

These surfaces don't dispatch sub-agents natively. Same patterns work, the user invokes them manually:

### `/audit` pattern on Codex/Cursor

Run the audit as 2–4 separate prompts, one per specialist, then a synthesis prompt:

```
Prompt 1: "Load bosskuai-laravel-development. Audit ONLY the backend correctness of this code: <paste>. Output the findings table only."

Prompt 2: "Load bosskuai-cybersecurity-risk. Audit ONLY the threat surface of the same code. Output the findings table only."

Prompt 3: "Load bosskuai-database-engineering. Audit ONLY the schema and queries. Output the findings table only."

Synthesis: "Here are three audit reports. Combine them into one prioritized list. Where they disagree, force a decision based on stage = MVP / pre-revenue."
```

Slower than `/audit` on Claude Code (you do the routing) but the result is the same.

### `/decide` pattern on Codex/Cursor

Two prompts:

```
Prompt 1: "Apply cofounder skill. Recommend: <decision>. Use the standard answer contract."

Prompt 2: "Here is the recommendation: <paste>. Apply the failure-modes table from cofounder-decision-quality-playbook. Find the strongest objection. Output: failure mode / evidence missing / counter-recommendation."

Manual: revise the recommendation based on the critique.
```

### `/implement` pattern on Codex/Cursor

Two prompts:

```
Prompt 1: "Implement <task>. Load the relevant specialist. Write code + tests."

Prompt 2: "Here is the diff: <paste>. Apply bosskuai-rigorous-code-review and the relevant specialist playbook's worked anti-patterns. Find what's wrong."

Manual: apply the review findings, re-run tests.
```

## Measuring whether deep-mode is actually better

Multi-agent flows feel sophisticated. The only honest way to know if they produce better answers is to run `eval_llm_quality.py` (from v1.8.4) twice on the same task — once single-call, once with the relevant slash command — and compare graded scores.

The 7-task case bank is enough to detect a real improvement. If `/decide` doesn't outscore single-call cofounder mode by at least 0.10 average score, it's not earning its cost. If `/audit` doesn't catch findings that single-call missed, it's theater.

Run the comparison before committing to deep-mode by default. The architecture is opt-in for exactly this reason.

## When NOT to use deep-mode

- Routine questions with one obvious specialist. (e.g. "how do I add a Stripe webhook?" — that's `/implement`-shaped only if the diff is non-trivial; otherwise just answer.)
- Time-sensitive requests. ("Is the deploy broken?" — answer fast, audit later.)
- Requests where the artifact isn't in context yet. Get the artifact first.
- Anything where the cost or latency hit isn't justified by the stakes.

The default is single-call. Deep-mode is a tool, not a default.
