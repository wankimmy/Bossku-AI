import { defineConfig, devices } from '@playwright/test'

const mockPort = 8001
const mockBase = `http://127.0.0.1:${mockPort}`

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
      command: 'npx nuxi dev --port 3000 --host 127.0.0.1',
      url: 'http://127.0.0.1:3000',
      reuseExistingServer: !process.env.CI,
      timeout: 180_000,
      env: {
        ...process.env,
        NUXT_PUBLIC_API_BASE: mockBase,
      },
    },
  ],
  use: {
    baseURL: 'http://127.0.0.1:3000',
    trace: 'on-first-retry',
  },
})
