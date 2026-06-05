# FAQ

## Is BosskuAI a replacement for LangChain or CrewAI?

No. It’s a **workflow and memory layer** for developers using AI coding tools. See [`comparison.md`](comparison.md).

## Do I have to use Docker?

No. Many teams use only `AGENTS.md`, `ai-assistant/`, and editor rules. Docker adds a UI + API for observability.

## Why the `[BOSSKUAI]` header?

So humans (and future tooling) can see **skill**, **agent phase**, and **memory usage** at a glance. Defined in [`AGENTS.md`](../AGENTS.md).

## Which file is canonical if docs disagree?

1. `AGENTS.md` for cross-tool behavior  
2. `app/config/bossku_models.php` for Docker MVP routing  
3. `skill-index.json` for skill metadata  

If something still conflicts, treat it as a bug in docs — open an issue or PR.

## Can Bossku hide my secrets?

Guidance forbids storing secrets in memory (**[`memory/memory-policy.md`](../memory/memory-policy.md)**). Hooks and logs should redact aggressively, but **you** must still rotate anything accidentally leaked.

## What about offline / air-gapped?

Disable router LLM in settings / env so classification runs with heuristics only (`README.md` mentions `BOSSKU_ROUTER_LLM_ENABLED`). Prefer local models via Ollama when configured.

## Roadmap?

See README **Roadmap** section — kept intentionally short and honest.
