import { expect, test } from '@playwright/test'

const links = [
  { label: 'Chat', href: '/' },
  { label: 'Conversations', href: '/conversations' },
  { label: 'Dashboard', href: '/dashboard' },
  { label: 'Runs', href: '/runs' },
  { label: 'Project', href: '/project' },
  { label: 'Agents', href: '/agents' },
  { label: 'Personas', href: '/personas' },
  { label: 'Data', href: '/data' },
  { label: 'Skills', href: '/skills' },
  { label: 'Memory & Brain', href: '/memory' },
  { label: 'Knowledge', href: '/knowledge' },
  { label: 'Settings', href: '/settings/models' },
]

test.describe('navigation', () => {
  test('desktop: header main nav lists all primary links', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium-desktop', 'desktop layout only')
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))

    await page.goto('/', { waitUntil: 'load' })
    const nav = page.locator('aside').first().getByRole('navigation')
    await expect(nav).toBeVisible()
    for (const link of links) {
      const item = nav.locator(`a[href="${link.href}"]`)
      await expect(item).toBeVisible()
      await expect(item).toContainText(link.label)
    }
  })

  test('mobile: Menu toggles panel and links navigate', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-pixel-7', 'mobile layout only')
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))

    await page.goto('/skills', { waitUntil: 'load' })
    expect(await page.evaluate(() => window.innerWidth)).toBeLessThan(768)
    const menuBtn = page.getByRole('button', { name: 'Open menu' })
    await expect(menuBtn).toBeVisible()

    const panel = page.locator('body > div.fixed.inset-0.z-40')

    await expect(panel).toHaveCount(0)
    await menuBtn.click()
    await expect(panel).toBeVisible({ timeout: 10_000 })

    const mobileNav = panel.getByRole('navigation')
    for (const link of links) {
      const item = mobileNav.locator(`a[href="${link.href}"]`)
      await expect(item).toBeVisible()
      await expect(item).toContainText(link.label)
    }

    await mobileNav.locator('a[href="/settings/models"]').click()
    await expect(page).toHaveURL(/\/settings\/models$/)
    await expect(panel).toHaveCount(0)
  })
})
