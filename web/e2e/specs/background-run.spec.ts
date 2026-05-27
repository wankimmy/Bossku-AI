import { expect, test } from '@playwright/test'

test('reconnects active run from session storage and shows top bar link', async ({ page }) => {
  const convId = 'conv_e2e_bg'
  const runId = 'run_e2e_background'
  const now = Date.now()

  await page.addInitScript(({ convId, runId, now }) => {
    localStorage.setItem('bossku_onboarding_v1', '1')
    localStorage.setItem(
      'bossku_landing_chat_v2',
      JSON.stringify({
        version: 2,
        activeId: convId,
        conversations: [
          {
            id: convId,
            title: 'Background run test',
            updatedAt: now,
            createdAt: now,
            turns: [{ id: 't1', role: 'user', content: 'Long task in progress', createdAt: now }],
            runEvents: [],
            activeRunId: runId,
          },
        ],
      }),
    )
    sessionStorage.setItem(
      'bossku_active_run_v1',
      JSON.stringify({ convId, runId, lastSeq: 0 }),
    )
  }, { convId, runId, now })

  await page.goto('/usage', { waitUntil: 'load' })

  const activeLink = page.getByRole('link', { name: /Run active/i })
  await expect(activeLink).toBeVisible({ timeout: 15_000 })
  await expect(activeLink).toContainText(/running/i)

  await activeLink.click()
  await expect(page).toHaveURL(new RegExp(`conv=${convId}`))
})
