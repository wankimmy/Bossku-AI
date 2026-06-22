# Project Understanding

Use this file to store durable understanding about a specific codebase or product after reading its source.

Treat it as shared memory across supported tool surfaces.

## What to store

- what the project is
- who it appears to serve
- the main workflows or business purpose
- the stack and architecture style
- the likely source-of-truth files
- the most relevant expert skills for future work

## What not to store

- temporary debugging notes
- one-off task chatter
- guesses that were never confirmed

## BosskuAI

- Project type: Confirmed: Public reusable AI workspace layer for Claude Code, Cursor, and Codex.
- Core purpose: Confirmed: Keep instructions, routing, memory, validation, and handoff behavior consistent across multiple coding assistants without adding a hosted control plane by default.
- Primary users: Confirmed: Power users and small teams that want repeatable cross-tool workflows and repo-local memory instead of relying only on chat history.
- Tool posture: Confirmed: Tool-agnostic workspace layer with lean tool-specific entry files and a shared canonical contract in `AGENTS.md`.
- Primary source-of-truth files: Confirmed: `AGENTS.md` is the canonical contract; `skill-index.json` is the routing registry; `README.md` and `WORKSPACE-ONBOARDING.md` are the public setup docs; `ai-assistant/memory/` is the durable memory surface.
- Knowledge layout: Confirmed: `ai-assistant/skills/` contains specialist workflows; `ai-assistant/references/` contains checklists, playbooks, and architecture notes; `ai-assistant/memory/` stores durable shared context; `scripts/` contains install, validation, and eval helpers.
- Current expert surface size: Confirmed: The repo contains 66 indexed skills total, including 63 active skills and 3 deprecated compatibility aliases.
- Codex support shape: Confirmed: `.codex/config.toml` enables multi-agent mode with read-only `planner`, `explorer`, `reviewer`, `security_reviewer`, `docs_researcher`, and `tdd_guide` roles.
- Current strength: Confirmed: BosskuAI is strongest as a local-first workspace layer that improves repeatability, continuity, and cross-tool consistency.
- Current gap: Confirmed: The repo includes local evals and a smarter local-hash retrieval scorer, but retrieval is still approximate and true task-answer accuracy still depends on the underlying model and memory quality.
- Strategic direction worth preserving: Confirmed: BosskuAI should stay curated, keep the always-loaded layer lean, and route specialist behavior only when the task clearly needs it.
- Most relevant skills for future BosskuAI work: Confirmed: `bosskuai-workspace-assistant`, `bosskuai-project-understanding`, `bosskuai-search-first`, `bosskuai-skill-stocktake`, `bosskuai-rules-distill`, `bosskuai-continuous-learning`, `bosskuai-engineering-delivery`, `bosskuai-rigorous-code-review`, and `bosskuai-documentation-lookup`.
- Install flow: Confirmed: `scripts/install.sh` and `scripts/install.ps1` apply the BosskuAI workspace layer into a target project; `scripts/check-workspace.sh` validates required files. Optional: `--preserve-memory` / `-PreserveMemory` keeps existing `ai-assistant/memory/` across a full reinstall; `--skills-only` / `-SkillsOnly` refreshes only `ai-assistant/skills`, `references`, and `scripts`.
- Maintenance flow: Confirmed: BosskuAI includes skill stocktake and rules distillation workflows, deterministic helper scripts, and evals for prompt surface, routing-fit, retrieval relevance, and workflow proxies.
- Learning hygiene flow: Confirmed: BosskuAI includes a dedicated continuous-learning workflow, plus `active-continuation.md` as optional ephemeral handoff state rather than durable memory.
- Hook posture: Confirmed: BosskuAI includes optional hook-ready reminder scripts under `ai-assistant/hooks/`, but they are advisory only and intentionally avoid automatic memory or rule mutation.

## Meatlers (My Fresh Storage)

- **Repo:** `C:/Users/Admin/Documents/Safwan/meatlers` — Laravel 13 + Inertia/Vue storefront for fresh meat e-commerce.
- **Prod site:** `myfreshstorage.com.my` (Plesk deploy).
- **Payment branch:** `payment-integration` on `https://github.com/wankimmy/meatlers.git`.
- **Payment status (2026-06-22):** **Complete and verified by operator** — iPay88 hosted checkout works end-to-end.
- **iPay88 flow:** checkout `form.post` → `CheckoutController::store` → `Inertia::location()` to `/payments/ipay88/handoff/{order}` (full page, not SPA XHR) → Blade auto-POST to `payment.ipay88.com.my` → backend/response callbacks mark paid.
- **Key fixes shipped:** `Inertia::location()` (fixes sandboxed `srcdoc` iframe blocking form POST); CSP `form-action` includes iPay88 origin via `config('ipay88.entry_url')`; handoff form `target="_top"`; default payment method `ipay88`.
- **Frontend build:** `ziggy-js` npm package (no `vendor/` needed for `npm run build`); `vite.config.js` `build.emptyOutDir: false` avoids Windows EPERM when Docker/nginx locks `public/build/assets`; prefer `make npm-build` or `docker compose run --rm --no-deps node sh -c "npm ci && npm run build"` when stack is running.
- **Docker:** `app` uses `vendor_data` volume; `node` uses `node_modules_data` volume — run `npm ci` inside Docker after `package-lock.json` changes.
- **CI:** backend tests + Pint + composer audit; frontend `composer install` then `npm ci && npm run build`; OWASP ZAP baseline gate (Medium+ fails).
- **Source-of-truth files:** `app/Http/Controllers/CheckoutController.php`, `app/Http/Controllers/Payments/Ipay88HandoffController.php`, `resources/views/payments/ipay88-handoff.blade.php`, `app/Http/Middleware/SecurityHeaders.php`, `config/payments.php`, `config/ipay88.php`, `tests/Feature/CheckoutPaymentTest.php`.
