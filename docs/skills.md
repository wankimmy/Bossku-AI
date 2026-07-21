# Skills

Canonical skills live in [`skills/`](../skills/). Each folder contains `SKILL.md` with YAML frontmatter. Write descriptions so agents can self-select: lead with **Use when…** and concrete user triggers (CI failure, release notes, cofounder mode, etc.).

## Routing

- Say `bossku` or use cofounder mode for cross-domain work.
- `bossku skills find "<task>"` suggests a skill from triggers and keywords.
- Deprecated Bossku names resolve via [`skills/aliases.json`](../skills/aliases.json).
- Vendored third-party skills are listed in [`skills/vendored.json`](../skills/vendored.json).

## Profiles

`bossku install --profile core` installs Bossku co-founder essentials only (~13 skills).

`bossku install --profile full` installs the entire library (~196 skills), including vendored packs.

## Vendored packs

| Pack | Skills | Optional pip tool |
|---|---|---|
| marketingskills | 47 marketing/CRO/SEO/copy skills | — |
| superpowers | 14 process skills (brainstorm, TDD, debug, plans) | — |
| hallmark | Anti-AI design skill | — |
| browser-use | 5 browser agent skills | `browser-use` |
| graphify | Knowledge-graph skill | `graphifyy` |
| markitdown | Document conversion skill | `markitdown[all]` |
| loop-engineering | 12 loop/triage/CI/PR skills | — |
| taste-skill | 13 anti-slop frontend / imagegen skills | — |
| scroll-world | Scroll-scrub Higgsfield cinematic world landing | Higgsfield CLI, ffmpeg |

Attribution: [`docs/third-party.md`](third-party.md). Optional deps: [`requirements-optional.txt`](../requirements-optional.txt).

## Overlaps (keep both)

| Bossku skill | Vendored skill | When to prefer vendored |
|---|---|---|
| `bosskuai-taste` | `hallmark` | New UI pages that need a distinct non-template look |
| `bosskuai-taste` | `taste-skill` (default), `soft-skill`, `minimalist-skill`, `brutalist-skill`, `redesign-skill` | Full tasteskill.dev pack: dials, redesign audit, direction variants |
| `bosskuai-marketing-growth` | `product-marketing`, `copywriting`, `cro` | Deep marketing task with frameworks |
| `bosskuai-browser-automation` | `browser-use` | Full browser-use agent stack is installed |
| `bosskuai-diagnose-loop` | `systematic-debugging` | Superpowers debug workflow requested |
| `bosskuai-tdd-loop` | `test-driven-development` | Superpowers TDD workflow requested |
| `bosskuai-diagnose-loop`, `bosskuai-ratchet-loop` | `loop-triage`, `loop-verifier`, `minimal-fix` | Loop-engineering agent loops (CI/PR/issue sweeps) |
| `bosskuai-gsap-animation`, `bosskuai-lenis-smooth-scroll` | `scroll-world` | Higgsfield scroll-scrub fly-through world landing; use Bossku GSAP/Lenis for code-only motion |

## Aliases (merged Bossku duplicates)

| Old ID | Canonical |
|---|---|
| `bosskuai-caveman` | `bosskuai-token-saver` |
| `bosskuai-bug-finding` | `bosskuai-diagnose-loop` |
| `bosskuai-root-cause-investigation` | `bosskuai-diagnose-loop` |
| `bosskuai-project-management` | `bosskuai-planning-execution` |
| `bosskuai-social-content-calendar` | `bosskuai-content-calendar` |
| `bosskuai-agent-security-hardening` | `bosskuai-prompt-injection-defense` |
| `bosskuai-grill-me` | `bosskuai-grill-with-docs` |
| `bosskuai-zoom-out` | `bosskuai-codebase-analysis` |
