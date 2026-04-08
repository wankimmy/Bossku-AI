---
name: bosskuai-lenis-smooth-scroll
description: Use this for Lenis smooth-scroll work, including setup, React/Vue/Nuxt integration, custom RAF loops, GSAP ScrollTrigger synchronization, anchors, nested scroll containers, scroll snap, horizontal/infinite scroll, cleanup, and scroll performance/accessibility review.
---

# BosskuAI Lenis Smooth Scroll

Use this skill when the task involves Lenis smooth scrolling: installing it, wiring it into a framework, syncing it with GSAP, debugging scroll jank, supporting anchors, handling nested scroll areas, using `lenis/snap`, or reviewing whether smooth scrolling is appropriate.

## How this differs from nearby skills

- **`bosskuai-gsap-animation`**: owns GSAP timelines and ScrollTrigger behavior. Load both when Lenis drives scroll and GSAP reacts to it.
- **`bosskuai-ui-ux-design-to-code`**: owns UX and accessibility decisions. Use both when smooth scroll changes navigation, focus, or reduced-motion behavior.
- **`bosskuai-3d-web-development`**: owns WebGL and 3D scroll choreography. Use Lenis here only for the scroll layer.
- **`bosskuai-performance-profiling`**: load when scroll performance is the main defect.

## Source posture

This skill was shaped from the local Lenis repo at `/Users/safwanyacob/Documents/Safwan/lenis`, currently package version `1.3.21` in its `package.json`. Check the local repo or official package docs again when exact options or exports matter.

## Mindset

- Smooth scrolling is infrastructure for an experience, not a default upgrade.
- Native scroll semantics, focus behavior, anchors, modals, and nested scroll areas must keep working.
- Use one RAF owner. When GSAP is present, wire Lenis into GSAP ticker instead of running competing loops.
- CSS setup matters. Missing Lenis CSS often creates subtle behavior bugs.
- Touch behavior, iframes, sticky elements, and route transitions deserve extra scrutiny.

## Workflow

### Phase 1 - Decide whether Lenis is appropriate

1. Identify the scroll job: general smoothing, scroll-linked animation, WebGL sync, parallax, horizontal, infinite, anchors, snap, or modal/nested scroll support.
2. Check accessibility and UX risks: reduced motion, keyboard navigation, focus jumps, anchor links, browser history, and native mobile feel.
3. Read existing scroll code before adding Lenis. Do not stack it on top of another smooth-scroll library.

### Phase 2 - Integrate

4. Install/import from `lenis`; import `lenis/dist/lenis.css` or confirm equivalent CSS is present.
5. For simple vanilla use, prefer `autoRaf: true`.
6. For GSAP ScrollTrigger or custom animation clocks, set `autoRaf: false`, call `lenis.raf(time)`, and clean up ticker callbacks.
7. For React, use `ReactLenis` and `useLenis` from `lenis/react`.
8. For Vue, use `VueLenis` and `useLenis` from `lenis/vue`; for Nuxt, configure `modules: ['lenis/nuxt']`.
9. For anchors, nested scroll, horizontal, snap, or infinite scroll, configure those intentionally rather than copying a catch-all setup.

### Phase 3 - Debug and harden

10. Confirm scroll events, progress, direction, velocity, and target scroll behave as expected.
11. Recalculate dimensions when content changes if auto resize is disabled or dynamic layout confuses measurements.
12. Use `prevent` for targeted native scroll exceptions; use `allowNestedScroll` only when the performance tradeoff is acceptable.
13. Sync ScrollTrigger with Lenis by updating ScrollTrigger on Lenis scroll and driving Lenis from GSAP ticker.
14. On teardown, call `destroy()` or unmount the provider cleanly, remove ticker callbacks, and stop custom RAF loops.
15. Verify reduced motion, mobile touch, keyboard focus, anchor links, modals, and route changes.

## Common patterns

```js
import Lenis from 'lenis'
import 'lenis/dist/lenis.css'

const lenis = new Lenis({ autoRaf: true })

lenis.on('scroll', ({ scroll, progress, velocity }) => {
  // wire only the state you need
})
```

```js
import Lenis from 'lenis'
import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

gsap.registerPlugin(ScrollTrigger)

const lenis = new Lenis({ autoRaf: false })

lenis.on('scroll', ScrollTrigger.update)
gsap.ticker.add((time) => lenis.raf(time * 1000))
gsap.ticker.lagSmoothing(0)
```

## Guardrails

- Do not add Lenis when the page only needs native scroll and a tiny transition.
- Do not break anchors, focus, modals, or nested scroll areas for visual smoothness.
- Do not use multiple independent RAF loops for Lenis plus GSAP.
- Do not enable heavy options like broad nested-scroll detection without checking performance.
- Do not forget cleanup in SPAs, route transitions, and component unmounts.

## Output format

```text
Lenis scroll summary:
  Scroll job: [smooth / GSAP sync / horizontal / snap / infinite / nested]
  Runtime: [vanilla / React / Vue / Nuxt]
  RAF owner: [autoRaf / custom RAF / GSAP ticker]
  Accessibility fallback: [reduced motion / native scroll / static]

Implementation plan:
  Setup: [imports, CSS, provider or instance]
  Options: [only options that matter]
  Integration: [GSAP, anchors, snap, nested containers]
  Cleanup: [destroy/ticker/RAF]

Verification:
  [mobile, keyboard, anchors, route changes, modals, FPS]
```

## References

- `../../references/checklists/lenis-smooth-scroll-checklist.md`
- `../../references/playbooks/lenis-smooth-scroll-playbook.md`
- `../../references/checklists/ui-fidelity-checklist.md`
