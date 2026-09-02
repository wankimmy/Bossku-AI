# Web Performance Checklist

> If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.

- Do you have field p75 numbers (CrUX or RUM) per template, not only a lab score?
- Is the LCP element known, server-rendered, and (if an image) preloaded with `fetchpriority="high"`, correct `srcset`/`sizes`, modern format, and no lazy loading?
- Is TTFB under 800 ms with HTML cached at the edge for public pages?
- Is critical CSS inlined and the rest deferred; are fonts preloaded with `font-display` set and fallback metrics adjusted?
- Are long tasks over 50 ms broken up, and is hydration limited to interactive components?
- Do all images, videos, iframes, and ad slots have reserved dimensions?
- Is the initial route JavaScript within the project budget, with heavy features dynamically imported?
- Are duplicate dependencies, legacy polyfills, and whole-library imports removed?
- Does every third-party script have an owner, a measured cost, and deferred or consent-gated loading?
- Are hashed assets served `immutable` for a year and HTML with `no-cache` or CDN `stale-while-revalidate`?
- Is compression (brotli/gzip), HTTP/2 or HTTP/3, and `preconnect` to critical origins in place, with no redirect chains?
- Is the rendering mode per route deliberate (SSG/ISR/SSR/SPA) and free of hydration mismatches?
- Are Lighthouse CI assertions and a bundle-size check failing the PR on regression?
- Is there a RUM alert on p75 LCP or INP regression after deploy?
- Are before/after p75 numbers recorded for the change?
