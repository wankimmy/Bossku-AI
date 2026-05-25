import { expect, test } from '@playwright/test'

test.describe('knowledge page', () => {
  test('imports URLs and local memories from the Knowledge tab', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.setItem('bossku_onboarding_v1', '1')
    })

    await page.goto('/knowledge', { waitUntil: 'load' })
    await page.waitForLoadState('networkidle')

    await expect(page.getByRole('heading', { name: 'Knowledge', exact: true })).toBeVisible()
    await expect(page.getByPlaceholder(/Paste URLs/i)).toBeVisible()
    await expect(page.getByRole('button', { name: 'Import URLs' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Import Codex Memory' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Import Claude Memory' })).toBeVisible()

    await page.getByPlaceholder(/Paste URLs/i).fill('https://example.com/article\nhttps://youtu.be/abc123XYZ09')
    await page.getByPlaceholder(/Optional tags/i).fill('research, ai')
    await page.getByPlaceholder(/Optional note/i).fill('User research dump')

    const importUrlsButton = page.getByRole('button', { name: 'Import URLs' })
    await expect(importUrlsButton).toBeEnabled()
    await importUrlsButton.click()

    await expect(page.getByText('Created 1')).toBeVisible()
    await expect(page.getByText('Mock knowledge article', { exact: true }).first()).toBeVisible()

    await page.getByRole('button', { name: 'Import Codex Memory' }).click()

    await expect(page.getByText('Imported Codex memory', { exact: true }).first()).toBeVisible()
  })
})
