import { expect, test } from '@playwright/test'

test.describe('memory page', () => {
  test('search shows JsonViewer hits and refresh keeps list', async ({ page }) => {
    await page.goto('/', { waitUntil: 'load' })
    await page.goto('/memory', { waitUntil: 'load' })

    await page.getByPlaceholder('Semantic search').fill('anything')

    await Promise.all([
      page.waitForResponse(
        r => r.url().includes('/api/memory/search') && r.request().method() === 'POST',
        { timeout: 25_000 },
      ),
      page.getByRole('button', { name: 'Search' }).click(),
    ])

    await expect(page.locator('pre').filter({ hasText: 'mock-search hit one' }).first()).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.locator('pre').filter({ hasText: 'hit-1' }).first()).toBeVisible()

    await page.getByRole('button', { name: /Refresh list/i }).click()
    await expect(page.getByText('Remember to run Playwright after UI changes')).toBeVisible()
  })
})
