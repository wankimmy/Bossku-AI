import { expect, test } from '@playwright/test'

const PATHS_FOR_OVERFLOW = ['/', '/skills', '/skills/sk_1', '/rules', '/playbooks', '/playbooks/pb_1', '/checklists', '/checklists/cl_1', '/memory', '/runs', '/runs/r_1', '/settings']

async function worstChildOverflowPct(page: import('@playwright/test').Page): Promise<number> {
  return page.evaluate(() => {
    let worst = 0
    const vw = window.innerWidth
    const hasHorizontalScroller = (el: HTMLElement) => {
      let node: HTMLElement | null = el.parentElement
      while (node) {
        const style = window.getComputedStyle(node)
        if (/(auto|scroll)/.test(style.overflowX) && node.scrollWidth > node.clientWidth + 2) {
          return true
        }
        node = node.parentElement
      }
      return false
    }

    for (const el of Array.from(document.querySelectorAll('*'))) {
      if (!(el instanceof HTMLElement)) continue
      if (hasHorizontalScroller(el)) continue
      const r = el.getBoundingClientRect()
      if (!r.width || !r.height) continue
      const over = Math.max(0, r.right - vw)
      if (over <= 1) continue
      worst = Math.max(worst, over / vw)
    }
    return Math.round(worst * 1000) / 1000
  })
}

test.describe('mobile layout patterns', () => {
  test('Pixel 7: no element visibly spills past viewport', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-pixel-7', 'mobile-only invariant')

    for (const path of PATHS_FOR_OVERFLOW) {
      await page.goto(path)
      await page.waitForTimeout(200)
      const spill = await worstChildOverflowPct(page)
      expect(spill, `layout spill fraction on ${path}`).toBeLessThanOrEqual(0.02)
    }
  })

  test('skill markdown pre accepts horizontal scroll internally', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-pixel-7')

    await page.goto('/skills/sk_1')

    const pre = page.getByRole('heading', { name: 'Source markdown' }).locator('xpath=following-sibling::pre')
    await expect(pre).toBeVisible()
    const canScrollWide = await pre.evaluate((el) => el.scrollWidth > el.clientWidth)
    expect(canScrollWide, 'fixture should produce wide pre').toBe(true)
  })

  test('sticky mobile Run bar only on home route', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-pixel-7')
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))

    const stickyRun = page.locator('.fixed.inset-x-0.bottom-0').getByRole('button', { name: 'Run task' })

    await page.goto('/')
    await expect(stickyRun).toBeVisible()

    await page.goto('/memory')
    await expect(stickyRun).toHaveCount(0)
  })
})
