import { expect, test } from '@playwright/test'

test.describe('run page', () => {
  test('stream run completes and shows Final answer', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
    await page.goto('/', { waitUntil: 'load' })

    await page.locator('textarea').first().fill('Playwright SSE smoke prompt')

    await page.getByRole('button', { name: 'Send' }).first().click()

    await expect(page.getByText(/Mock stream done/i).first()).toBeVisible({
      timeout: 20_000,
    })
  })

  test('sync POST run surfaces final_output in the conversation', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
    await page.goto('/', { waitUntil: 'load' })

    await page.locator('textarea').first().fill('Sync orchestrator hello')
    await page.getByRole('button', { name: 'Run sync API' }).click()
    await expect(page.getByText(/Mock sync completed/i).first()).toBeVisible({ timeout: 20_000 })
  })

  test('short smoke chat stays in chat without final result chrome', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
    await page.goto('/', { waitUntil: 'load' })

    await page.locator('textarea').first().fill('test')
    await page.getByRole('button', { name: 'Send' }).first().click()

    await expect(page.getByTestId('chat-thread-scroll')).toBeVisible()
    await expect(page.getByText('BosskuAI is running. Your prompt "test" was received.').first()).toBeVisible({
      timeout: 20_000,
    })
    await expect(page.getByText('[BOSSKUAI]')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Final result' })).toHaveCount(0)
    await expect(page.getByText('final-reviewer')).toHaveCount(0)
  })

  test('long pasted prompt is compacted in chat and sent to the agent', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
    await page.goto('/', { waitUntil: 'load' })

    const longPrompt = [
      'Please analyze this long pasted log.',
      'START_VISIBLE',
      'A'.repeat(26_000),
      'MIDDLE_SECRET_SHOULD_NOT_BE_STORED',
      'B'.repeat(26_000),
      'END_VISIBLE',
    ].join('\n')

    await page.locator('textarea').first().fill(longPrompt)
    await page.getByRole('button', { name: 'Send' }).first().click()

    await page.getByRole('tab', { name: /Chat/ }).click()
    await expect(page.getByText(`Long prompt attached (${longPrompt.length} chars)`).first()).toBeVisible({
      timeout: 20_000,
    })
    await expect(page.getByText('START_VISIBLE').first()).toBeVisible()
    await expect(page.getByText('END_VISIBLE').first()).toBeVisible()
    await expect(page.getByText('MIDDLE_SECRET_SHOULD_NOT_BE_STORED')).toHaveCount(0)
    await expect(page.getByText(/Mock stream done for long prompt attachment/).first()).toBeVisible({
      timeout: 20_000,
    })
    await expect(page.getByText('The prompt field must not be greater than 50000 characters.')).toHaveCount(0)
  })
})
