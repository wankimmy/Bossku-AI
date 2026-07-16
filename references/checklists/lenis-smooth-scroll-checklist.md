# Lenis Smooth Scroll Checklist

> If the request is general, ambiguous, or touches many files, ask clarifying yes/no questions before acting.

- Is Lenis needed for this experience, or would native scroll plus small transitions be enough?
- Is `lenis/dist/lenis.css` imported or otherwise represented by equivalent CSS?
- Is there only one RAF owner: `autoRaf`, custom `requestAnimationFrame`, GSAP ticker, or a framework frame loop?
- If GSAP ScrollTrigger is present, does Lenis update ScrollTrigger and run from the GSAP ticker?
- Are anchors, focus jumps, browser history, route changes, modals, and nested scroll areas still correct?
- Are `prevent`, `allowNestedScroll`, `anchors`, `orientation`, `infinite`, and `syncTouch` used only when needed?
- Are React/Vue/Nuxt providers mounted in the right layer and cleaned up on unmount?
- Is `lenis/snap` used only when the experience truly needs scroll snapping?
- Are touch devices, reduced motion, keyboard navigation, and low-power devices verified?
- Are custom RAF loops, ticker callbacks, and Lenis instances destroyed or removed on teardown?
