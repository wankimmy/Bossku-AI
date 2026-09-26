---
name: bosskuai-gsap-animation
description: "Use for GSAP work: timelines, ScrollTrigger pins/scrubs, SplitText, Flip, MorphSVG, matchMedia, useGSAP cleanup, Lenis/ScrollSmoother sync, and deciding when native CSS scroll-driven animation suffices. UI micro-motion: animate."
---

# BosskuAI GSAP Animation

Use this skill when the task is to create, audit, or refactor GSAP-powered motion for web interfaces: timelines, entrance sequences, scroll-triggered sections, text reveals, pinned panels, responsive motion, route transitions, or component-level animation cleanup.

## How this differs from nearby skills

- **`bosskuai-lenis-smooth-scroll`**: owns smooth scrolling and scroll plumbing. Load both when GSAP ScrollTrigger must sync with Lenis.
- **`bosskuai-3d-web-development`**: owns WebGL, Three.js, R3F, and 3D scene choreography. Use this skill for 2D DOM/SVG motion, or alongside 3D only for the GSAP timeline layer.
- **`bosskuai-ui-ux-design-to-code`**: owns UX structure and accessibility. Use both when motion choices affect comprehension, focus, or reduced-motion behavior.
- **`bosskuai-performance-profiling`**: load when animation causes jank, layout thrash, memory leaks, or low FPS.

## Mindset

- Motion should clarify state, sequence, and spatial relationships; decorative motion must earn its frame budget.
- Timelines are product logic. Name the sequence, define triggers, and clean it up.
- Prefer transform and opacity animations. Treat layout-affecting properties as suspicious on hot paths.
- ScrollTrigger needs deterministic refresh behavior, especially with images, fonts, route transitions, and smooth-scroll libraries.
- React/Vue/Nuxt integrations need cleanup on unmount, route changes, breakpoint changes, and reduced-motion changes.

## Workflow

### Phase 1 - Classify the motion

1. Identify the motion job: entrance, hover, scroll-linked, scroll-triggered, pinned story, text reveal, route transition, gesture, or 3D sync.
2. Identify the target environment: vanilla JS, React, Next/Nuxt, Vue, Webflow, Astro, or a canvas/WebGL hybrid.
3. Check whether the project already uses GSAP, `@gsap/react`, ScrollTrigger, SplitText, Flip, or Lenis. If it does not and the job is scroll-linked progress with no pinning or cross-element sequencing, use CSS scroll-driven animations (`animation-timeline: scroll()` / `view()`) inside `@supports (animation-timeline: scroll())` with a static fallback (Chrome/Edge 115+, Safari 26; not in Firefox stable, so not Baseline), and the View Transitions API for route crossfades; stop there.
4. Define the fallback: no-JS, reduced motion, mobile simplification, or static end state.

### Phase 2 - Build the sequence

5. Register only the plugins that are used.
6. Use `gsap.timeline()` for multi-step sequences; avoid scattered tweens when timing relationships matter.
7. In component frameworks, scope selectors and cleanup with `useGSAP()` or `gsap.context()` equivalents.
8. Use `gsap.matchMedia()` for breakpoint-specific timelines and automatic cleanup.
9. For ScrollTrigger, define trigger, start/end, scrub, pin, markers during development, and refresh timing.
10. If Lenis is present, sync ScrollTrigger through Lenis scroll events and drive Lenis from GSAP ticker; do not run two independent RAF loops.

### Phase 3 - Harden behavior

11. Add `prefers-reduced-motion` handling: disable scrubbed storytelling or replace with short fades/static states.
12. Avoid animating `top`, `left`, `width`, `height`, or expensive filters unless there is evidence the path is safe.
13. Use `will-change` sparingly and remove long-lived layer hints if they bloat memory.
14. Clean up timelines, ScrollTriggers, ticker callbacks, resize listeners, and custom observers.
15. Verify after fonts/images load and after route transitions if the layout changes.

## Common patterns

```js
import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

gsap.registerPlugin(ScrollTrigger)

const tl = gsap.timeline({
  scrollTrigger: {
    trigger: '.section',
    start: 'top 70%',
    end: 'bottom top',
    scrub: true,
  },
})

tl.from('.headline', { y: 32, opacity: 0 })
```

```js
const ctx = gsap.context(() => {
  gsap.from('.card', { y: 24, opacity: 0, stagger: 0.08 })
}, rootElement)

ctx.revert()
```

## Guardrails

- Do not add scroll hijacking or cinematic sequences where native scrolling, focus order, or page comprehension suffers.
- Do not ignore `prefers-reduced-motion`.
- Do not create animations that depend on unstable selectors if component refs are available.
- Do not leave ScrollTriggers or ticker callbacks alive after a route/component unmount.
- Do not assume GSAP docs from memory for version-sensitive plugin behavior; check official docs when exact API details matter.
- Licensing (checked 2026-09-26, gsap.com/licensing and gsap.com/docs/v3/Installation): GSAP and all plugins, including the formerly members-only SplitText and MorphSVG, are free for commercial use and ship in the public `gsap` npm package; replace any `npm.greensock.com` `.npmrc` setup with `npm install gsap`. The Standard 'No Charge' License bars use in no-code visual animation builders that compete with Webflow; raise that with the user before building one.

## Output format

```text
GSAP motion summary:
  Motion job: [entrance / scroll / pin / text / route / interaction]
  Runtime: [vanilla / React / Vue / Nuxt / etc.]
  Plugins: [ScrollTrigger / Flip / SplitText / none]
  Reduced motion: [fallback]

Implementation plan:
  Timeline: [sequence]
  Triggers: [start/end/scrub/pin]
  Cleanup: [context/useGSAP/revert/ticker removal]
  Performance: [transform/opacity/layer strategy]

Verification:
  [breakpoints, route changes, reduced motion, FPS/jank checks]
```

## References

- `../../references/checklists/gsap-animation-checklist.md`
- `../../references/checklists/ui-fidelity-checklist.md`
