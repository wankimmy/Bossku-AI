# BosskuAI

A repo-local AI workspace layer for builders using Claude Code, Cursor, and Codex.

BosskuAI gives those tools the same memory files, routing rules, skills, handoff habits, human-output checks, and token discipline. No hosted control plane. No claim that prompts magically make every answer better.

Current version: **v1.8.9**. Skill count: **75**. Marquee playbooks at senior depth: **9**. Always-loaded prompt surface: ~877 tokens (−87.91% vs the pre-refactor baseline).

---

## Why use it

- Keep project memory in files, not only chat history.
- Share one working style across Claude Code, Cursor, and Codex.
- Route tasks into focused skills instead of loading one giant prompt.
- Reduce generic AI writing with `bosskuai-human-output`.
- Compress noisy output/rules with `bosskuai-token-saver`.
- Improve prompts/workflows with a metric-based ratchet loop.
- Run multi-agent flows on Claude Code (`/audit`, `/decide`, `/implement`) when single-call won't catch what matters.
- Open a local dashboard to see your workspace as a graph.

## What it is not

- Not a hosted agent platform.
- Not a replacement for tests or review.
- Not a guarantee of lower token usage on every task.
- Not a magic memory system; stale files can still mislead the model.
- Not faster — multi-agent flows take 30–90s and cost more tokens. They earn their cost only on cross-domain audits, hard-to-undo decisions, and non-trivial code changes.

---

## Install

```bash
git clone https://github.com/wankimmy/Bossku-AI bosskuAI
./bosskuAI/scripts/install.sh /path/to/your/project --profile core
```

Windows:

```powershell
.\bosskuAI\scripts\install.ps1 C:\path\to\your\project -Profile core
```

Profiles:

| Profile | Use when |
|---|---|
| `core` | smallest practical layer: memory, routing, search-first, human-output, token-saver, ratchet |
| `dev` | coding, review, architecture, Laravel, databases, Redis, Docker, VPS deployment, testing, GitHub workflow |
| `growth` | SEO, GEO, marketing, content calendar, launch, customer discovery, sales, competitor research |
| `design` | UI/UX, design systems, 3D, GSAP, Lenis |
| `full` | install every skill and support file |

Hooks are disabled by default:

```bash
./bosskuAI/scripts/install.sh /path/to/your/project --profile dev --with-hooks
```

The dashboard (v1.8.8+) provides a safer alternative — dry-run plan, confirm token, auto-backup before any overwrite. See **Dashboard** below.

---

## Install into a specific tool

### Claude Code

1. **Clone and install:**

   ```bash
   git clone https://github.com/wankimmy/Bossku-AI bosskuAI
   ./bosskuAI/scripts/install.sh /path/to/your/project --profile dev
   ```

   This copies `CLAUDE.md`, `.claude/` (commands, rules, settings), `.claude-plugin/`, `ai-assistant/`, and `AGENTS.md` into your project.

2. **Open the project in Claude Code** (`claude` CLI or the Claude Code VS Code/JetBrains extension).

3. **Test plugin loading** (optional):

   ```bash
   cd /path/to/your/project
   claude --plugin-dir . --debug
   /plugin validate
   ```

4. **Activate** — start any prompt with `bossku`.

5. **(Optional) Enable hooks:**

   ```bash
   ./bosskuAI/scripts/install.sh /path/to/your/project --profile dev --with-hooks
   ```

6. **(Optional) Wire MCPs** — merge `mcp-configs/claude-mcp-servers.json` into `~/.claude.json` under `mcpServers`, restart Claude Code, and verify with `/mcp`. Full guide in `mcp-configs/README.md`.

---

### Cursor

1. **Clone and install:**

   ```bash
   git clone https://github.com/wankimmy/Bossku-AI bosskuAI
   ./bosskuAI/scripts/install.sh /path/to/your/project --profile dev
   ```

   This places `.cursor/rules/bosskuai.mdc` (with `alwaysApply: true`) and `AGENTS.md` into your project.

2. **Open the project in Cursor.** The `.cursor/rules/` file is picked up automatically — no manual step needed.

3. **Verify the rule loaded:**
   - Open **Cursor Settings** (`Cmd+Shift+J` / `Ctrl+Shift+J`) → **Rules**
   - You should see `bosskuai` listed under project rules
   - Toggle it on if it appears disabled

4. **Activate** — start any Composer or chat prompt with `bossku`.

5. **(Optional) Wire MCPs** — go to **Cursor Settings → Features → MCP Servers**, click **Add MCP Server**, and use the `command`/`args` values from `mcp-configs/cursor-mcp-servers.json`. Full guide in `mcp-configs/README.md`.

> Multi-agent flows (`/audit`, `/decide`, `/implement`) are Claude Code–only. On Cursor they work as manual prompt sequences — see `docs/multi-agent-architecture.md`.

---

### Codex

1. **Clone and install:**

   ```bash
   git clone https://github.com/wankimmy/Bossku-AI bosskuAI
   ./bosskuAI/scripts/install.sh /path/to/your/project --profile dev
   ```

   This copies `AGENTS.md` and `.codex/` (including `config.toml` and `AGENTS.md`) into your project. Codex reads `AGENTS.md` from the project root automatically.

2. **Open the project in Codex.**

3. **(Optional) Wire MCPs** — edit `.codex/config.toml`, uncomment the `[mcp_servers.<name>]` blocks you want, and add your API keys. Full guide in `mcp-configs/README.md`.

4. **Activate** — start any prompt with `bossku`.

---

### After installing on any tool

Run the workspace validation to confirm everything landed correctly:

```bash
bash scripts/check-workspace.sh . --profile full
python3 scripts/eval_workspace.py
```

Then follow `WORKSPACE-ONBOARDING.md` to initialize project memory.

---

## Local dashboard (v1.8.8+)

Self-hosted, loopback-only UI for inspecting the workspace.

```bash
python3 scripts/dashboard.py
# open http://127.0.0.1:8765
```

Pure stdlib — no `pip install`. Five tabs:

- **Skill Graph** — D3 force-directed mindmap of all 75 skills. Click a node for description, triggers, keywords, referenced playbooks, depth pill (DEEP/OK/THIN). Toggle color by category vs depth, toggle cross-reference edges and trigger-overlap edges.
- **Memory** — every file in `ai-assistant/memory/` rendered live with full content.
- **Vector DB** — SQLite stats + a real query box that runs `vector_memory.retrieve_from_conn` (the production scorer) and shows score breakdown per result.
- **Evals** — runs all four eval suites in subprocess and parses headlines.
- **Actions** — three buttons:
  - *Generate project-understanding prompt* — writes a framed prompt file for a target path. No LLM call here; you run the prompt yourself in Claude Code.
  - *Sync skills to project* — dry-run shows the full plan, confirm token gates apply, every overwrite backed up to `<target>/.bosskuai-backup/<timestamp>/`.
  - *Reindex vector DB*.

This is a maintainer tool, not a runtime upgrade. It makes the workspace easier to understand, audit, and propagate.

---

## Multi-agent slash commands (Claude Code, v1.8.6+)

Three opt-in deep-mode flows. Default flow stays single-call on every surface.

| Command | Pattern | When | Cost vs single-call |
|---|---|---|---|
| `/audit` | Fan-out 2–4 specialists in parallel, then synthesize with explicit decision-forcing rules | Cross-domain audits ("audit my checkout flow") | 1.4–1.8× with caching, 3–5× without |
| `/decide` | Propose recommendation → separate critic sub-agent attacks it → revise | Hard-to-undo decisions | 1.1–1.3× with caching, ~2× without |
| `/implement` | Write code+tests → separate reviewer sub-agent applies rigorous-code-review + specialist anti-patterns → revise | Non-trivial diffs (payments, auth, multi-tenancy, migrations) | 1.1–1.3× with caching, ~2× without |

Each command enforces an explicit context budget — sub-agents load ONE specialist skill plus its playbook, never the cofounder skill or other specialists. This keeps focus tight and the cached prefix stable.

On Codex and Cursor's own chat, the same patterns are documented as manual prompt sequences — the runtimes don't dispatch sub-agents natively. See **`docs/multi-agent-architecture.md`**.

For why every-skill-as-an-agent is a bad idea (latency multiplies, error compounds, synthesis is the bottleneck), see the same doc's "Why not full multi-agent" section.

---

## Marquee playbooks (deepened v1.8.3–v1.8.5)

Nine playbooks rewritten from checklists to senior-level references with worked anti-pattern → fix → verify pairs:

| Playbook | Lines | Worked patterns |
|---|---|---|
| `bosskuai-laravel-development` | 415 | N+1 lists, missing Form Request authorization, soft-delete uniqueness across drivers, non-idempotent jobs, webhook signature/replay, transaction-after-commit ordering, tenant scoping, API Resource leakage, Octane state, env outside config |
| `bosskuai-nuxt-development` | 382 | SSR waterfalls, hydration mismatches, useFetch vs useAsyncData, route rules, SEO timing, Core Web Vitals, slow Nitro routes, Nuxt 4 migration |
| `bosskuai-redis-caching-queues` | 386 | Stampede / single-flight / SWR, eviction policy split, timeout >= retry_after, locks-with-TTL, failed-job alerting, Horizon tags, worker memory leaks, SLOWLOG, invalidation |
| `bosskuai-vps-docker-deployment` | 433 | Published DB ports, root containers, healthchecks, SSL renewal, untested backups, bind-mount data loss, 502s on first deploy, log rotation, queue:restart on deploy, rollback drills |
| `bosskuai-database-engineering` | 386 | Composite index column order, soft-delete uniqueness across MariaDB/MySQL/PG/SQLite, online ALTER, EXPLAIN reading guide, JSON indexing per driver, FK ON DELETE, UUIDv4 vs ULID, counter drift, migration safety |
| `bosskuai-mongodb` | 464 | Unbounded array on hot doc, ESR rule, $lookup blowup, schema drift, write concern, resumable migrations, keyset pagination, Atlas gotchas |
| `bosskuai-cybersecurity-risk` | 365 | 3 worked threat models (Stripe webhook, multi-tenant leak, secrets in git), MVP risk-vs-theater table, top-10 actually-matters list, auth anti-patterns, incident-response prep |
| `cofounder-decision-quality` | 221 | 3 worked decisions (good vs bad answers), stage-aware defaults, when-to-push-back, when-to-ASK, 8 named cofounder failure modes |
| `bosskuai-product-strategy` | 181 | 3 diagnostic questions, 3 worked decisions, 8 MVP failure modes, scope-cut techniques in escalating order, JTBD shaping, AI-product anti-patterns |

Every playbook follows the same template: wrong-shape, right-shape, exact verification command.

---

## Engineering principles skill (v1.8.9+)

`bosskuai-engineering-principles` codifies four widely-recognized engineering principles as a routable skill:

1. **Think Before Coding** — state assumptions, ask when uncertain, present alternatives.
2. **Simplicity First** — minimum code, no speculative abstractions.
3. **Surgical Changes** — every changed line traces to the request.
4. **Goal-Driven Execution** — verifiable goals with per-step `verify:` clauses.

The four-principle frame is adapted from [Andrej Karpathy via forrestchang/andrej-karpathy-skills](https://github.com/forrestchang/andrej-karpathy-skills) (MIT). The BosskuAI specifics — output contract, specialist routing table, when-NOT-to-use rule, honesty rule against virtue-signaling — are this workspace's adaptations.

Loaded only when triggered. Adds zero tokens to the always-loaded surface.

---

## Eval suite

Five evaluations ship in the workspace. The first four run in seconds and gate every release. The fifth is a harness for grading actual model answers.

```bash
bash scripts/check-workspace.sh . --profile full       # workspace structure
bash scripts/verify-skill-references.sh .              # all references resolve
bash scripts/validate-skill-index.sh .                 # index <-> folders match
python3 -S scripts/eval_workspace.py                   # routing/retrieval/workflow + token surface
python3 -S scripts/eval_expert_coverage.py             # 12 expert benchmark cases
python3 -S scripts/eval_adversarial_routing.py         # 8 symptom-language cases (no skill jargon)
python3 -S scripts/eval_routing_generalization.py      # 8 fresh cases not used to design triggers
python3 -S scripts/eval_token_budget.py --emit-prompts # generates prompts for cost comparison
python3 -S scripts/eval_llm_quality.py --emit-prompts  # generates prompts for graded LLM-quality runs
```

Current scores (v1.8.9):

| Eval | Score | Notes |
|---|---|---|
| Workspace routing-fit | 18/18 | keyword-matched (regression check) |
| Workspace retrieval | 8/8 (top-1 8/8) | uses production vector_memory scorer |
| Workspace workflow | 3/3 | end-to-end skill chains |
| Workspace prompt-surface | −87.91% | vs pre-refactor baseline |
| Expert coverage | 12/12 | each marquee skill must satisfy a `must_cover` keyword list |
| Adversarial routing | 8/8 GREEN | symptom-language cases |
| Routing generalization | 7/8 GREEN | fresh symptom cases (the remaining failure is preserved honestly, not over-fitted) |
| LLM-quality | scaffold | run-graded; harness produces deterministic scores from external grader output |
| Token-budget | scaffold | run-graded; reads token usage JSONs from real Claude Code sessions |

The two scaffolded evals are externalized on purpose — the harness produces deterministic scores from saved grade JSONs, so different graders don't move the number for the same input. See **`docs/llm-quality-eval.md`** and **`docs/caching-and-token-budget.md`**.

---

## Token economics on Claude Code

Anthropic prompt caching gives ~90% discount on cached input tokens. v1.8.7 wired the slash commands to enforce a stable cached prefix per sub-agent dispatch.

Five-minute test to verify caching is hitting in your setup:

1. Ask the cofounder a high-stakes question (single-call mode). Note total input/output tokens from the response.
2. Within 5 minutes, ask the same question with `/decide`. Note the sum across both calls.
3. Divide step 2 by step 1.

| Ratio | Diagnosis |
|---|---|
| 1.1–1.5× | Caching is working. Deep-mode is roughly the same cost as single-call. |
| 2.0×+ | Caching is NOT hitting. Likely cause: session timed out, AGENTS.md/SKILL.md edited mid-session, or dispatch leaked too much context. |

See `docs/caching-and-token-budget.md` for per-surface guidance (Claude Code gets all four levers, Codex gets raw token reduction only, Cursor's own chat is treated as single-call because caching is opaque there).

---

## Claude Code plugin

The plugin manifest is at `.claude-plugin/plugin.json` and exposes custom paths for:

- `skills`: `./ai-assistant/skills/`
- `commands`: `./.claude/commands/`
- `agents`: `./agents/`

Test locally:

```bash
claude --plugin-dir . --debug
/plugin validate
/reload-plugins
```

Use `.claude-plugin/plugin.with-hooks.json` only if you intentionally want hook-enabled plugin testing.

---

## Before / after examples

Human-output:

```txt
Before: Unlock a seamless AI-powered workflow that elevates your productivity.
After:  Keep the same project rules and memory across Claude Code, Cursor, and Codex.
```

Token-saver:

```txt
Before: Please make sure you carefully inspect the implementation and then provide a detailed list of issues.
After:  Inspect implementation. List issues, impact, fix.
```

Ratchet loop:

```txt
Metric:   approx prompt tokens
Baseline: 7,256
Change:   move long skill details into playbooks
Decision: keep if routing eval still passes
```

Cofounder decision contract:

```txt
Decision: [single recommendation]
Why now: [evidence + constraint]
Tradeoff: [gain / cost]
Smallest proof step: [one action]
Owner/skill: [primary skill + optional secondary]
Metric: [leading + lagging signal]
Do not do yet: [scope cut]
Risk/rollback: [main risk + mitigation]
```

---

## Release history (condensed)

| Version | Headline | Notes |
|---|---|---|
| **v1.8.9** | Engineering principles skill (Karpathy frame) | New tiny skill, on-demand load, properly attributed (MIT). Did not duplicate existing skills. |
| **v1.8.8** | Local dashboard | D3 mindmap, memory viewer, vector DB query, eval status, safe action buttons with dry-run + backup. Pure stdlib. |
| **v1.8.7** | Token economics + minimum-context dispatch | Slash commands enforce explicit per-sub-agent context budget. Token-budget eval scaffold. Per-surface caching guide. |
| **v1.8.6** | Multi-agent flows for Claude Code | `/audit`, `/decide`, `/implement` slash commands. Honest framing in `docs/multi-agent-architecture.md` of why every-skill-as-agent is the wrong shape. |
| **v1.8.5** | Cofounder + product-strategy + cybersecurity + mongodb depth | Worked decisions, threat models, MoSCoW scope-cut, schema-drift fixes. 14 redundant orphan files purged. |
| **v1.8.4** | Marquee depth pass + adversarial routing eval + LLM-quality scaffold | Nuxt, Redis, VPS Docker, Database Engineering rewritten. Adversarial routing went from 0/8 to 8/8. Generalization eval ships GREEN at 7/8. |
| **v1.8.3** | Honest depth pass | Laravel rewrite (93 → 415 lines, 10 worked anti-patterns). 22 trivial duplicate playbooks deleted; 14 substantive ones renamed `*-detailed-playbook.md` and properly linked. |
| **v1.8.2** | Expert stack + Claude Opus 4.7 readiness | First-class skills for Laravel, database engineering, VPS Docker, Redis, content calendar. Expert-coverage benchmark added. |
| **v1.8.0** | Real TF-IDF embedder + shared retrieval path + maintenance tooling | `vector_memory.py` got proper IDF-weighted scoring. Eval now uses production retrieval. `gen_skills.py`, `rotate_learning_log.py`, `validate_changelog.sh` added. |

Each release's full notes live in `CHANGELOG.md`.

---

## Honest framing

A few things this README will not claim:

- The release-history table above does not say "answers got better." That requires running `eval_llm_quality.py` with a real grader, which is the user's measurement, not this repo's claim.
- The token-budget table does not say "deep-mode is always cheaper." With caching working it's 1.1–1.5×; without, 2–3×. The `eval_token_budget.py` harness measures which world a given session is in.
- The marquee-playbook depth table does not say "Bossku is now expert at all 9 domains." It says nine playbooks contain senior-level worked examples and verification steps. Whether the agent applies them well in any given task is a separate measurement.
- The skill count (75) is not a quality signal. Most of the load-bearing work happens in the 9 marquee playbooks plus the cofounder skill. The other ~65 are checklists ranging from adequate to thin.

The next-step checklist for anyone serious about validating this:

1. Run `eval_llm_quality.py` against a real Claude Code session with an external grader. That's the only measurement that grades actual answer quality.
2. Run `eval_token_budget.py` against a real session in both single-call and deep-mode. That tells you whether caching is hitting in your setup.
3. Open the dashboard and look at the skill graph. The depth heatmap will show you which skills still need the same depth treatment as the nine marquee playbooks.

---

## Key commands

```bash
# Quick health check
bash scripts/check-workspace.sh . --profile full
bash scripts/verify-skill-references.sh .
bash scripts/validate-skill-index.sh .

# Routing + retrieval + workflow + token surface
python3 -S scripts/eval_workspace.py

# Marquee skill coverage
python3 -S scripts/eval_expert_coverage.py

# Routing under symptom-language prompts
python3 -S scripts/eval_adversarial_routing.py
python3 -S scripts/eval_routing_generalization.py

# Open the dashboard
python3 scripts/dashboard.py
```

---

## Docs

- `WORKSPACE-ONBOARDING.md` — getting started inside a project after install
- `CHANGELOG.md` — full release notes
- `docs/multi-agent-architecture.md` — slash commands, surface matrix, why-not-full-multi-agent
- `docs/caching-and-token-budget.md` — per-surface caching guidance, five-minute test
- `docs/llm-quality-eval.md` — externalized grader pattern, schema, scoring math
- `docs/adversarial-routing.md` — why keyword routing is brittle and three paths to fix it
- `docs/expert-benchmark-suite.md` — what `eval_expert_coverage.py` measures
- `docs/4.5-expert-upgrade.md` — earlier expert-stack notes
- `docs/plugin-testing.md` — Claude Code plugin local-test loop
- `docs/benchmarks.md` — eval-runner usage
- `SECURITY.md`

---

## License

MIT — see `LICENSE`.
