# BosskuAI Public Product Tour Design

## Goal

Create a silent, 16:9, approximately 60-second BosskuAI product tour that makes the real application easy to understand from the README. It must show concrete screens from the app, use simulated camera navigation with cinematic zooms, and explain the value of the plan, approval, audit, and memory workflow.

## Audience and success criteria

The audience is a software builder deciding whether to try BosskuAI. The first ten seconds must establish both the product interface and its purpose. The video succeeds when a viewer can answer these questions without reading any other documentation:

1. What does the BosskuAI app look like?
2. How does it make AI-assisted changes more visible and controllable?
3. Which additional capabilities are available after the core workflow?

The video is a public product overview, not a tutorial. Every shown feature must be represented by a real rendered BosskuAI screen or a factual text overlay attached to that screen.

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

Target duration is 58 to 62 seconds at 1280 by 720, 24 fps, silent. The full product image remains visible behind every title instead of cutting to abstract marketing cards.

| Time | Product view | On-screen copy | Proof shown |
| --- | --- | --- | --- |
| 0:00-0:06 | Home workspace, task composer, workspace tabs | `SEE THE WORK BEFORE IT SHIPS` | The real BosskuAI control surface |
| 0:06-0:14 | Prompt enters, Agents view and Plan tab | `PLAN BEFORE EDITING` | Planner, executor, auditor, and final reviewer workflow |
| 0:14-0:22 | Plan overview and file-scoped checklist | `KEEP THE SCOPE VISIBLE` | Clear task plan and tracked checklist |
| 0:22-0:31 | Approval Required modal in the run context | `PAUSE FOR THE DECISION` | Human approval gate for risky work |
| 0:31-0:40 | Changes and Audit tabs | `INSPECT THE CHANGE. REVIEW THE AUDIT.` | Files, commands, checks, findings, and remaining risk state |
| 0:40-0:48 | Memory Inspector, knowledge, and Pixel Office context | `KEEP THE CONTEXT` | Persistent memory and visible agent activity |
| 0:48-0:55 | Slash skills, Skills Graph, and model settings montage | `ROUTE THE RIGHT EXPERTISE` | Skills, model roles, audit, plan mode, and optional local inference |
| 0:55-1:00 | Full dashboard, calm pull-back | `CURSOR. CLAUDE CODE. CODEX. OPENCODE.` | BosskuAI returns to the app while naming the supported tool surfaces |

The expanded features appear in the final montage because they support the core promise but should not displace it. Staff, project, run history, and other feature pages are not required unless they make the captured app sequence more coherent.

## Screen-capture rules

Capture the Nuxt app itself, not hand-built mock dashboard art. Deterministic local data from the existing web E2E mock fixtures is allowed because the real frontend renders it; no live provider keys, user data, or customer project files may appear.

Capture these named views:

1. `/` with a prepared run state and the workspace tabs visible.
2. The same run with Agents, Plan, Changes, Audit, and Memory content visible in separate captures.
3. The approval modal over the real run context.
4. `/skills-graph`, `/memory`, and `/settings/models` for the final feature montage.

Use one stable, realistic task and fixture state across the run sequence. Do not show a fabricated clean audit or successful test count. If the available fixture reports a finding or pending review, keep that state and word the overlay neutrally.

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
| `docs/assets/bosskuai-product-tour.mp4` | 1280 by 720 H.264 final product tour, target under 15 MB |
| `docs/assets/bosskuai-product-tour-preview.gif` | Short, lightweight animated preview with a recognizable dashboard moment |
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
3. Render a draft, review representative frames at 0:03, 0:10, 0:19, 0:27, 0:36, 0:44, 0:52, and 0:58, then render the final MP4.
4. Use `ffprobe` to confirm H.264, 1280 by 720 dimensions, 24 fps, expected duration, and a reasonable repository size.
5. Verify the README image target and MP4 target resolve from the repository root.
6. Inspect the final diff. Do not commit the transient `.superpowers/` companion files.

## Out of scope

- Voiceover, music, captions, or localization.
- Live model-provider execution or any use of provider credentials.
- Changes to BosskuAI's product behaviour or visual system.
- A separate vertical social-media cut.
