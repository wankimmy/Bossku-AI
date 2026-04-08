# Lenis Smooth Scroll Playbook

Use this for Lenis setup, framework integration, GSAP ScrollTrigger sync, scroll snap, horizontal/infinite scroll, and smooth-scroll debugging.

## Workflow

1. Decide if smooth scroll is required for the experience.
2. Confirm no competing smooth-scroll library or custom scroll hijacker is already active.
3. Import `lenis` and the recommended CSS, or confirm equivalent CSS exists.
4. Pick the RAF owner:
   - `autoRaf: true` for simple pages.
   - custom RAF for bespoke animation loops.
   - GSAP ticker when integrating with ScrollTrigger.
5. Choose framework wrapper if applicable: `lenis/react`, `lenis/vue`, or `lenis/nuxt`.
6. Configure only the options required by the behavior: anchors, prevent, nested scroll, orientation, infinite scroll, or snap.
7. For GSAP, connect Lenis scroll to `ScrollTrigger.update`, call `lenis.raf(time * 1000)` from GSAP ticker, and disable lag smoothing if needed.
8. For `lenis/snap`, add snap points or elements intentionally and verify keyboard/touch behavior.
9. Test anchors, focus, modals, nested scroll regions, route transitions, dynamic content, touch, and reduced motion.
10. Clean up Lenis instances, providers, ticker callbacks, and custom RAF loops.

## Output expectation

- scroll job and runtime
- chosen RAF owner
- options rationale
- GSAP/framework integration notes
- accessibility and teardown verification
