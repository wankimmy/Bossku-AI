# BosskuAI Web E2E Tests

These Playwright smoke tests cover the Nuxt web app. They use a small Node mock API, not Laravel or Docker.

## Setup

From `web/`:

```bash
npm install
npm run e2e:install
```

## Run

From `web/`:

```bash
npm run e2e
```

Playwright starts the mock API and Nuxt dev server through `playwright.config.ts`.

## Manual Debugging

Start only the mock API:

```bash
npm run e2e:mock
```

Then start Nuxt in another terminal with the mock API as the backend:

PowerShell:

```powershell
$env:NUXT_PUBLIC_API_BASE="http://127.0.0.1:8001"
npm run dev
```

macOS or Linux:

```bash
export NUXT_PUBLIC_API_BASE="http://127.0.0.1:8001"
npm run dev
```

## Test Projects

- `chromium-desktop` - desktop viewport
- `mobile-pixel-7` - mobile viewport and touch behavior

## Fixtures

Mock data lives in `e2e/fixtures/`. The mock server is `e2e/server/api-mock.ts`.

Use `npm test` for Vitest unit/component tests. Use `npm run e2e` for browser smoke tests.
