# BosskuAI Public Product Tour Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Deliver a silent 70-74 second HyperFrames product tour that shows the real BosskuAI UI, types a task, animates Pixel Office activity, tours every run workspace submenu, and appears as a linked preview in the README.

**Architecture:** A deterministic E2E-only scenario will render the existing Nuxt frontend with meaningful workflow data and a pending approval. Playwright creates evidence captures from those real screens. HyperFrames composes the captures with product-native camera motion and text, then creates the final MP4 and a lightweight README preview.

**Tech Stack:** Nuxt 3, Playwright, existing web E2E mock API, HyperFrames, GSAP, FFmpeg, Markdown.

---

## File structure

| File | Responsibility |
| --- | --- |
| web/e2e/server/api-mock.ts | Prompt-scoped product-tour scenario and approval API fixture. |
| web/e2e/specs/product-tour-capture.spec.ts | Assert every required UI view and optionally create source PNGs. |
| docs/media/bosskuai-product-tour/DESIGN.md | Product-native visual identity and motion constraints. |
| docs/media/bosskuai-product-tour/index.html | 72-second HyperFrames composition. |
| docs/media/bosskuai-product-tour/assets/screens/*.png | Nuxt frontend captures consumed by the composition. |
| docs/media/bosskuai-product-tour/scripts/assert-captures.mjs | Fail when a required source PNG is absent. |
| docs/assets/bosskuai-product-tour.mp4 | Final 1280 by 720 H.264 video. |
| docs/assets/bosskuai-product-tour-preview.gif | Short linked README preview. |
| README.md | New See BosskuAI section after the introduction. |

### Task 1: Add a deterministic product-tour UI scenario

**Files:**
- Modify: web/e2e/server/api-mock.ts
- Create: web/e2e/specs/product-tour-capture.spec.ts
- Test: web/e2e/specs/product-tour-capture.spec.ts

- [ ] **Step 1: Write the failing product-tour capture test.**

Create web/e2e/specs/product-tour-capture.spec.ts. It uses the natural fixture prompt Review the access policy before release. It never reads mock-server state.

~~~ts
import { expect, test, type Page } from '@playwright/test'
import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'

const tourPrompt = 'Review the access policy before release.'
const captureDir = process.env.BOSSKU_TOUR_CAPTURE_DIR

async function capture(page: Page, name: string) {
  if (!captureDir) return
  await mkdir(captureDir, { recursive: true })
  await page.screenshot({ path: join(captureDir, name + '.png'), fullPage: false })
}

test('renders all public product-tour states', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('bossku_onboarding_v1', '1'))
  await page.goto('/', { waitUntil: 'load' })
  await expect(page.getByTestId('landing-conversation-tabs')).toBeVisible()
  await capture(page, '01-dashboard-empty')

  await page.locator('textarea').first().fill(tourPrompt)
  await capture(page, '02-dashboard-typed')
  await page.getByRole('button', { name: 'Send' }).first().click()

  const panels = [
    { tab: 'Agents', panel: 'landing-panel-agents', proof: 'Agent activity', file: '03-agents' },
    { tab: 'Plan', panel: 'landing-panel-plan', proof: tourPrompt, file: '04-plan' },
    { tab: 'Changes', panel: 'landing-panel-changes', proof: 'app/Policies/AccessPolicy.php', file: '05-changes' },
    { tab: 'Audit', panel: 'landing-panel-audit', proof: 'Workspace scope needs approval', file: '06-audit' },
    { tab: 'Memory', panel: 'landing-panel-memory', proof: 'Authorization rules from the active project.', file: '07-memory' },
  ] as const

  for (const item of panels) {
    await page.getByRole('tab', { name: item.tab }).click()
    await expect(page.getByTestId(item.panel)).toContainText(item.proof)
    await capture(page, item.file)
  }

  await expect(page.getByTestId('change-approval-modal')).toBeVisible()
  await capture(page, '08-approval')

  for (const item of [
    { path: '/skills-graph', proof: 'Skills Graph', file: '09-skills-graph' },
    { path: '/memory', proof: 'Memory & Brain', file: '10-memory-inspector' },
    { path: '/settings/models', proof: 'Audit step enabled', file: '11-model-settings' },
  ]) {
    await page.goto(item.path, { waitUntil: 'load' })
    await expect(page.getByText(item.proof)).toBeVisible()
    await capture(page, item.file)
  }
})
~~~

- [ ] **Step 2: Run the focused test and record the expected failure.**

Run:

~~~bash
E2E_WEB_PORT=28472 npx playwright test e2e/specs/product-tour-capture.spec.ts --project=chromium-desktop
~~~

Expected: FAIL because the current generic stream does not populate the Plan, Changes, Audit, and Memory panels and does not create a pending approval dialog.

- [ ] **Step 3: Add the prompt-scoped stream and approval fixture.**

In web/e2e/server/api-mock.ts, define this constant beside the existing fixture constants:

~~~ts
const productTourPrompt = 'Review the access policy before release.'
~~~

In the existing POST or GET /api/runs/stream route, immediately after trimmedPrompt is available and before the generic stream events, branch only when trimmedPrompt equals productTourPrompt. Emit the following ordered events with the current runId:

| Event | Agent | Required artifacts |
| --- | --- | --- |
| memory_loaded | memory | snippets: Authorization rules from the active project. |
| planner_completed | planner | plan goal equal to the prompt; todos Trace policy usage completed, Check tenant boundaries in_progress, Run focused verification pending |
| executor_started | executor | status running |
| executor_completed | executor | files_read with app/Policies/AccessPolicy.php, files_changed modifying that path, tests_run with php artisan test --filter=AccessPolicy and passed status |
| auditor_completed | auditor | one medium authorization finding titled Workspace scope needs approval with needs_review status |
| approval_requested | executor | stage executor_approvals, status waiting, artifacts.pending_count equal to 1 |

Finish the branch after approval_requested so the frontend remains paused. Add GET /api/runs/{runId}/approvals for this runId. Return stage executor_approvals and one pending file_change with a stable id, medium risk, executor as evidence.asking_agent, the reason that the change affects access boundaries, and non-empty before, after, and diff content. Do not change generic mock behavior or any production route.

- [ ] **Step 4: Capture the real frontend views.**

Run:

~~~bash
BOSSKU_TOUR_CAPTURE_DIR=../docs/media/bosskuai-product-tour/assets/screens E2E_WEB_PORT=28472 npx playwright test e2e/specs/product-tour-capture.spec.ts --project=chromium-desktop
~~~

Expected: PASS and write exactly eleven 1280 by 800 PNGs named 01-dashboard-empty.png through 11-model-settings.png. Every image comes from the Nuxt page.

- [ ] **Step 5: Commit the capture foundation.**

~~~bash
git add web/e2e/server/api-mock.ts web/e2e/specs/product-tour-capture.spec.ts docs/media/bosskuai-product-tour/assets/screens
git commit -m "test: add BosskuAI product tour captures"
~~~

### Task 2: Create the HyperFrames source project

**Files:**
- Create: docs/media/bosskuai-product-tour/DESIGN.md
- Create: docs/media/bosskuai-product-tour/index.html
- Create: docs/media/bosskuai-product-tour/scripts/assert-captures.mjs
- Test: docs/media/bosskuai-product-tour/scripts/assert-captures.mjs

- [ ] **Step 1: Scaffold the composition.**

Run from docs/media:

~~~bash
npx hyperframes init bosskuai-product-tour --non-interactive
~~~

Expected: a HyperFrames project with index.html. Keep the generated runtime files only if the CLI requires them.

- [ ] **Step 2: Add the approved visual identity.**

Create DESIGN.md with this complete content:

~~~md
# BosskuAI Product Tour Design

## Style Prompt

Product-native developer workflow film. Use the real BosskuAI emerald and zinc UI, clean system sans typography, deliberate camera moves, and high-contrast readable proof. The interface is the hero, not a decorative marketing frame.

## Colors

- Canvas: #09090B
- Surface: #18181B
- Border: #3F3F46
- Active: #059669
- Focus: #34D399
- Decision: #F59E0B
- Text: #F4F4F5

## Typography

- ui-sans-serif, system-ui, sans-serif
- 700 for titles, 400-600 for labels and body copy

## What NOT to Do

- No gradients, neon glow, or fake dashboard art.
- No unverified metrics or fabricated clean audit state.
- No small copy, abrupt cuts, or outgoing scene exits before transitions.
- No external Pixel Office animation beyond captured product behavior.
~~~

- [ ] **Step 3: Write the source-capture verifier and confirm the complete input set.**

Create scripts/assert-captures.mjs:

~~~js
import { access } from 'node:fs/promises'
import { resolve } from 'node:path'

const required = [
  '01-dashboard-empty.png', '02-dashboard-typed.png', '03-agents.png', '04-plan.png',
  '05-changes.png', '06-audit.png', '07-memory.png', '08-approval.png',
  '09-skills-graph.png', '10-memory-inspector.png', '11-model-settings.png',
]
const screens = resolve(import.meta.dirname, '../assets/screens')

for (const file of required) await access(resolve(screens, file))
console.log('Verified 11 BosskuAI product captures.')
~~~

~~~bash
node scripts/assert-captures.mjs
~~~

Expected: Verified 11 BosskuAI product captures.

### Task 3: Compose the cinematic product tour

**Files:**
- Modify: docs/media/bosskuai-product-tour/index.html
- Test: HyperFrames lint, validation, visual inspection, and animation map

- [ ] **Step 1: Build the static hero layout before motion.**

Create one main composition with id bosskuai-product-tour, start 0, duration 72, track index 0, width 1280, and height 720. Each scene needs a full-screen frame, a real screenshot in a crop-safe wrapper, a title, and a compact caption. Use this exact order:

| Seconds | Capture and proof |
| --- | --- |
| 0-8 | 01-dashboard-empty plus 02-dashboard-typed; animated text overlay inside the real composer |
| 8-16 | 03-agents with the real Pixel Office and agent activity |
| 16-24 | 03-agents, focusing on the Agents submenu |
| 24-32 | 04-plan, focusing on the Plan submenu |
| 32-40 | 05-changes, focusing on the Changes submenu |
| 40-48 | 06-audit and 08-approval, focusing on audit evidence and the human decision |
| 48-56 | 07-memory, focusing on the Memory submenu |
| 56-65 | 09-skills-graph, 10-memory-inspector, and 11-model-settings as a readable three-panel recap |
| 65-72 | 01-dashboard-empty wide closing view |

The title sequence is ASK WITH CONTEXT, WATCH THE TEAM WORK, FOLLOW THE HANDOFF, PLAN BEFORE EDITING, KEEP THE SCOPE VISIBLE, PAUSE. INSPECT. DECIDE., KEEP THE CONTEXT, ROUTE THE RIGHT EXPERTISE, and CURSOR. CLAUDE CODE. CODEX. OPENCODE.

- [ ] **Step 2: Add the deterministic type-in and camera choreography.**

Register one paused GSAP timeline synchronously. Use the screenshot's empty composer as the base and insert only the approved prompt in a clipped overlay. Use these core expressions:

~~~js
const typedPrompt = 'Review the access policy before release.'
const typing = document.querySelector('#typed-prompt')
const tl = gsap.timeline({ paused: true })

for (let i = 1; i <= typedPrompt.length; i += 1) {
  tl.call(() => { typing.textContent = typedPrompt.slice(0, i) }, [], 0.6 + i * 0.045)
}

tl.from('#dashboard-frame', { opacity: 0, scale: 0.94, duration: 0.7, ease: 'power3.out' }, 0.2)
tl.from('#dashboard-title', { opacity: 0, y: 32, duration: 0.55, ease: 'power2.out' }, 0.55)
tl.to('#dashboard-frame', { scale: 1.22, x: -110, y: -40, duration: 1.1, ease: 'sine.inOut' }, 5.9)

window.__timelines = window.__timelines || {}
window.__timelines['bosskuai-product-tour'] = tl
~~~

Give each later scene separate gsap.from entrances for the image, focus ring, title, and caption. Use a cross-dissolve or wipe transition between scenes. Never fade out the previous scene before its transition begins. The only final exit is the closing fade after second 72.

- [ ] **Step 3: Validate the source before rendering.**

Run from docs/media/bosskuai-product-tour:

~~~bash
npx hyperframes lint
npx hyperframes validate
npx hyperframes inspect --samples 18
node ../../../../.codex/skills/hyperframes/scripts/animation-map.mjs . --out .hyperframes/anim-map
~~~

Expected: every command exits 0. The animation map must show no unreviewed collisions, invisible hero content, offscreen end states, or dead zones over one second except the final closing hold.

- [ ] **Step 4: Commit the editable composition.**

~~~bash
git add docs/media/bosskuai-product-tour
git commit -m "feat: add BosskuAI product tour composition"
~~~

### Task 4: Render and publish the README experience

**Files:**
- Create: docs/assets/bosskuai-product-tour.mp4
- Create: docs/assets/bosskuai-product-tour-preview.gif
- Modify: README.md
- Test: media metadata, README paths, focused unit and E2E tests

- [ ] **Step 1: Render the draft and inspect all proof moments.**

~~~bash
npx hyperframes render --output ../../assets/bosskuai-product-tour-draft.mp4 --fps 24 --quality draft --strict
~~~

Expected: duration from 70 to 74 seconds. Review frames at 0:03, 0:10, 0:19, 0:27, 0:36, 0:44, 0:52, 1:00, and 1:08. Confirm readable type-in, active Pixel Office, every workspace submenu, pending approval dialog, and closing dashboard.

- [ ] **Step 2: Render final assets.**

~~~bash
npx hyperframes render --output ../../assets/bosskuai-product-tour.mp4 --fps 24 --quality high --strict
ffmpeg -y -ss 0 -t 8 -i ../../assets/bosskuai-product-tour.mp4 -vf "fps=10,scale=960:-2:flags=lanczos" ../../assets/bosskuai-product-tour-preview.gif
~~~

Expected: MP4 is H.264 at 1280 by 720 and under 25 MB. GIF is under 6 MB and shows the typed-composer opening.

- [ ] **Step 3: Insert the public preview after the README introduction.**

Insert this exact Markdown directly before the What BosskuAI does heading:

~~~md
## See BosskuAI

[![Watch the BosskuAI product tour](docs/assets/bosskuai-product-tour-preview.gif)](docs/assets/bosskuai-product-tour.mp4)

Watch the full product tour: type a task, follow the agents in Pixel Office, inspect every run workspace, approve risky work, review the audit, and keep the context for the next run.
~~~

- [ ] **Step 4: Verify the final output and regressions.**

Run:

~~~bash
ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height,r_frame_rate -of default=nokey=0:noprint_wrappers=1 docs/assets/bosskuai-product-tour.mp4
test -s docs/assets/bosskuai-product-tour-preview.gif
test -s docs/assets/bosskuai-product-tour.mp4
rg -n 'bosskuai-product-tour-preview.gif|bosskuai-product-tour.mp4' README.md
npm test -- --run web/tests/approval-stream.spec.ts web/tests/plan-overview.spec.ts
E2E_WEB_PORT=28472 npx playwright test e2e/specs/product-tour-capture.spec.ts --project=chromium-desktop
git diff --check
~~~

Expected: codec_name=h264, width=1280, height=720, r_frame_rate=24/1, both assets are non-empty, the README has both relative paths, tests pass, and the diff check is silent.

- [ ] **Step 5: Commit the public deliverables.**

~~~bash
git add README.md docs/assets/bosskuai-product-tour.mp4 docs/assets/bosskuai-product-tour-preview.gif
git commit -m "docs: showcase BosskuAI product tour"
~~~

## Plan self-review

- Spec coverage: Task 1 produces real app states and Pixel Office activity. Task 2 locks the approved product-native identity. Task 3 implements every workspace submenu and camera-motion requirement. Task 4 renders, exposes, and verifies the public README assets.
- Scope: Product behavior remains unchanged. The richer data sequence activates only in the E2E mock for the exact capture prompt.
- Verification: browser evidence capture, source capture checks, HyperFrames lint and contrast validation, layout inspection, animation-map review, media metadata checks, README path checks, focused unit tests, E2E tests, and Git whitespace validation are all required.
