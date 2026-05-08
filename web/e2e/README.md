# BosskuAI Web – Playwright smoke tests

End-to-end tests live under **`e2e/specs/`** and run against **Nuxt dev** plus a **tiny Node mock API** (no Laravel/Docker).

## Prerequisites

- Node/npm as for the rest of `web/`
- One-time browsers: **`npm run e2e:install`** (Chromium bundle for Playwright)

## Commands

From **`web/`**:

```bash
npm run e2e:install    # once per machine (~300 MB Chromium)
npm run e2e            # playwright test (starts mock + nuxi dev via playwright.config.ts)
```

Optional — run mock only (manual Nuxt debugging):

```bash
npm run e2e:mock
# then in another terminal, with NUXT_PUBLIC_API_BASE=http://127.0.0.1:8001
npm run dev
```

## Projects

Configured in **`playwright.config.ts`**:

- **`chromium-desktop`** — 1280×800
- **`mobile-pixel-7`** — device preset (touches header **Menu**, bottom nav patterns, overflow checks)

Shared config uses **`workers: 1`** so the in-memory mock does not race on CRUD-ish routes.

## Mock API

Implementation: **`e2e/server/api-mock.ts`**  
JSON bodies: **`e2e/fixtures/*.json`**

Coverage mirrors **[`app/routes/api.php`](../../app/routes/api.php)** (runs, skills, rules, playbooks, checklists, memory, settings, SSE stream, knowledge import stub).

Test-only endpoints:

- **`POST /api/__e2e/reset`** — reset settings + last-PUT capture (used by settings spec)
- **`GET /api/__e2e/last-settings-put`** — optional debugging (body of last `PUT /api/settings`)

## Vitest vs Playwright

- **`npm test`** — unit/component tests (**Vitest**), no browser
- **`npm run e2e`** — browser smoke (**Playwright**)
