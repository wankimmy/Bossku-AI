---
name: bosskuai-ai-model-selection
description: Use this for recommending which AI model fits a specific task based on reasoning depth, speed, tool use, coding needs, multimodality, cost sensitivity, and reliability tradeoffs.
---

# BosskuAI AI Model Selection

Use this skill when the user wants to know **which AI model fits a specific task** — or when BosskuAI needs to select a model for a handoff (pair with `bosskuai-context-limit-continuation`).

## How this differs from nearby skills

- **`bosskuai-context-limit-continuation`**: handles the handoff mechanics when context is exhausted; this skill recommends *which model* to use for the remaining work. Load both together at handoff.
- **`bosskuai-cross-model-escalation`**: decides when to get another model involved because the current one is blocked or brittle; this skill chooses the best helper model once that escalation is justified.

## Maintenance (time-sensitive)

**Annual review (required):** Refresh model names, capability claims, and default picks against current vendor docs at least once per calendar year — **recommended window: Q1**. The roster below is a template until re-verified.

**Last reviewed:** 2026-03.

## Current model roster (verify at cutoff August 2025 — re-check for newer releases)

### Anthropic / Claude
| Model | Strengths | Best for | Notes |
|-------|-----------|----------|-------|
| **claude-opus-4-6** | Deepest reasoning, nuance, complex multi-step logic | Architecture, strategy, ambiguous analysis, long research | Highest cost; use when reasoning quality has clear payoff |
| **claude-sonnet-4-6** | Fast, capable, excellent coding and tool use | Implementation, code gen, review, structured tasks | Best cost/capability tradeoff for most agent work |
| **claude-haiku-4-5** | Very fast, low cost | Simple extraction, classification, summarization, high-volume tasks | Not for complex reasoning |

### OpenAI / GPT
| Model | Strengths | Best for | Notes |
|-------|-----------|----------|-------|
| **gpt-4o** | Multimodal (vision, audio), strong coding | Vision tasks, voice pipelines, general-purpose coding | Strong function calling |
| **o3** | Deep reasoning, math, science | Hard logic problems, research synthesis, PhD-level tasks | Slow, expensive; for genuinely hard problems |
| **o4-mini** | Fast reasoning, cost-efficient | Coding, math, structured reasoning where o3 is overkill | Good reasoning/cost balance |

### Google / Gemini
| Model | Strengths | Best for | Notes |
|-------|-----------|----------|-------|
| **gemini-2.5-pro** | Massive 1M+ context, strong coding | Very long documents, large codebases, video analysis | Best-in-class context window |
| **gemini-2.5-flash** | Fast, multimodal, low cost | High-volume tasks, mobile-first applications | Good for latency-sensitive pipelines |

### BosskuAI default model split (from CLAUDE.md)
- **Planning / strategy / architecture / analysis** → `claude-opus-4-6`
- **Implementation / execution / codegen** → `claude-sonnet-4-6`

## Workflow

1. **Classify the task requirements** — Score each dimension:
   - Reasoning depth: shallow (extraction, formatting) / medium (structured analysis) / deep (ambiguous, multi-step, novel)
   - Speed sensitivity: latency-critical / interactive / async batch
   - Cost sensitivity: high-volume / exploratory / one-off
   - Modality: text / code / vision / audio / video / long-doc
   - Context length: < 32k tokens / 32k–200k / 200k+
   - Tool use: needs function calling / browser / code interpreter?
   - Reliability: production-critical / experimental

2. **Identify the primary failure mode** — What goes wrong if the wrong model is chosen? (low quality, too slow, too expensive, can't process the modality)

3. **Recommend primary + fallback**:
   - **Primary**: best fit for the task profile above
   - **Fallback**: next-best if primary is unavailable, over budget, or over context

4. **Explain the tradeoff** in terms of:
   - Capability: what will be better or worse?
   - Latency: how much slower/faster?
   - Cost: rough relative cost difference?
   - Reliability: any known limitations for this task type?

5. **Flag time-sensitivity** — Model capabilities change fast. If this recommendation depends on current benchmarks or pricing, say so and recommend re-checking.

**If the current model is already mid-task and stuck:** pair this skill with **`bosskuai-cross-model-escalation`** so the helper request is scoped tightly instead of becoming a vague tool switch.

## Task-to-model quick reference

| Task type | Recommended model |
|-----------|------------------|
| Complex architecture / strategy | claude-opus-4-6 |
| Code generation / implementation | claude-sonnet-4-6 |
| PR review / code analysis | claude-sonnet-4-6 |
| Long document analysis (>100k tokens) | gemini-2.5-pro |
| Vision / image understanding | gpt-4o or claude-sonnet-4-6 |
| Hard math / logic reasoning | o3 or o4-mini |
| High-volume extraction / classification | claude-haiku-4-5 or gemini-2.5-flash |
| Agent tool use (multi-step tasks) | claude-sonnet-4-6 or gpt-4o |
| Voice / audio pipeline | gpt-4o |

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not recommend Opus for mechanical tasks where Sonnet is sufficient — cost and latency matter.
- Do not recommend the cheapest model for tasks where reasoning quality directly affects correctness.
- Always provide a fallback — tool availability changes.
- Caveat time-sensitive claims: "as of August 2025."
- If the active model is blocked mid-task, do not stop at model naming alone; pair with `bosskuai-cross-model-escalation` and define the exact helper question.

## Output format

```
Task profile:
  Reasoning: [shallow / medium / deep]
  Speed: [latency-critical / interactive / async]
  Modality: [text / code / vision / long-doc / etc.]
  Context length needed: [estimate]
  Tool use needed: [yes/no]
Primary recommendation: [model name] — [one-line reason]
Fallback: [model name] — [one-line reason]
Tradeoff: [capability / latency / cost / reliability]
Caveats: [any time-sensitive claims]
```

## References

- `../../references/playbooks/model-selection-playbook.md`
- `../../references/checklists/model-selection-checklist.md`
- Pair with **`bosskuai-cross-model-escalation`** when the current model needs backup mid-task
