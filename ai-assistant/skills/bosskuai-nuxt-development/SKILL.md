---
name: bosskuai-nuxt-development
description: Use for expert Nuxt 4.x development, code auditing, and best-practice guidance grounded in official docs via Context7.
---

# BosskuAI Nuxt Development

Use this skill when building, reviewing, or auditing Nuxt applications. Covers Nuxt 4.x as the primary target with Nuxt 3 compatibility notes. Always grounds guidance in official documentation fetched via Context7 MCP — never rely on assumptions about API signatures, config options, or framework behavior.

## How this differs from nearby skills

- **vs polyglot-engineering**: polyglot gives general stack guidance across languages and frameworks; this skill provides Nuxt-specific architecture, conventions, anti-patterns, and idiomatic patterns.
- **vs coding-best-practices**: best practices applies universal quality patterns; this skill applies them through the Nuxt lens with framework-specific idioms (auto-imports, composables, rendering modes).
- **vs documentation-lookup**: documentation-lookup fetches any library's docs generically; this skill knows *what* to look up, *how* to interpret Nuxt docs in context, and which anti-patterns to check.
- **vs rigorous-code-review**: code review is stack-agnostic skeptical review; this skill audits specifically for Nuxt anti-patterns and missed built-in framework features.

## MCP requirements

- **Context7 (mandatory for docs).** Before giving Nuxt-specific guidance, resolve the Nuxt library via Context7's `resolve-library-id` and fetch the relevant section with `get-library-docs`. Do not rely on training data for API signatures, config options, or behavior specifics.
- **Graceful degradation:** If Context7 is unavailable, answer from training data and explicitly state: *"This is from training data — verify against https://nuxt.com/docs/4.x before using in production."*

## Mindset

- **Use Nuxt conventions instead of fighting them.** File-based routing, auto-imports, and built-in composables exist for a reason — use them.
- **Check built-in features before adding libraries.** Nuxt does more than most developers realize. Before installing a package or writing a custom solution, check if Nuxt already provides it.
- **Minimal changes, no redundant code.** If Nuxt auto-imports it, do not manually import it. If file-based routing handles it, do not add a manual route. If a composable exists, do not reinvent it.
- **Server-side by default.** Understand the rendering mode before writing code. Know what runs on server vs client.
- **Always verify against official Nuxt 4.x docs.** Do not guess. Fetch, read, then recommend.

## Nuxt 4.x core concepts

| Area | Key guidance |
|------|-------------|
| **File-based routing** | `app/pages/` directory. Dynamic params: `[id].vue`. Catch-all: `[...slug].vue`. Nested layouts via directory structure. |
| **Auto-imports** | Components, composables, and utils are auto-imported. Never manually import from `#imports` unless disambiguation is needed. |
| **Composables** | `useFetch`, `useAsyncData`, `useState`, `useHead`, `useSeoMeta`, `useRuntimeConfig`, `useRoute`, `useRouter`, `navigateTo`, `useNuxtApp`. |
| **Data fetching** | `useFetch` for simple fetches, `useAsyncData` for complex logic. Always handle `pending` and `error` states. Use `$fetch` only in event handlers or server routes — never in `<script setup>`. |
| **Server routes** | `server/api/` and `server/routes/` with Nitro. Use `defineEventHandler`, `getQuery`, `readBody`. Validate all inputs server-side. |
| **Middleware** | `middleware/` directory. Route middleware (named or inline) vs global. Use `defineNuxtRouteMiddleware`. |
| **Plugins** | `plugins/` directory. Use `defineNuxtPlugin`. Provide/inject pattern. Order via filename numbering. |
| **Modules** | `modules/` for local modules, `nuxt.config.ts` for installed modules. Prefer published modules over custom solutions when available. |
| **State management** | `useState` for SSR-safe shared state. Pinia for complex state. Never use plain `ref()` for cross-component shared state — it is not SSR-safe. |
| **Rendering modes** | SSR (default), SPA, SSG (`nuxt generate`), hybrid (`routeRules`). Use `routeRules` for per-route rendering mode configuration. |
| **SEO** | `useHead` and `useSeoMeta` for meta tags. `defineOgImage` if using `nuxt-og-image`. `useSchemaOrg` for structured data. |
| **Error handling** | `error.vue` for fatal errors, `<NuxtErrorBoundary>` for component-level, `createError` for server errors with status codes. |
| **Layers** | Nuxt layers for shared config/components across projects. `extends` in `nuxt.config.ts`. |
| **TypeScript** | Nuxt 4 is TypeScript-first. Run `nuxi typecheck`. Auto-generated types in `.nuxt/`. |

## Nuxt 3 → 4 migration notes

When working with existing Nuxt 3 codebases, be aware of these key changes:

- **Directory structure**: Nuxt 4 uses `app/` prefix for application code (`app/pages/`, `app/components/`, `app/composables/`, `app/layouts/`). Source files previously at root move under `app/`.
- **Compatibility flag**: Set `compatibilityVersion: 4` in `nuxt.config.ts` to opt into Nuxt 4 behavior while on Nuxt 3.
- **Import paths**: Some auto-import paths change. Verify against official migration guide via Context7.
- **Do not migrate unless asked**: If the project is on Nuxt 3 and migration is not the task, give Nuxt 3–compatible guidance. Do not silently apply Nuxt 4 patterns to a Nuxt 3 project.

## Workflow: Development

1. **Verify the Nuxt version** — Check `package.json` for the `nuxt` dependency version. Confirm whether the project uses Nuxt 3 or 4.
2. **Fetch official docs via Context7** — Resolve `nuxt` library ID, then query for the specific topic (composable, config option, module, etc.).
3. **Check existing project conventions** — Read `nuxt.config.ts`, existing components, composables, and middleware to understand the project's patterns before adding new code.
4. **Apply framework conventions first** — Use file-based routing, auto-imports, and built-in composables before reaching for custom solutions or third-party packages.
5. **Implement minimally** — Write only what is needed. No wrapper functions around built-in composables. No manual imports of auto-imported utilities. No abstraction layers that duplicate framework behavior.
6. **Validate** — Run `nuxi typecheck` and `nuxi build`. Test the route behavior. Verify SSR/hydration works correctly.

## Workflow: Audit

Use this when reviewing an existing Nuxt codebase for quality, correctness, and adherence to framework conventions.

1. **Identify the Nuxt version and config** — Read `nuxt.config.ts`, `package.json`, and `tsconfig.json`. Note modules, rendering mode, and any custom configuration.
2. **Fetch current docs** — Use Context7 to confirm correct patterns for the project's Nuxt version.
3. **Scan for anti-patterns** — Check every file against the anti-pattern table below. Focus on correctness and security first, then convention adherence.
4. **Report findings** — Use the audit output format. Categorize by severity: critical (broken behavior or security risk), warning (correctness issue or performance problem), info (convention deviation).

## Common Nuxt anti-patterns

| Anti-pattern | Why it is wrong | Fix |
|-------------|----------------|-----|
| Manual imports of auto-imported composables | Redundant, fights the framework | Remove the import statement |
| `$fetch` in `<script setup>` or component `setup()` | Causes double-fetch (server + client), no SSR payload deduplication | Use `useFetch` or `useAsyncData` |
| Plain `ref()` for shared cross-component state | Not SSR-safe, causes hydration mismatch | Use `useState` or Pinia |
| Manually defining routes instead of using `pages/` | Bypasses file-based routing conventions | Move to `pages/` directory structure |
| Not handling `useFetch` error/pending states | Poor UX, potential runtime errors on null data | Always destructure `{ data, pending, error }` and handle each |
| Server route without input validation | Security risk — unvalidated user input | Use `zod`, `h3-zod`, or manual validation in event handlers |
| `useHead` with reactive data but no `computed` | Meta tags won't update on client navigation | Wrap in `computed()` or use the function form of `useHead` |
| Importing from `vue` what Nuxt auto-imports | Redundant imports that add noise | Remove `import { ref, computed } from 'vue'` etc. |
| Using `process.env` instead of `useRuntimeConfig` | Not SSR-safe, not type-safe, not reactive | Use `useRuntimeConfig().public.*` for client-accessible values |
| Fat `nuxt.config.ts` with inline logic | Hard to maintain and test | Extract to modules or composables |

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- **Always fetch Nuxt docs via Context7** before giving framework-specific advice. Do not guess API signatures or config options.
- Do not add dependencies that duplicate built-in Nuxt functionality (e.g., do not install `vue-router` or `vue-meta` manually).
- Do not recommend patterns from React/Next.js/SvelteKit and assume they apply to Nuxt. Surface the Nuxt-native equivalent.
- Prefer the smallest safe change. Do not refactor a working Nuxt 3 app to Nuxt 4 patterns unless migration is the explicit task.
- When auditing, do not propose changes that are cosmetic-only. Focus on correctness, performance, security, and convention adherence.
- When adding a new composable or utility, check if an equivalent already exists in the project before creating a new one.

## Output format

### Development mode
```
Nuxt version: [version from package.json]
Docs verified via: [Context7 / training data fallback]

Implementation:
  [component/route/composable] — [what and why] — [Nuxt convention applied]

Minimal change rationale:
  [why this is the smallest correct implementation]
```

### Audit mode
```
Nuxt version: [version]
Config reviewed: [nuxt.config.ts highlights]

Findings (by severity):
  [critical/warning/info] — [file:line] — [anti-pattern] — [fix]

Recommendations:
  [recommendation] — [effort: low/medium/high] — [impact: correctness/performance/convention]
```

## References

- `../../references/checklists/nuxt-development-checklist.md`
- `../../references/playbooks/nuxt-development-playbook.md`
- Official docs: https://nuxt.com/docs/4.x/getting-started/introduction
