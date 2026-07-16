# ADR: Two-phase model split (plan then execute)

- Date: 2026-03-30
- Status: Accepted

## Context

Meaningful work mixes ambiguous reasoning (scope, architecture, security tradeoffs) with high-volume concrete edits (implementation, refactors, boilerplate). A single default model profile optimizes for neither: fast models skip necessary analysis; strongest models are costly and slow for mechanical tasks.

BosskuAI is used across Claude Code, Cursor, and Codex with different vendor names but the same behavioral contract.

## Decision

Enforce a **two-phase split** for non-trivial tasks:

1. **Planning / strategy / architecture / analysis** — use the strongest appropriate reasoning model available in the active tool.
2. **Implementation / execution / codegen** — use a fast, tool-capable model suited to concrete edits.

Quick or trivial tasks (single-line fixes, factual lookups) may skip the split.

Tool-specific model names live in each entry point (`AGENTS.md`, `CLAUDE.md`, `.cursor/rules/bosskuai.mdc`, `.claude/rules/bosskuai.md`, `.codex/` config); the **rule** is the phase split, not a single global model string.

## Consequences

- **Positive:** Fewer irreversible mistakes from rushing to code; better cost/latency on bulk edits when planning is already settled.
- **Negative:** Operators must keep tool configs updated when vendors ship new models; slightly more ceremony per meaningful task.
- **Mitigation:** State plan phase and execution phase explicitly at the start of meaningful responses; allow explicit skip for trivial work.

## Alternatives considered

- **Single model everywhere** — simpler but repeatedly misallocates capability vs cost.
- **User decides per message without a default** — inconsistent team behavior and weak guardrails.
