# GSAP Animation Checklist

> If the request is general, ambiguous, or touches many files, ask clarifying yes/no questions before acting.

- Is the motion job clear: entrance, scroll, pin, text reveal, route transition, interaction, or 3D sync?
- Are only the needed GSAP plugins registered?
- Are multi-step sequences represented as timelines instead of scattered unrelated tweens?
- In component frameworks, are selectors scoped and animations cleaned up with `useGSAP()`, `gsap.context()`, or equivalent teardown?
- Does ScrollTrigger define trigger, start, end, scrub, pin, and refresh behavior intentionally?
- Are breakpoint-specific timelines handled with `gsap.matchMedia()` or an equivalent cleanup-safe approach?
- Is `prefers-reduced-motion` honored with a static or simplified fallback?
- Are hot-path animations limited mostly to transforms and opacity?
- Are ScrollTriggers, ticker callbacks, resize listeners, observers, and timelines removed on unmount or route change?
- Was the motion checked across mobile, desktop, dynamic content, and font/image load states?
