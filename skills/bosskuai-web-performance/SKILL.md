---
name: bosskuai-web-performance
description: "Use this for frontend and web performance — Core Web Vitals (LCP, INP, CLS), Lighthouse and field data (CrUX, RUM), bundle size and code splitting, images and fonts, caching headers and CDN, SSR/SSG/ISR and hydration cost, third-party scripts, and performance budgets in CI for Nuxt, Next.js, React, and server-rendered pages. Backend CPU, memory, and query profiling belong to bosskuai-performance-profiling."
---

# BosskuAI Web Performance

Use this skill when a page or app feels slow in the browser, when Search Console or Lighthouse flags Core Web Vitals, or when a frontend needs a performance budget before it grows.

## How this differs from nearby skills

- **`bosskuai-performance-profiling`**: server CPU, memory, queries, flame graphs; this skill starts where the response leaves the server.
- **`bosskuai-react-development`** / **`bosskuai-nuxt-development`**: framework correctness; this skill decides what to ship, when, and how much.
- **`bosskuai-seo-geo`**: ranking and discoverability; Core Web Vitals is the overlap, owned here.
- **`bosskuai-aws-deployment`** / **`bosskuai-vps-docker-deployment`**: CDN and caching infrastructure; this skill specifies the headers and edge rules.

## Mindset

- Field data at p75 on real users beats a lab score on a MacBook. Measure on a mid-range Android over 4G.
- The fastest byte is the one not sent; the fastest script is the one not run.
- Budgets, not heroics: a size or vitals budget in CI prevents the regression instead of chasing it.
- Every third-party tag is a performance and privacy decision with an owner.

## Targets (p75, field)

- LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1; TTFB ≤ 800 ms.
- Initial route JavaScript ≤ ~200 KB compressed for content sites, ≤ ~350 KB for apps; set the number per project and enforce it.

## Diagnose

1. Field: CrUX (Search Console, PageSpeed Insights "Discover what your real users are experiencing"), or your own RUM via the `web-vitals` library sent to analytics with route and device.
2. Lab: Lighthouse CI on a throttled profile, WebPageTest filmstrip, Chrome DevTools Performance panel (long tasks, LCP element, layout shift regions), Coverage tab for unused JS/CSS.
3. Bundle: `vite-bundle-visualizer`, `next build` analyzer, `nuxi analyze`; look for duplicate dependencies, whole-library imports, and polyfills for supported browsers.
4. Network: waterfall for render-blocking CSS/JS, late-discovered LCP image, font chains, and third-party tags.

## LCP

- Cut TTFB first: cache the HTML (ISR/SWR at the edge), fix slow queries, put the origin behind a CDN.
- SSR or prerender the LCP element; never client-fetch hero content.
- Hero image: correct `srcset`/`sizes`, AVIF/WebP, `fetchpriority="high"`, `<link rel=preload>`, no `loading="lazy"` above the fold, explicit dimensions.
- Inline critical CSS, defer the rest; `font-display: swap` (or `optional` for non-brand text) and preload the one font file the LCP text uses.

## INP

- Break long tasks (> 50 ms): chunk work, yield with `scheduler.yield()` or `setTimeout`, move heavy computation to a Web Worker.
- Trim hydration: fewer client components (RSC, Nuxt islands/lazy hydration), smaller initial state, defer non-critical widgets until idle or interaction.
- Keep handlers cheap: debounce input, avoid synchronous layout reads in handlers, update state in one batch.
- Third-party scripts: load after interaction or via Partytown, consent-gate, remove what nobody reads.

## CLS

- Width/height or `aspect-ratio` on every image, video, iframe, and ad slot; reserve space for late content.
- Font fallback metrics (`size-adjust`, `ascent-override`) so swaps do not shift text.
- Never insert content above existing content without user action; animate with `transform`/`opacity` only.

## JavaScript budget

- Route-level code splitting; dynamic import for editors, charts, maps, and anything below the fold.
- Modern build targets; drop legacy polyfills; check `sideEffects` for tree shaking; replace heavy utilities (moment → date-fns/Temporal, lodash → per-function imports).
- Deduplicate dependencies (`pnpm why`, `npm ls`); one icon strategy; one date library.

## Caching and delivery

- Hashed static assets: `Cache-Control: public, max-age=31536000, immutable`.
- HTML: `no-cache` with ETag, or `s-maxage` + `stale-while-revalidate` at the CDN for public pages.
- Brotli or gzip at the edge; HTTP/2 or HTTP/3; `preconnect` to the API and font origins; avoid redirect chains.
- ISR/SSG for public pages, SSR for personalized, SPA only behind login.
- Service worker only with an invalidation plan; otherwise it becomes the cache you cannot bust.

## Framework notes

- **Nuxt**: watch the payload size, use `useLazyAsyncData` for non-critical data, `nuxt/image`, `routeRules` with `swr`/`isr`/`prerender`, fix hydration mismatches (time, randomness, browser-only state).
- **Next.js**: keep the `"use client"` boundary low, `next/image`, `next/font`, `dynamic(..., { ssr: false })` for browser-only widgets, streaming with Suspense.
- **React SPA**: prerender or SSR the shell; lazy routes; virtualize lists.
- **Server-rendered (Laravel Blade, Django)**: full-page cache where possible, defer scripts, inline critical CSS, image pipeline.

## Budgets in CI

- Lighthouse CI assertions on key URLs (mobile profile) and `size-limit` or `bundlesize` on the initial chunk, failing the PR on regression.
- RUM dashboard with an alert when p75 LCP or INP regresses after a deploy.

## Workflow

1. Get field data per template (home, listing, detail, app shell) and pick the worst metric.
2. Reproduce in lab with throttling; identify the single biggest contributor.
3. Fix that one thing; measure again; keep or revert (`bosskuai-ratchet-loop`).
4. Set the budget that would have caught it; add it to CI.

## Guardrails

- Do not lazy-load the LCP image or hero content.
- Do not optimize lab scores that field data says are already fine.
- Do not claim "faster" without before/after p75 numbers or a filmstrip.
- Do not add a service worker for caching without an invalidation plan.
- Do not ship a third-party tag without an owner and a measured cost.

## Output format

```text
Page/template: [...] - Field p75: LCP [..] INP [..] CLS [..] TTFB [..]
Biggest contributor: [what, evidence]
Fixes (ordered by impact/effort):
  [change] — [expected metric effect] — [where]
Budget to add: [metric/size, threshold, CI check]
Before/after: [numbers, method]
```

## References

- `../../references/checklists/web-performance-checklist.md`
- `../../references/pitfalls/performance-pitfalls.md`
- `../../references/checklists/seo-geo-checklist.md`
