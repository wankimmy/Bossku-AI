import { expect, test } from '@playwright/test'

test.describe('settings', () => {
  test('saving reasoning_model sends PUT and updates mock server', async ({ page }) => {
    await page.request.post('http://127.0.0.1:8001/api/__e2e/reset')
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))

    await page.goto('/settings/models', { waitUntil: 'load' })

    const reasoningSelect = page.locator('label').filter({ hasText: 'Reasoning model (planner)' }).locator('select')
    await expect(reasoningSelect).toBeVisible()
    await reasoningSelect.selectOption({ label: 'GLM 5.1' })

    const reqPromise = page.waitForRequest(
      r => r.url().includes('/api/settings') && r.method() === 'PUT',
    )

    await page.getByRole('button', { name: /Save settings/i }).click()
    const putReq = await reqPromise

    const raw = putReq.postData()
    expect(raw).toBeTruthy()
    const payload = JSON.parse(raw!) as Record<string, string>
    expect(payload.reasoning_model).toBe('glm-5.1')
  })
})
