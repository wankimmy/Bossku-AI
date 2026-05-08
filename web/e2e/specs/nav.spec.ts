import { expect, test } from '@playwright/test'

const labels = ['Run', 'Skills', 'Rules', 'Playbooks', 'Checklists', 'Memory', 'Runs', 'Settings']

test.describe('navigation', () => {
  test('desktop: header main nav lists all primary links', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium-desktop', 'desktop layout only')
    await page.goto('/', { waitUntil: 'load' })
    const nav = page.locator('header nav[aria-label="Main"]')
    await expect(nav).toBeVisible()
    for (const label of labels) {
      await expect(nav.getByRole('link', { name: label, exact: true })).toBeVisible()
    }
  })

  test('mobile: Menu toggles panel and links navigate', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-pixel-7', 'mobile layout only')

    await page.goto('/skills', { waitUntil: 'load' })
    expect(await page.evaluate(() => window.innerWidth)).toBeLessThan(768)
    const menuBtn = page.getByRole('button', { name: 'Menu' })
    await expect(menuBtn).toBeVisible()
    await expect(page.locator('header nav[aria-label="Main"]')).toBeHidden()

    const panel = page.locator('#mobile-nav-panel')

    await expect(panel).toBeHidden()
    await menuBtn.click()
    await expect(panel).toBeVisible({ timeout: 10_000 })

    const mobileNav = page.locator('#mobile-nav-panel nav[aria-label="Main mobile"]')
    for (const label of labels) {
      await expect(mobileNav.getByRole('link', { name: label, exact: true })).toBeVisible()
    }

    await mobileNav.getByRole('link', { name: 'Settings', exact: true }).click()
    await expect(page).toHaveURL(/\/settings$/)
    await expect(panel).toBeHidden()
  })
})
