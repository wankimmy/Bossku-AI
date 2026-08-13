---
name: bosskuai-nuxt-development
description: Use for expert Nuxt 4.x development, code auditing, and best-practice guidance grounded in official docs via Context7.
---

# BosskuAI Nuxt Development

Use this skill for building, auditing, or debugging Nuxt 3/4 applications, where the answer depends on Nuxt's rendering model rather than general Vue knowledge.

## How this differs from nearby skills

- **`bosskuai-polyglot-engineering`**: general cross-language guidance; this skill knows Nuxt's specific rendering and data-fetching rules.
- **`bosskuai-performance-profiling`**: profiles a running system; this skill covers Nuxt-specific hydration and payload costs.
- **`bosskuai-ui-ux-design-to-code`**: interface and accessibility work; this skill covers the framework beneath it.
- **`bosskuai-seo-geo`**: SEO strategy; this skill wires the meta, sitemap, and rendering that implement it.

## Ground API details in official docs

Nuxt's API surface moves between versions, and 3 vs 4 differ in directory structure and defaults. Resolve the library through Context7 (`resolve-library-id`, then `get-library-docs`) for the specific composable or config option rather than answering from memory. State the version you targeted.

## Orient before changing anything

1. Read `package.json` for the Nuxt version, and `nuxt.config.ts` for modules, rendering mode, and `routeRules`.
2. Determine the structure: Nuxt 4 `app/` directory or Nuxt 3 root-level.
3. Identify rendering mode per route: SSR, SSG, ISR, hybrid, or client-only. Many bugs are a route rendering differently than assumed.

## Rules that catch most Nuxt bugs

- `useFetch` for component-bound data; `useAsyncData` when you need control over the key or handler.
- The same call site runs on the server and reuses on hydration. Duplicate fetches usually mean an unstable key.
- Server routes (`server/api/*`) own external API calls and secrets, not page components.
- `useHead` / `useSeoMeta` belong in `setup()`. Inside `onMounted` they never render server-side.
- `routeRules` in `nuxt.config.ts` declares SSR/SSG/ISR/CDN behavior per path.
- Audit the hydration payload: full database rows or secrets serialized into the page are both a leak and a performance cost.
- Hydration mismatches usually come from time, randomness, or browser-only state used during render.

## Guardrails

- Never expose private keys through `runtimeConfig.public` or a client-visible composable.
- Do not reach for client-only rendering to silence a hydration warning; find the mismatch.
- Do not add a module for something Nuxt already provides natively.
- Confirm which Nuxt major version the code targets before quoting an API.

## Verification

```bash
pnpm nuxt typecheck
pnpm nuxt build --analyze     # bundle composition
pnpm nuxt preview             # production-like local run
```

Check headers with `curl -I` and measure Core Web Vitals on a real device profile, not localhost.

## Output format

```text
Nuxt version: [3.x / 4.x] - Structure: [app/ or root] - Rendering: [per route]
Docs consulted: [Context7 topics, or why not needed]

Findings:
  P0/P1/P2 - [file:line] - [issue] - [fix]

Change plan: [smallest correct change]
Verification: [commands run and result]
```

## References

- `../../references/playbooks/bosskuai-nuxt-development-playbook.md` — audit reference, wrong-way/right-way pairs
- `../../references/playbooks/nuxt-development-detailed-playbook.md` — feature development workflow
- `../../references/checklists/expert-cofounder-stack-checklist.md`
