# BosskuAI Public Product Tour Design

## Goal

Create a silent, 16:9, approximately 72-second BosskuAI product tour that makes the real application easy to understand from the README. It must show concrete screens from the app, animate a task being typed, show the Pixel Office agents working, tour the workspace submenus, use simulated camera navigation with cinematic zooms, and explain the value of the plan, approval, audit, and memory workflow.

## Audience and success criteria

The audience is a software builder deciding whether to try BosskuAI. The first ten seconds must establish both the product interface and its purpose. The video succeeds when a viewer can answer these questions without reading any other documentation:

1. What does the BosskuAI app look like?
2. How does it make AI-assisted changes more visible and controllable?
3. Which additional capabilities are available after the core workflow?

The video is a public product overview, not a tutorial. Every shown feature must be represented by a real rendered BosskuAI screen or a factual text overlay attached to that screen. In this design, "workspace submenus" means the visible run workspace tabs: Agents, Plan, Changes, Audit, and Memory.

## Visual identity

Use the existing BosskuAI application language instead of a separate marketing palette.

| Role | Token | Use |
| --- | --- | --- |
| Canvas | Zinc 950 `#09090B` | Video background and UI framing |
| Surface | Zinc 900 `#18181B` | Screen chrome and contextual panels |
| Border | Zinc 700 `#3F3F46` | Device frame and subtle separation |
| Active | Emerald 600 `#059669` | Active work, successful workflow steps, buttons |
| Readable accent | Emerald 400 `#34D399` | Large titles and focus highlights on dark surfaces |
| Human decision | Amber 500 `#F59E0B` | Approval and risk moments only |
| Primary text | Zinc 100 `#F4F4F5` | Titles and key UI labels |
| Secondary text | Zinc 300 `#D4D4D8` | Supporting copy |

- Use the app's native `ui-sans-serif, system-ui, sans-serif` stack. Do not introduce a decorative display font or another brand typeface.
- Keep titles below eight words. Use 60px or larger at a 1280 by 720 render and 24px or larger for supporting copy.
- Keep all text at WCAG AA contrast. Emerald indicates activity, never body copy. Amber appears only for human-review moments.
- Avoid gradients, neon glows, tiny simulated code editors, fabricated metrics, or generic dashboard illustrations.

Motion is deliberate and product-led: wide dashboard context, a 1.15x to 1.35x push-in on the proof, a short hold long enough to read it, then a pull-back or soft cross-dissolve to the next feature. The final video is linear, so the apparent navigation is simulated by screen captures and camera motion rather than interactive controls.

## Content and scene structure

Target duration is 70 to 74 seconds at 1280 by 720, 24 fps, silent. The full product image remains visible behind every title instead of cutting to abstract marketing cards.

| Time | Product view | On-screen copy | Proof shown |
| --- | --- | --- | --- |
| 0:00-0:08 | Home workspace and task composer | `ASK WITH CONTEXT` | A real task types into the visible composer, one phrase at a time |
| 0:08-0:16 | Pixel Office sidebar and agent activity | `WATCH THE TEAM WORK` | The rendered Pixel Office and Agent Process state show planner, executor, auditor, and final reviewer activity |
| 0:16-0:24 | Agents workspace submenu | `FOLLOW THE HANDOFF` | Agent messages and workflow progress in the real Agents tab |
| 0:24-0:32 | Plan workspace submenu | `PLAN BEFORE EDITING` | File-scoped checklist in the real Plan tab |
| 0:32-0:40 | Changes workspace submenu | `KEEP THE SCOPE VISIBLE` | Files read, files changed, commands, and tests in the real Changes tab |
| 0:40-0:48 | Audit workspace submenu and approval gate | `PAUSE. INSPECT. DECIDE.` | Audit findings plus the real Approval Required modal for risky work |
| 0:48-0:56 | Memory workspace submenu | `KEEP THE CONTEXT` | Run context, Memory tab, and Memory Inspector content |
| 0:56-1:05 | Slash skills, Skills Graph, and model settings montage | `ROUTE THE RIGHT EXPERTISE` | Skills, model roles, audit, plan mode, and optional local inference |
| 1:05-1:12 | Full dashboard, calm pull-back | `CURSOR. CLAUDE CODE. CODEX. OPENCODE.` | BosskuAI returns to the real app while naming the supported tool surfaces |

The expanded features appear in the final montage because they support the core promise but should not displace it. The five workspace tabs receive a complete, ordered tour before the montage. Staff, project, run history, and other feature pages are not required unless they make the captured app sequence more coherent.

## Screen-capture rules

Capture the Nuxt app itself, not hand-built mock dashboard art. Deterministic local data from the existing web E2E mock fixtures is allowed because the real frontend renders it; no live provider keys, user data, or customer project files may appear.

Capture these named views:

1. `/` with a blank visible composer, a prepared run state, the workspace tabs, and the Pixel Office sidebar.
2. The same run with Agents, Plan, Changes, Audit, and Memory content visible in separate captures.
3. The approval modal over the real run context.
4. `/skills-graph`, `/memory`, and `/settings/models` for the final feature montage.

Use one stable, realistic task and fixture state across the run sequence. The first capture supplies the actual empty composer; HyperFrames overlays the task text inside that captured control with deterministic type-in timing. The Pixel Office capture must use the app's rendered active agent state from the same run fixture. Do not animate characters or create status claims that the UI does not render. Do not show a fabricated clean audit or successful test count. If the available fixture reports a finding or pending review, keep that state and word the overlay neutrally.

## HyperFrames implementation

The source composition lives under `docs/media/bosskuai-product-tour/` with a project-local `DESIGN.md`, `index.html`, screen-capture assets, and any needed sub-compositions. HTML remains the source of truth.

- Create each scene as a timed HyperFrames composition with a registered paused GSAP timeline.
- Build each scene at its visible end-state, then use `gsap.from()` for the screen, focus ring, title, and caption entrances.
- Use soft cross-dissolves or wipe-style transitions between scenes. Do not animate an outgoing scene away before its transition begins.
- Use finite ambient motion only. No random behaviour, auto-play media, or infinite GSAP repeats.
- Simulate the zoom by animating a screen wrapper, never by changing a source capture's dimensions.
- Include a reduced-information closing frame that holds for at least two seconds so README viewers can identify the product.

## README treatment and deliverables

Create these tracked deliverables:

| File | Purpose |
| --- | --- |
| `docs/media/bosskuai-product-tour/` | Editable HyperFrames source and design identity |
| `docs/assets/bosskuai-product-tour.mp4` | 1280 by 720 H.264 final product tour, target under 25 MB so interface text remains legible |
| `docs/assets/bosskuai-product-tour-preview.gif` | Short, lightweight animated preview with a recognizable dashboard moment, target under 6 MB |
| `README.md` | New `See BosskuAI` section directly after the introduction |

The README uses a linked preview so GitHub users see motion without relying on unsupported inline video markup:

```markdown
[![Watch the BosskuAI product tour](docs/assets/bosskuai-product-tour-preview.gif)](docs/assets/bosskuai-product-tour.mp4)
```

The adjacent sentence must state that the link opens the full product tour and describe the focus: planning, approvals, audit, and memory.

## Quality gates

Before handoff:

1. Run `npx hyperframes lint`, `npx hyperframes validate`, and `npx hyperframes inspect` from the composition directory. Resolve errors and contrast warnings.
2. Generate and review the animation map for pacing, collisions, off-canvas movement, and dead zones.
3. Render a draft, review representative frames at 0:03, 0:10, 0:19, 0:27, 0:36, 0:44, 0:52, 1:00, and 1:08, then render the final MP4.
4. Use `ffprobe` to confirm H.264, 1280 by 720 dimensions, 24 fps, expected duration, and a reasonable repository size.
5. Verify the README image target and MP4 target resolve from the repository root.
6. Inspect the final diff. Do not commit the transient `.superpowers/` companion files.

## Out of scope

- Voiceover, music, captions, or localization.
- Live model-provider execution or any use of provider credentials.
- Changes to BosskuAI's product behaviour or visual system.
- A separate vertical social-media cut.
