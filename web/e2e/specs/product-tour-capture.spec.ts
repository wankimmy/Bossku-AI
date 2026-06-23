import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'
import { expect, test, type Page } from '@playwright/test'

const tourPrompt = 'Review the access policy before release.'
const captureDir = process.env.BOSSKU_TOUR_CAPTURE_DIR

async function capture(page: Page, name: string) {
  if (!captureDir) return
  await mkdir(captureDir, { recursive: true })
  await page.screenshot({ path: join(captureDir, `${name}.png`), fullPage: false })
}

test('renders and captures the public BosskuAI product-tour states', async ({ page }) => {
  test.setTimeout(45_000)
  await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
  await page.goto('/', { waitUntil: 'load' })
  await expect(page.getByTestId('landing-conversation-tabs')).toBeVisible()
  await capture(page, '01-dashboard-empty')

  await page.locator('textarea').first().fill(tourPrompt)
  await capture(page, '02-dashboard-typed')
  await page.getByRole('button', { name: 'Send' }).first().click()

  const panels = [
    { tab: /^Agents/, panel: 'landing-panel-agents', file: '03-agents' },
    { tab: /^Plan/, panel: 'landing-panel-plan', proof: tourPrompt, file: '04-plan' },
    { tab: /^Changes/, panel: 'landing-panel-changes', proof: 'app/Policies/AccessPolicy.php', file: '05-changes' },
    { tab: /^Audit/, panel: 'landing-panel-audit', proof: 'Workspace scope needs approval', file: '06-audit' },
    { tab: /^Memory/, panel: 'landing-panel-memory', proof: 'Authorization rules from the active project.', file: '07-memory' },
  ] as const

  for (const item of panels) {
    await page.getByRole('tab').filter({ hasText: item.tab }).click()
    const panel = page.getByTestId(item.panel)
    await expect(panel).toBeVisible()
    if (item.proof) await expect(panel).toContainText(item.proof)
    await capture(page, item.file)
  }

  await expect(page.getByTestId('change-approval-modal')).toBeVisible({ timeout: 12_000 })
  await capture(page, '08-approval')

  await page.goto('/skills-graph', { waitUntil: 'load' })
  await expect(page.getByRole('heading', { name: 'Skills Graph' })).toBeVisible()
  await capture(page, '09-skills-graph')

  await page.goto('/memory', { waitUntil: 'load' })
  await expect(page.getByRole('heading', { name: 'Memory & Brain' })).toBeVisible()
  await capture(page, '10-memory-inspector')

  await page.goto('/settings/models', { waitUntil: 'load' })
  await expect(page.getByLabel('Audit step enabled')).toBeVisible()
  await capture(page, '11-model-settings')
})
