# Nuxt Development Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

## Before writing code
- [ ] Nuxt version confirmed from `package.json` (Nuxt 3 vs 4)
- [ ] Relevant official docs fetched via Context7 MCP
- [ ] Checked if Nuxt has a built-in feature for this before adding a library or custom solution
- [ ] Existing project conventions read (`nuxt.config.ts`, adjacent components, composables)

## Implementation
- [ ] Auto-imports used — no redundant manual imports of composables, components, or Vue APIs
- [ ] Data fetching uses `useFetch` or `useAsyncData` — not `$fetch` in `<script setup>`
- [ ] `pending` and `error` states handled for all async data
- [ ] Shared state uses `useState` or Pinia — not plain `ref()` across components
- [ ] Server routes validate all inputs (`getQuery`, `readBody` validated before use)
- [ ] Rendering mode appropriate for this route (SSR / SPA / SSG / hybrid via `routeRules`)
- [ ] File-based routing used — no manually defined routes
- [ ] Runtime config used for environment values — not `process.env`

## SEO & Meta
- [ ] `useHead` or `useSeoMeta` used with reactive values (`computed` or function form)
- [ ] OG tags set for public-facing pages

## Audit-specific
- [ ] Anti-pattern table from SKILL.md scanned against every modified file
- [ ] Findings categorized by severity: critical / warning / info
- [ ] Proposed changes follow the minimal-change principle — no cosmetic-only changes

## Verification
- [ ] `nuxi typecheck` passes without errors
- [ ] `nuxi build` succeeds
- [ ] No hydration mismatch warnings in browser console
- [ ] SSR payload correct — no double-fetch on client navigation
