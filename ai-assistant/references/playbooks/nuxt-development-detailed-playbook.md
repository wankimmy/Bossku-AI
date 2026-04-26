# Nuxt Development Playbook

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

Use this when building new features, fixing bugs, or auditing existing code in a Nuxt application.

## Development workflow

1. **Check Nuxt version and project structure.** Read `package.json` for the `nuxt` version. Read `nuxt.config.ts` for modules, rendering mode, and custom config. Check whether the project uses the Nuxt 4 `app/` directory structure or Nuxt 3 root-level structure.

2. **Fetch official docs via Context7.** Resolve the Nuxt library ID with `resolve-library-id`, then query `get-library-docs` for the specific feature, composable, or config option. Do not rely on training data for API details.

3. **Read existing project conventions.** Before writing code, read adjacent components, composables, and middleware to match the project's patterns. Do not introduce new conventions unless the existing ones are demonstrably wrong.

4. **Use framework conventions first.** Apply file-based routing, auto-imports, and built-in composables before reaching for custom solutions or third-party packages. If Nuxt provides it, use it.

5. **Implement minimally.** Write only what is needed:
   - No wrapper functions around built-in composables
   - No manual imports of auto-imported utilities
   - No abstraction layers that duplicate framework behavior
   - No redundant type annotations for auto-generated types

6. **Validate.** Run `nuxi typecheck` and `nuxi build`. Test the route in the browser. Check for hydration mismatch warnings. Verify SSR payload correctness.

## Audit workflow

1. **Read the project setup.** Check `nuxt.config.ts`, `package.json`, `tsconfig.json`. Note the Nuxt version, installed modules, rendering mode, and any custom configuration.

2. **Fetch current docs via Context7.** Confirm correct patterns for the project's specific Nuxt version. Do not apply Nuxt 4 patterns to a Nuxt 3 project unless migration is the task.

3. **Scan for anti-patterns.** Check every file against the anti-pattern table in the skill. Priority order:
   - Critical: security risks (unvalidated server inputs), broken SSR (`$fetch` in setup, non-SSR-safe state)
   - Warning: correctness issues (unhandled error states, non-reactive useHead)
   - Info: convention deviations (manual imports of auto-imported items, redundant code)

4. **Cross-check dependencies.** Review `package.json` for packages that duplicate Nuxt built-in features (`vue-router`, `vue-meta`, `axios` when `$fetch` suffices, etc.).

5. **Report findings.** Use the audit output format from the skill. Every finding must include: severity, file location, what is wrong, and how to fix it.

## Nuxt project structure reference

### Nuxt 4 (`app/` prefix)
```
app/
  pages/           # File-based routing
  components/      # Auto-imported components
  composables/     # Auto-imported composables
  layouts/         # Layout components
  middleware/      # Route middleware
  plugins/         # Nuxt plugins
  utils/           # Auto-imported utility functions
  app.vue          # Root component
  error.vue        # Error page
server/
  api/             # API routes (Nitro)
  routes/          # Server routes (Nitro)
  middleware/      # Server middleware
  utils/           # Server utilities
public/            # Static assets (served as-is)
nuxt.config.ts     # Nuxt configuration
```

### Nuxt 3 (root-level)
```
pages/             # File-based routing
components/        # Auto-imported components
composables/       # Auto-imported composables
layouts/           # Layout components
middleware/        # Route middleware
plugins/           # Nuxt plugins
utils/             # Auto-imported utility functions
server/            # Same as Nuxt 4
public/            # Static assets
app.vue            # Root component
nuxt.config.ts     # Configuration
```

## Common pitfalls

**Using `$fetch` in `<script setup>`** — This causes the request to run on both server and client. Use `useFetch` or `useAsyncData` which handle SSR payload deduplication automatically.

**Plain `ref()` for cross-component state** — A plain `ref()` created in a composable is not SSR-safe. The value initializes on the server, serializes to the client, but the `ref()` reinitializes on the client causing a hydration mismatch. Use `useState` instead.

**Manual imports of auto-imported items** — `import { ref, computed } from 'vue'` and `import { useFetch } from '#imports'` are unnecessary in Nuxt. Auto-imports handle this. Removing them reduces noise and aligns with framework conventions.

**Using `process.env` for config** — `process.env` is not reactive, not type-safe, and behaves differently on server vs client. Use `useRuntimeConfig()` with values defined in `nuxt.config.ts` under `runtimeConfig`.

**Not handling pending/error states** — `useFetch` returns `{ data, pending, error, refresh }`. Code that only destructures `data` and ignores `pending` and `error` will show broken UI during loading and silent failures on errors.

**Adding packages that duplicate Nuxt features** — Do not install `vue-router` (Nuxt handles routing), `vue-meta` or `@vueuse/head` (use `useHead`), `axios` (use `$fetch` / `useFetch`), or state management libraries when `useState` suffices.

## Output expectation

- Nuxt version and rendering mode identified
- Docs verified via Context7 (or training data fallback explicitly labeled)
- Implementation that uses framework conventions and built-in features
- Minimal-change rationale for every recommendation
- For audits: findings categorized by severity with actionable fixes
