# Caching and Token Budget

How BosskuAI minimizes token cost without losing accuracy. Per surface, because the rules differ.

## Hard truth first

"Save tokens AND get more accurate answers" is two goals. They sometimes conflict:

- Loading more context → more tokens, sometimes better answers.
- Loading less context → fewer tokens, sometimes worse answers.
- Caching is the only lever that's pure upside on both axes — same content, lower cost.

This guide focuses on the upside-only levers first, the trade-off levers second.

## Surface matrix

| | Claude Code (VS Code ext) | Codex CLI | Cursor's own chat |
|---|---|---|---|
| API path | Anthropic direct | OpenAI | Cursor's routing layer |
| Prompt caching | ✅ explicit, controllable | ❌ no equivalent | ⚠️ opaque |
| Cache discount | ~90% on cache hit | n/a | unknown |
| Sub-agent dispatch | ✅ Task tool | ❌ | ❌ |
| Verifiable cost | ✅ usage in API response | ✅ token counts | ❌ no per-call breakdown |

**Implication**: real token-economics work happens on Claude Code. Codex benefits from raw context reduction. Cursor's own chat you treat as a single-call surface and accept the opacity.

## The four levers, ranked by honest impact

### 1. Prompt caching (Claude Code only — biggest lever)

Anthropic prompt caching gives ~90% discount on cached input tokens. Cache hits require:

- The cached content is identical byte-for-byte to a previous call (within ~5 minutes by default).
- The cached content sits at the START of the prompt — caching is prefix-based.
- The content is marked with `cache_control: { type: "ephemeral" }` (Claude Code does this automatically for stable workspace files).

**What this means for the workspace design:**

- AGENTS.md, CLAUDE.md, and the active SKILL.md should be **stable across calls in a session**. Don't edit them mid-session if you can avoid it; every edit invalidates the cache.
- Skill content is much more cacheable than conversation history. Conversation grows; skills don't.
- Sub-agent dispatches benefit MOST from caching — each sub-agent call repeats the system prompt and the dispatcher's framing. With caching that repeated prefix is ~free.

**Concrete example of the win:**

A `/audit` flow without caching:
```
Sub-agent A: 8000 input tokens (system + framing + skill + artifact)
Sub-agent B: 8000 input tokens (same)
Sub-agent C: 8000 input tokens (same)
Synthesis:    9000 input tokens (system + 3 sub-agent outputs)
Total: 33,000 input tokens at 1.0×
```

Same flow with caching (system + framing cached at the prefix of each sub-agent):
```
Sub-agent A: 8000 input tokens (full price first hit)
Sub-agent B: 8000 input tokens, but ~6500 cache-hit at 0.1×, 1500 fresh at 1.0×
Sub-agent C: 8000 input tokens, same split
Synthesis:    9000 input tokens, ~5000 cache-hit at 0.1×, 4000 fresh at 1.0×
Effective: 8000 + (650+1500) + (650+1500) + (500+4000) ≈ 16,800 effective tokens
```

That's a **~50% real cost reduction** on the same flow, with the same accuracy. This is the win.

But it only works if the prefix is stable. The next lever is what makes the prefix stable.

### 2. Minimum-context loading per sub-agent (medium lever)

Each sub-agent dispatch in `/audit`, `/decide`, `/implement` now follows an explicit context budget — see those slash command files for the verbatim rules. The principle:

- Sub-agent loads ONE specialist SKILL.md and ONE playbook. Not multiple skills, not the cofounder skill, not other playbooks.
- Conversation history beyond the task framing is excluded.
- The artifact under review is included; everything else the dispatcher already chose is excluded.

**Why this matters for caching specifically:** if every sub-agent loaded a different combination of 3-4 skills, the cached prefix would differ between sub-agents. Cache hits would drop. By specifying ONE skill per sub-agent at the dispatch point, the prefix structure is consistent and the cache hits.

**Why this matters for accuracy:** focused context outperforms juggled context. A sub-agent with one specialist's playbook in view applies that specialist's anti-patterns rigorously. A sub-agent with five skills loaded thinks generically.

### 3. Always-loaded surface budget (small lever, but free)

Files that load on every Claude Code session:
- `AGENTS.md`
- `CLAUDE.md`
- `skill-index.json`
- `.claude/rules/*.md`
- `.codex/AGENTS.md` (if Codex extension is active in the workspace)

Current total: ~877 tokens. Tracked by `evals/baselines/pre-refactor.json` and `eval_workspace.py`.

These are small enough that caching makes them effectively free per session. The optimization here is: **don't grow them.** Every word added is paid every session, even with caching (first call in a session pays full price). Resist the urge to add general-purpose advice to AGENTS.md; put it in a skill.

### 4. Lazy playbook loading (small lever)

Skills load their referenced playbooks on demand, not eagerly. The `bosskuai-mongodb` skill is 35 lines; its playbook is 464 lines. The 464 lines load only when a sub-agent or single-call flow actually needs MongoDB depth. This is already how the workspace works — keep it that way.

## Per-surface guidance

### Claude Code (VS Code extension) — your main surface

You get all four levers. The slash commands in v1.8.7 are designed for this. The cost wins:

- `/audit` cost vs single-call: theoretically 1.4–1.8× with caching, vs 3–5× without.
- `/decide` cost vs single-call: theoretically 1.1–1.3× with caching, vs 2× without.
- `/implement` cost vs single-call: theoretically 1.1–1.3× with caching, vs 2× without.

These are estimates from the prefix structure. Real numbers depend on session continuity (5-minute cache TTL means the second call within 5 minutes hits, calls 10 minutes apart don't). The token-budget eval below is how to verify the actual numbers in your usage.

### Codex CLI — secondary surface

OpenAI doesn't expose the same caching primitives. You get raw token reduction:

- Codex AGENTS.md is already minimal. Don't grow it.
- Sub-agent dispatch isn't supported, so the multi-agent cost question is moot — you'd be running sequential prompts manually, which is more tokens than a single call.
- Stay in single-call routing on Codex. It's the cheapest path.

### Cursor's own chat — tertiary surface

You can't measure or control caching. Treat as single-call. Keep `.cursor/rules/bosskuai.mdc` lean (currently is). Don't try to run multi-agent flows here — the user typing 3 sequential prompts pays for the workspace context 3 times, which is worse than one call on any other surface.

## How to actually measure this

Theoretical numbers are not honest numbers. The token-budget eval (`scripts/eval_token_budget.py`) is designed to read real token counts from your runs and produce a comparison.

The eval doesn't run model calls itself — it provides:

1. A standard task to use as the comparison input.
2. A spec for what to record from each model run (input tokens, cached tokens, output tokens, wall time).
3. A scorer that reads recorded JSONs and produces a comparison table.

This mirrors `eval_llm_quality.py` from v1.8.4 — externalized measurement, deterministic scoring.

See the script's `--help` output and `evals/token-budget-cases.json` for the standard tasks.

## What to do today

If you only do one thing: **on Claude Code, run the same task twice — once with `/decide`, once with single-call cofounder mode.** Note input/output tokens for each (Claude Code shows usage in the response). Confirm `/decide` costs 1.1–1.3× single-call (caching working) and not 2× (caching not working).

If `/decide` costs 2× or close to it, the cache isn't hitting. Most likely cause: the session was idle longer than 5 minutes between the propose and critique calls. Or AGENTS.md / SKILL.md was edited mid-session. Or the dispatch didn't actually exclude cofounder skill from the critic's context (verify by reading the dispatched prompt in Claude Code's session log).

If `/decide` costs 1.1–1.3×, caching is working as designed.

This is the honest test. Architecture diagrams don't reduce tokens; verifiable cache hits do.
