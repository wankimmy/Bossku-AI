import { expect, test } from '@playwright/test'

test('restores saved chat, agent process, and completed plan progress', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'desktop-only right-panel smoke')

  await page.addInitScript(() => {
    localStorage.setItem('bossku_onboarding_v1', '1')
    localStorage.setItem('bossku_landing_chat_v2', JSON.stringify({
      version: 2,
      activeId: 'conv_e2e',
      conversations: [
        {
          id: 'conv_e2e',
          title: 'Seeded landing conversation',
          createdAt: 1779667200000,
          updatedAt: 1779667300000,
          turns: [
            { id: 'turn_1', role: 'user', content: 'Seeded question', createdAt: 1779667200000 },
            { id: 'turn_2', role: 'assistant', content: 'Seeded answer', createdAt: 1779667300000 },
          ],
          runEvents: [
            {
              type: 'planner_done',
              agent: 'orchestrator',
              status: 'success',
              artifacts: {
                checklist: [
                  { id: 'plan_1', title: 'Implement seeded change', owner: 'executor', status: 'pending' },
                ],
              },
            },
            {
              type: 'executor_step_done',
              agent: 'executor',
              status: 'success',
              artifacts: {
                files_changed: [
                  { path: 'web/pages/index.vue', change_type: 'modified', summary: 'Split landing conversation view' },
                ],
              },
            },
            {
              type: 'run_completed',
              agent: 'final-reviewer',
              status: 'success',
              output: 'Seeded answer',
            },
          ],
        },
      ],
    }))
  })

  await page.goto('/?conv=conv_e2e', { waitUntil: 'load' })

  await expect(page.getByText('Agent transcript')).toBeVisible()
  await expect(page.getByText('Changed 1 file(s).')).toBeVisible()

  await page.getByRole('button', { name: /^Chat/i }).click()
  await expect(page.getByText('Seeded question')).toBeVisible()
  await expect(page.locator('pre').filter({ hasText: 'Seeded answer' }).first()).toBeVisible()

  await page.getByRole('button', { name: /Agent Process/i }).click()
  await expect(page.getByText('Agent transcript')).toBeVisible()
  await expect(page.getByText('Changed 1 file(s).')).toBeVisible()

  await page.locator('aside').getByRole('button', { name: /Plan/i }).click()
  const planItem = page.locator('summary').filter({ hasText: 'Implement seeded change' })
  await expect(planItem).toContainText('completed')
})
