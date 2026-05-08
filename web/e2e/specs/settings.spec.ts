import { expect, test } from '@playwright/test'

test.describe('settings', () => {
  test('saving planner_model sends PUT and updates mock server', async ({ page }) => {
    await page.request.post('http://127.0.0.1:8001/api/__e2e/reset')

    await page.goto('/settings', { waitUntil: 'load' })

    await page.waitForSelector('label:has-text("Planner model") input')

    const plannerInput = page.locator('label:has-text("Planner model") input')

    await plannerInput.evaluate((el) => {
      const input = el as HTMLInputElement
      input.value = 'gpt-playwright-patch'
      input.dispatchEvent(new Event('input', { bubbles: true }))
      input.dispatchEvent(new Event('change', { bubbles: true }))
    })

    const reqPromise = page.waitForRequest(
      r => r.url().includes('/api/settings') && r.method() === 'PUT',
    )

    await page.getByRole('button', { name: /Save settings/i }).click()
    const putReq = await reqPromise

    const raw = putReq.postData()
    expect(raw).toBeTruthy()
    const payload = JSON.parse(raw!) as Record<string, string>
    expect(payload.planner_model).toBe('gpt-playwright-patch')
  })
})
