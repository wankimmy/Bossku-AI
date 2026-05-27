import { expect, test } from '@playwright/test'

test.describe('onboarding spotlight', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.removeItem('bossku_onboarding_v1')
    })
  })

  test('first visit shows tour and skip dismisses it', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' })
    const dialog = page.getByRole('dialog', { name: /Welcome to BosskuAI/i })
    await expect(dialog).toBeVisible({ timeout: 5000 })
    await expect(dialog.getByText('1/8')).toBeVisible()
    await page.getByRole('button', { name: 'Skip tour' }).click()
    await expect(dialog).toBeHidden({ timeout: 3000 })
    await expect(page.getByRole('dialog')).toHaveCount(0)
  })

  test('help menu can restart tour', async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('bossku_onboarding_v1', '1')
    })
    await page.goto('/', { waitUntil: 'networkidle' })
    await expect(page.getByRole('dialog')).toHaveCount(0)
    await page.getByRole('button', { name: 'Help' }).click()
    await page.getByRole('button', { name: 'Take a tour' }).click()
    await expect(page.getByRole('dialog', { name: /Welcome to BosskuAI/i })).toBeVisible({ timeout: 5000 })
  })
})
