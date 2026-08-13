---
name: bosskuai-ai-model-selection
description: Use this for recommending which AI model fits a specific task based on reasoning depth, speed, tool use, coding needs, multimodality, cost sensitivity, and reliability tradeoffs.
---

# BosskuAI AI Model Selection

Use this skill when the user wants to know **which AI model fits a specific task**, or when BosskuAI needs to select a model for a handoff (pair with `bosskuai-context-limit-continuation`).

## How this differs from nearby skills

- **`bosskuai-context-limit-continuation`**: handles the handoff mechanics when context runs out; this skill picks *which model* finishes the work. Load both at handoff.
- **`bosskuai-cross-model-escalation`**: decides *when* to bring in another model because the current one is blocked; this skill chooses the helper once that call is made.
- **`bosskuai-cost-optimization`**: sets the spend envelope; this skill picks the model that fits inside it.

## Verify before quoting

Model rosters move faster than this file. For anything Claude/Anthropic — model ids, pricing, context windows, thinking and effort behavior — **load the `claude-api` skill rather than answering from memory**; it carries the current cached table and a live Models API lookup. For OpenAI, Google, or other vendors, check the vendor's own docs. Never invent a version number: a confidently wrong model id costs more than saying "verify this".

## Claude roster

Current as of 2026-08. Ids are complete as written — never append date suffixes.

| Model | Id | Context | $/1M in | $/1M out | Best for |
|---|---|---|---|---|---|
| Claude Fable 5 | `claude-fable-5` | 1M | 10.00 | 50.00 | The hardest reasoning and longest-horizon agentic runs |
| Claude Opus 5 | `claude-opus-5` | 1M | 5.00 | 25.00 | **Default.** Agentic coding, architecture, deep analysis |
| Claude Sonnet 5 | `claude-sonnet-5` | 1M | 3.00 | 15.00 | High-volume production work at near-Opus quality |
| Claude Haiku 4.5 | `claude-haiku-4-5` | 200K | 1.00 | 5.00 | Extraction, classification, summarization at volume |

Default to `claude-opus-5` unless the user names another model. Never downgrade for cost on the user's behalf — that is their call.

## The controls that matter more than the model

Picking a bigger model is the expensive lever. Reach for these first:

- **Effort** (`output_config: {effort: ...}`): `low` | `medium` | `high` | `xhigh` | `max`, default `high`. This is the primary intelligence/latency/cost dial. `xhigh` suits coding and agentic work; `low` and `medium` are unusually strong on current models and are the main cost saving. Sweep it on real tasks — defaults carried over from an older model rarely transfer.
- **Adaptive thinking** (`thinking: {type: "adaptive"}`): the model decides depth per request. Fixed `budget_tokens` is removed on current models and returns a 400.
- **Prompt caching**: repeated context bills at roughly a tenth of input price. Often a larger saving than switching model tier.
- **Batch processing**: half price for work with no latency requirement.

A Sonnet-tier model at `xhigh` with caching frequently beats an Opus-tier model at default effort on both quality and cost. Test before assuming the tier is the bottleneck.

## Task profile

Score the request on these before recommending:

- **Reasoning depth**: shallow (extraction, formatting) / medium (structured analysis) / deep (ambiguous, multi-step, novel)
- **Speed sensitivity**: latency-critical / interactive / async batch
- **Cost sensitivity**: high-volume / exploratory / one-off
- **Modality**: text / code / vision / long document
- **Context needed**: fits comfortably / near the window / needs compaction
- **Tool use**: none / function calling / multi-step agentic
- **Reliability**: production-critical / experimental

Then name the **primary failure mode** if the choice is wrong — low quality, too slow, too expensive, or cannot handle the modality. That failure mode, not the benchmark, decides the tier.

## Guardrails

- Do not recommend the top tier for mechanical work where a cheaper model plus higher effort suffices.
- Do not recommend the cheapest model where reasoning quality directly affects correctness — money, auth, data integrity, medical, or legal output.
- Always give a fallback: availability, rate limits, and refusals all happen. Rate limits are per-model, so a fallback in the same tier may not have headroom.
- Do not present benchmark scores as decisive for a specific task. Test on the actual workload.
- Date any claim that depends on current pricing or capability.

## Output format

```text
Task profile:
  Reasoning: [shallow / medium / deep]
  Speed: [latency-critical / interactive / async]
  Modality: [text / code / vision / long-doc]
  Context needed: [estimate]
  Tool use: [none / function calling / agentic]

Primary failure mode if wrong: [what breaks]

Primary: [model id] at effort [level] - [one-line reason]
Fallback: [model id] - [when it takes over]

Cheaper levers tried first: [effort / caching / batching]
Tradeoff: [capability / latency / cost / reliability]
Verified against: [claude-api skill / vendor docs / not verified]
```

## References

- `../../references/playbooks/model-selection-playbook.md`
- `../../references/checklists/model-selection-checklist.md`
