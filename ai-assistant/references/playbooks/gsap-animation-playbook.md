# GSAP Animation Playbook

Use this for GSAP-powered UI motion, ScrollTrigger storytelling, responsive timelines, framework cleanup, and GSAP plus Lenis integration.

## Workflow

1. Name the motion job and user-facing purpose.
2. Identify runtime: vanilla, React, Vue, Nuxt, Astro, Webflow, or a 3D/canvas hybrid.
3. Check existing animation libraries before adding GSAP.
4. Choose the minimum plugin set: ScrollTrigger, Flip, SplitText, Draggable, or none.
5. Build a timeline for sequences with dependent timing.
6. Scope selectors to a root element or component ref.
7. Add breakpoint-specific behavior with cleanup-safe media handling.
8. For ScrollTrigger, define trigger, start/end, scrub, pin, markers during development, refresh timing, and dynamic content behavior.
9. If Lenis is present, let GSAP ticker drive Lenis and let Lenis scroll update ScrollTrigger.
10. Add reduced-motion fallback and verify mobile simplification.
11. Review performance: transform/opacity first, minimal layout reads, no long-lived unnecessary `will-change`.
12. Clean up all timelines, contexts, ScrollTriggers, observers, and ticker callbacks.

## Output expectation

- motion map
- plugin choice
- timeline structure
- ScrollTrigger config
- cleanup plan
- reduced-motion and performance verification
