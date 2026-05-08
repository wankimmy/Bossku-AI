import { expect, test } from '@playwright/test'

test.describe('run page', () => {
  test('stream run completes and shows Final answer', async ({ page }) => {
    await page.goto('/', { waitUntil: 'load' })

    await page.locator('textarea').first().fill('Playwright SSE smoke prompt')

    await page.locator('button', { hasText: 'Stream run' }).first().click()

    await expect(page.getByRole('heading', { name: 'Final answer', exact: true })).toBeVisible({
      timeout: 20_000,
    })
    await expect(
      page.getByRole('heading', { name: 'Final answer', exact: true }).locator('xpath=following-sibling::div'),
    ).toContainText(/Mock stream done/i)
  })

  test('sync POST run surfaces final_output via alert', async ({ page }) => {
    await page.goto('/', { waitUntil: 'load' })

    page.once('dialog', async dialog => {
      expect(dialog.message()).toMatch(/Mock sync completed/)
      await dialog.dismiss()
    })

    await page.locator('textarea').first().fill('Sync orchestrator hello')
    await page.locator('button', { hasText: 'Run (sync API)' }).click()
  })
})
