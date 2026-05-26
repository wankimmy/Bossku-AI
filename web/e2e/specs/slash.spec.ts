import { expect, test } from '@playwright/test'
import { resolve } from 'node:path'

test.describe('slash commands', () => {
  test('opens the slash menu and inserts project understanding without running', async ({ page }) => {
    await page.request.post('http://127.0.0.1:8001/api/__e2e/reset')
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))

    await page.goto('/', { waitUntil: 'load' })
    await page.waitForLoadState('networkidle')
    const prompt = page.getByLabel('Message')

    await prompt.fill('/')
    await expect(page.getByRole('option', { name: '/project-understanding' })).toBeVisible()

    await prompt.fill('/build')
    await expect(page.getByRole('option', { name: /build fixer/i })).toBeVisible()

    await prompt.fill('/project-understanding')
    await page.getByRole('option', { name: '/project-understanding' }).click()

    await expect(prompt).toHaveValue(/Inspect the active repository first/)
    await expect(page.getByText('Slash commands', { exact: true })).toHaveCount(0)
  })

  test('project page shows project-understanding CTA and manual path registration still works', async ({ page }) => {
    await page.request.post('http://127.0.0.1:8001/api/__e2e/reset')
    await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))

    await page.goto('/project', { waitUntil: 'load' })
    await page.waitForLoadState('networkidle')

    await expect(page.getByRole('heading', { name: 'Project', exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Run /project-understanding' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Open Folder' })).toBeVisible()

    const hostPath = `${resolve(process.cwd(), '..').replace(/\\/g, '/')}/demo-app`

    await page.getByPlaceholder('Name (e.g. my-app)').fill('Demo app')
    await page.getByPlaceholder('Host path (e.g. C:/dev/projects/my-app)').fill(hostPath)
    const registerResponse = page.waitForResponse(
      resp => resp.url().includes('/api/project/register') && resp.request().method() === 'POST',
    )
    await page.getByRole('button', { name: 'Add path' }).click()
    const register = await registerResponse
    expect(register.ok()).toBe(true)
    const listResponse = await page.request.get('http://127.0.0.1:8001/api/project/list')
    const list = await listResponse.json() as { projects?: Array<{ id: string; name: string }> }
    const demoProject = list.projects?.find(project => project.name === 'Demo app')
    expect(demoProject).toBeTruthy()
    await page.request.post(`http://127.0.0.1:8001/api/project/${demoProject!.id}/activate`)
    await page.reload({ waitUntil: 'networkidle' })

    await expect(page.locator('section').filter({ hasText: 'Active project ready' })).toContainText('Demo app')
  })
})
