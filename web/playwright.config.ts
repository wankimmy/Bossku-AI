import { defineConfig, devices } from '@playwright/test'

const mockPort = 8001
const mockBase = `http://127.0.0.1:${mockPort}`
// Default off 3000 to match the repo's 284xx port scheme (README) and avoid
// reuseExistingServer silently binding to an unrelated app already on :3000.
const webPort = Number(process.env.E2E_WEB_PORT || 28471)
const webBase = `http://127.0.0.1:${webPort}`
const nuxiBuildCommand = process.platform === 'win32'
  ? 'npm.cmd exec nuxi build'
  : 'npx nuxi build'

export default defineConfig({
  testDir: './e2e/specs',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  // Single mock API process: avoid parallel GET/PUT races on shared in-memory state.
  workers: 1,
  reporter: [['list']],
  projects: [
    {
      name: 'chromium-desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'mobile-pixel-7',
      use: {
        ...devices['Pixel 7'],
      },
    },
  ],
  webServer: [
    {
      command: `npx tsx ./e2e/server/api-mock.ts`,
      url: `${mockBase}/api/runs`,
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
      env: { ...process.env, MOCK_PORT: String(mockPort) },
    },
    {
      command: `${nuxiBuildCommand} && node .output/server/index.mjs`,
      url: webBase,
      reuseExistingServer: false,
      timeout: 300_000,
      env: {
        ...process.env,
        HOST: '127.0.0.1',
        NITRO_HOST: '127.0.0.1',
        NITRO_PORT: String(webPort),
        NODE_ENV: 'test',
        NPM_CONFIG_PRODUCTION: 'false',
        NUXT_PUBLIC_API_BASE: mockBase,
        PORT: String(webPort),
      },
    },
  ],
  use: {
    baseURL: webBase,
    trace: 'on-first-retry',
  },
})
