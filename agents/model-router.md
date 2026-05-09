# Model router (BosskuAI)

Concrete **defaults live in**:

- Laravel / Docker MVP: [`app/config/bossku_models.php`](../app/config/bossku_models.php)
- Workspace YAML hint file: [`ai-assistant/config/model-router.yaml`](../ai-assistant/config/model-router.yaml)
- Cross-tool narrative: [`ai-assistant/references/always-on-model-router.md`](../ai-assistant/references/always-on-model-router.md)

Tools (Cursor / Codex / OpenCode) cannot always switch models per message — BosskuAI **documents intent** you should follow manually when the UI allows.

## Design goals

1. **Orchestrator and executor must not share the primary model by default** when both phases run and both model slots are configurable.
2. **Expensive reasoning** for planning, architecture, audit, and final review.
3. **Cheaper / coding-oriented** executor when risk is low.
4. **Small/local** models only for summarization, classification, formatting, lightweight memory cleanup — not auth/payment-critical paths.
5. **Never route blindly**: note task type + risk + why (internal reasoning or orchestrator logs).

## Target role map (authoritative naming for docs)

| Role | Model role | Runtime |
|---|---|---|
| Orchestrator | reasoning | Ollama |
| Executor | coding | Ollama |
| Auditor | review | Ollama |
| Security auditor | review | Ollama |
| Final reviewer | reasoning | Ollama |
| Router / classify | fast | Ollama |

Concrete model names come from `OLLAMA_REASONING_MODEL`, `OLLAMA_CODING_MODEL`, `OLLAMA_REVIEW_MODEL`, and `OLLAMA_FAST_MODEL` when set, with older `BOSSKU_*` keys kept as compatibility aliases.

## Routing table

| Task type | Best model role | Recommended model line | Reason |
|---|---|---|---|
| Ambiguous requirement / architecture | Planner | Orchestrator primary | Fewer wrong files; cheaper rework |
| Standard feature / refactor | Coder | Executor primary | Tokens scale with code volume |
| Security / auth / payment review | Reviewer | Auditor primary | Strong safety reasoning |
| Ship checklist / completeness | Reviewer | Final reviewer primary | Consolidates risks + next actions |
| Trivial Q&A | Planner or Coder | Direct answer / smallest sufficient | Saves orchestration overhead |
| High-risk execution | Coder (+ reviewer after) | Use high-risk Ollama coding/review route | Single wrong line is costly |

Escalate executor toward the high-risk Ollama route when `bossku_models.php` high-risk triggers apply.
