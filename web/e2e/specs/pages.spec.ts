import { expect, test } from '@playwright/test'

type Row = { path: string; heading: RegExp }

const routes: Row[] = [
  { path: '/', heading: /^\s*BosskuAI agent workspace\s*$/ },
  { path: '/skills', heading: /^\s*Skills\s*$/ },
  { path: '/skills/sk_1', heading: /^\s*Laravel playbook\s*$/ },
  { path: '/rules', heading: /^\s*Rules\s*$/ },
  { path: '/playbooks', heading: /^\s*Playbooks\s*$/ },
  { path: '/playbooks/pb_1', heading: /^\s*Deploy checklist playbook\s*$/ },
  { path: '/checklists', heading: /^\s*Checklists\s*$/ },
  { path: '/checklists/cl_1', heading: /^\s*Release QA\s*$/ },
  { path: '/memory', heading: /^\s*Memory & Brain\s*$/ },
  { path: '/knowledge', heading: /^\s*Knowledge\s*$/ },
  { path: '/runs', heading: /^\s*Run history\s*$/ },
  { path: '/runs/r_1', heading: /^\s*Run r_1\s*$/ },
  { path: '/settings', heading: /^\s*Settings\s*$/ },
]

test.describe('page smoke', () => {
  for (const { path, heading } of routes) {
    test(`${path} renders expected title and no doc overflow at 390px`, async ({ page }) => {
      const consoleErrors: string[] = []
      const pageErrors: string[] = []
      page.on('console', msg => {
        if (msg.type() !== 'error') return
        const text = msg.text()
        if (text.includes('Hydration completed but contains mismatches')) return
        consoleErrors.push(text)
      })
      page.on('pageerror', err => pageErrors.push(String(err)))

      await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
      await page.setViewportSize({ width: 390, height: 844 })
      await page.goto(path, { waitUntil: 'load' })
      await expect(page.getByRole('heading', { level: 1, name: heading }).first()).toBeVisible()
      await page.waitForTimeout(200)

      const docOverflow = await page.evaluate(() => {
        const el = document.documentElement
        return el.scrollWidth > el.clientWidth + 2
      })
      expect(docOverflow, `unexpected horizontal overflow on ${path}`).toBe(false)

      await expect.poll(() => consoleErrors.join('\n'), { timeout: 5_000 }).toBe('')
      expect(pageErrors, `runtime errors on ${path}`).toEqual([])
    })
  }
})
