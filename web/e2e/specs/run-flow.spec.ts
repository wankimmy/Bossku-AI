import { expect, test } from '@playwright/test'

test.describe('run page', () => {
  test('stream run completes and shows Final answer', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
    await page.goto('/', { waitUntil: 'load' })

    await page.locator('textarea').first().fill('Playwright SSE smoke prompt')

    await page.getByRole('button', { name: 'Run task' }).first().click()

    await expect(page.getByText(/Mock stream done/i).first()).toBeVisible({
      timeout: 20_000,
    })
  })

  test('sync POST run surfaces final_output in the conversation', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
    await page.goto('/', { waitUntil: 'load' })

    await page.locator('textarea').first().fill('Sync orchestrator hello')
    await page.getByRole('button', { name: 'Run sync API' }).click()
    await expect(page.getByText(/Mock sync completed/i).first()).toBeVisible({ timeout: 20_000 })
  })
})
