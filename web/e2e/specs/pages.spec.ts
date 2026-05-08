import { expect, test } from '@playwright/test'

type Row = { path: string; heading: RegExp }

const routes: Row[] = [
  { path: '/', heading: /^\s*Run task\s*$/ },
  { path: '/skills', heading: /^\s*Skills\s*$/ },
  { path: '/skills/sk_1', heading: /^\s*Laravel playbook\s*$/ },
  { path: '/rules', heading: /^\s*Rules\s*$/ },
  { path: '/playbooks', heading: /^\s*Playbooks\s*$/ },
  { path: '/playbooks/pb_1', heading: /^\s*Deploy checklist playbook\s*$/ },
  { path: '/checklists', heading: /^\s*Checklists\s*$/ },
  { path: '/checklists/cl_1', heading: /^\s*Release QA\s*$/ },
  { path: '/memory', heading: /^\s*Memory inspector\s*$/ },
  { path: '/runs', heading: /^\s*Run history\s*$/ },
  { path: '/runs/r_1', heading: /^\s*Run detail\s*$/ },
  { path: '/settings', heading: /^\s*Settings\s*$/ },
]

test.describe('page smoke', () => {
  for (const { path, heading } of routes) {
    test(`${path} renders expected title and no doc overflow at 390px`, async ({ page }) => {
      const consoleErrors: string[] = []
      const pageErrors: string[] = []
      page.on('console', msg => {
        if (msg.type() === 'error') consoleErrors.push(msg.text())
      })
      page.on('pageerror', err => pageErrors.push(String(err)))

      await page.goto(path, { waitUntil: 'load' })
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading)

      await page.setViewportSize({ width: 390, height: 844 })
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
