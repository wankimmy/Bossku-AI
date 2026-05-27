import { expect, test } from '@playwright/test'

test('restores saved chat and agent process after a completed run', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('bossku_onboarding_v1', '1')
  })

  await page.goto('/', { waitUntil: 'load' })

  await page.locator('textarea').first().fill('Seeded restore prompt')
  await page.getByRole('button', { name: 'Run task' }).first().click()
  await expect(page.getByText(/Mock stream done for:/i).first()).toBeVisible({
    timeout: 20_000,
  })
  await expect(page).toHaveURL(/conv=/)

  const tabs = page.getByTestId('landing-conversation-tabs')
  await tabs.getByRole('tab', { name: /Agent Process/i }).click()
  await expect(page.getByText('Agent transcript')).toBeVisible()
  await expect(page.getByText(/Mock stream done for:/i).first()).toBeVisible()

  await page.reload({ waitUntil: 'load' })

  const restoredTabs = page.getByTestId('landing-conversation-tabs')
  await restoredTabs.getByRole('tab', { name: /^Chat/i }).click()
  await expect(page.getByText('Seeded restore prompt')).toBeVisible()
  await expect(page.getByText(/Mock stream done for:/i).first()).toBeVisible()
})
