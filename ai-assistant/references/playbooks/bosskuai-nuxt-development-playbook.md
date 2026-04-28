# BosskuAI Nuxt Development Playbook

Senior-level audit and implementation reference for Nuxt 3/4 production apps. Each section pairs the wrong-way pattern with the right-way fix and the verification step that proves it.

## Audit flow

1. Read `nuxt.config.ts`, `app.vue`, `app/router.options.ts`, `pages/`, `layouts/`, `composables/`, `server/api/`, `server/middleware/`, `nitro.config.ts` (if present), and module imports.
2. Identify Nuxt version, Nitro preset, and rendering mode per route (SSR / SSG / hybrid / client-only).
3. Trace one critical page end-to-end: route → middleware → page setup → useFetch/useAsyncData calls → server route → Nitro handler → external service → response → hydration.
4. Check route rules, metadata, sitemap, robots, redirects, error pages, and SEO/structured data.
5. Verify with `nuxt build --analyze`, `nuxt typecheck`, Lighthouse / WebPageTest, and `curl -I` for headers.

## Nuxt 3/4 best-practice checks (one-liner version)

- `useFetch` for component-bound data; `useAsyncData` when you need full control over the key/handler.
- Same call site executes once on the server and reuses on hydration — never duplicate fetches.
- Server routes (`server/api/*`) own external API calls, not page components.
- Route rules (`routeRules` in `nuxt.config.ts`) declare SSR/SSG/ISR/CDN behavior per path.
- `useHead` / `useSeoMeta` is set in `setup()`, not inside `onMounted` (or it won't render server-side).
- Sitemap and robots come from real modules (`@nuxtjs/sitemap`, `@nuxtjs/robots`) wired to actual routes.
- Hydration payload audited — no full DB rows or secrets serialized into the page.
- Core Web Vitals measured on real device profiles, not just localhost.
- Nitro middleware terminates requests fast; long work goes to a background queue.

## Recommended commands

```bash
pnpm nuxt typecheck
pnpm nuxt build --analyze            # bundle visualizer
pnpm nuxt preview                    # production-like local run
npx unlighthouse                     # site-wide Lighthouse audit
curl -sI https://example.com/page    # check cache + render headers
```

---

## Worked anti-patterns and fixes

### 1. SSR fetch waterfall

**Wrong**

```vue
<script setup>
const { data: user } = await useFetch('/api/user/me')
const { data: orders } = await useFetch(`/api/orders?userId=${user.value.id}`)
const { data: stats } = await useFetch(`/api/orders/stats?userId=${user.value.id}`)
</script>
```

Each `await` blocks the next. SSR walltime = sum of three round trips.

**Right**

```vue
<script setup>
const { data: user } = await useFetch('/api/user/me')

const [orders, stats] = await Promise.all([
  useFetch('/api/orders', { query: { userId: user.value.id } }),
  useFetch('/api/orders/stats', { query: { userId: user.value.id } }),
])
</script>
```

Or move the dependency into a single server route that fetches in parallel server-side, returning one payload.

**Verify** — measure server-side render time before/after with `console.time` in the server middleware, or look at the `x-response-time` header. The composite endpoint should match the slowest single dependency, not the sum.

### 2. Duplicate fetch on hydration

**Wrong**

```vue
<script setup>
const route = useRoute()
const { data } = await useFetch('/api/post/' + route.params.slug)
</script>
```

`useFetch` keys on the URL. If the URL is built dynamically without a stable key and the params change between SSR and client, the client refetches.

**Right**

```vue
<script setup>
const route = useRoute()
const { data } = await useFetch('/api/post', {
  query: { slug: route.params.slug },
  key: `post-${route.params.slug}`,
})
</script>
```

**Verify** — open Network in DevTools, hard-reload the page. Inspect: SSR-fetched calls should appear in the HTML payload (`<script id="__NUXT_DATA__">`) and **not** as a duplicate XHR after hydration.

### 3. Hydration mismatch from non-deterministic render

**Wrong**

```vue
<template>
  <p>Item ID: {{ Math.random() }}</p>
  <p>Now: {{ new Date().toLocaleString() }}</p>
</template>
```

Server rendered with one value; client rerendered with a different value → console warning, sometimes broken interactivity.

**Right**

```vue
<script setup>
const id = useState('item-id', () => crypto.randomUUID())
const now = ref(new Date())
onMounted(() => { now.value = new Date() })
</script>

<template>
  <p>Item ID: {{ id }}</p>
  <ClientOnly><p>Now: {{ now.toLocaleString() }}</p></ClientOnly>
</template>
```

**Verify** — Vue Devtools → Components panel should not show hydration warnings. Run `pnpm nuxt build && pnpm nuxt preview` and check the dev console on first load.

### 4. `useFetch` vs `useAsyncData` confusion

**Wrong** — calling `$fetch` directly inside `setup()`:

```vue
<script setup>
const data = await $fetch('/api/users')
</script>
```

This re-runs on the client and fails to leverage SSR caching.

**Right rules:**

- **`useFetch`**: for `/api/...` calls bound to a component, default reactivity, automatic key from URL.
- **`useAsyncData('key', () => $fetch(...))`**: when you need a custom key, transform, or call something that isn't a URL (e.g. multiple fetches combined).
- **`$fetch`**: only inside server routes, event handlers, or `onMounted`/`onClick` (client-only).

```vue
<script setup>
const { data } = await useAsyncData('top-products', () =>
  Promise.all([
    $fetch('/api/products/featured'),
    $fetch('/api/products/popular'),
  ]).then(([featured, popular]) => ({ featured, popular }))
)
</script>
```

### 5. Route rules and rendering mode

**Wrong** — every page rendered fresh per request:

```ts
// nuxt.config.ts
export default defineNuxtConfig({ ssr: true })
```

Marketing pages re-render under traffic; static content pays request-time cost.

**Right** — declarative route rules per path:

```ts
export default defineNuxtConfig({
  routeRules: {
    '/':                  { prerender: true },
    '/blog/**':           { isr: 3600 },
    '/pricing':           { swr: 600 },
    '/dashboard/**':      { ssr: true, headers: { 'cache-control': 'no-store' } },
    '/admin/**':          { ssr: false },
    '/api/**':            { cors: true, headers: { 'cache-control': 'no-store' } },
    '/old-path':          { redirect: '/new-path' },
  },
})
```

**Verify** — `curl -I` each path and inspect the `cache-control`, `x-nitro-prerendered`, and CDN headers. Run a load test and confirm prerendered pages don't hit the origin.

### 6. SEO metadata set after hydration

**Wrong**

```vue
<script setup>
onMounted(() => {
  useHead({ title: post.value.title })
})
</script>
```

Crawlers see the empty SSR title.

**Right**

```vue
<script setup>
const { data: post } = await useFetch(`/api/post/${slug}`)

useSeoMeta({
  title:       () => post.value.title,
  description: () => post.value.excerpt,
  ogTitle:     () => post.value.title,
  ogImage:     () => post.value.heroImage,
  twitterCard: 'summary_large_image',
})
</script>
```

**Verify** — `curl https://example.com/post/x | grep -i 'og:title\|<title>'` — values must appear in the raw HTML, not just after JS executes.

### 7. Sitemap and structured data

**Wrong** — handwritten static `sitemap.xml` in `public/`. Goes stale.

**Right** — generate from real source via `@nuxtjs/sitemap`:

```ts
// nuxt.config.ts
modules: ['@nuxtjs/sitemap', '@nuxtjs/robots'],
sitemap: {
  sources: ['/api/__sitemap'],
},

// server/api/__sitemap.ts
export default defineSitemapEventHandler(async () => {
  const posts = await db.post.findMany({ select: { slug: true, updatedAt: true } })
  return posts.map(p => ({
    loc: `/blog/${p.slug}`,
    lastmod: p.updatedAt,
    changefreq: 'weekly',
  }))
})
```

For structured data, emit JSON-LD in `useHead`:

```vue
<script setup>
useHead({
  script: [{
    type: 'application/ld+json',
    children: JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Article',
      headline: post.value.title,
      datePublished: post.value.publishedAt,
      author: { '@type': 'Person', name: post.value.author.name },
    }),
  }],
})
</script>
```

**Verify** — paste rendered HTML into Google's Rich Results Test and Schema.org validator. Submit `sitemap.xml` to Search Console and watch coverage.

### 8. Hydration payload bloat

**Wrong**

```vue
<script setup>
const { data: user } = await useFetch('/api/user/me')
</script>
```

Server returns the entire user row, including internal flags, password hash, etc. The whole thing serializes into the client payload.

**Right** — server route returns only what the page needs:

```ts
// server/api/user/me.get.ts
export default defineEventHandler(async (event) => {
  const session = await requireSession(event)
  return {
    id:          session.user.id,
    displayName: session.user.displayName,
    avatarUrl:   session.user.avatarUrl,
  }
})
```

**Verify** — view source on the rendered page, find `__NUXT_DATA__`, paste into a JSON formatter, and grep for any field that shouldn't be there. Set a budget: payload < 100KB gzipped on landing pages.

### 9. Core Web Vitals — LCP, CLS, INP

**Common LCP regressions:**

- Hero image not preloaded → `<link rel="preload" as="image" imagesrcset="...">` via `useHead`.
- Wrong `<NuxtImg>` `sizes` attribute → browser fetches the largest variant unnecessarily.
- Webfont swap blocking text render → use `font-display: swap` and self-host primary font.

**Common CLS regressions:**

- Images without explicit `width`/`height` → reserve space.
- Ads / embeds inserting after layout → reserve a fixed slot.
- Late-arriving banners (cookie consent, promo) → render server-side or in a slot that doesn't push content.

**Common INP regressions:**

- Heavy hydration on first interaction → break the page into islands, lazy-hydrate non-critical components.
- Synchronous third-party scripts (analytics, chat widgets) → `defineNuxtPlugin` with `parallel: true`, or use a `nuxt-third-parties` integration.

**Verify** — run `npx unlighthouse` against the production build, not dev mode. Open the WebPageTest filmstrip. Use the Web Vitals overlay on a real mid-tier Android device for INP.

### 10. Nitro server route doing slow work synchronously

**Wrong**

```ts
// server/api/checkout.post.ts
export default defineEventHandler(async (event) => {
  const body = await readBody(event)
  const order = await createOrder(body)
  await sendConfirmationEmail(order)
  await chargeCard(order)
  return { id: order.id }
})
```

The browser waits for email + Stripe.

**Right** — return fast, queue the side effects:

```ts
export default defineEventHandler(async (event) => {
  const body = await readBody(event)
  const order = await createOrder(body)
  await queue.publish('order.created', { orderId: order.id })
  return { id: order.id, status: 'received' }
})
```

**Verify** — `time curl -X POST https://example.com/api/checkout -d '...'` should return in well under 500ms. Email and payment confirmations land in the worker logs.

### 11. Nuxt 4 migration gotchas

Nuxt 4 moved app code under `app/`. Common migration mistakes:

- Aliases (`~`, `@`) referring to `pages/` instead of `app/pages/`.
- Test setup files importing from old paths.
- Custom modules that hard-code `srcDir`.

Fix: set `future.compatibilityVersion: 4` in `nuxt.config.ts` first, then move files in one PR. Run `pnpm nuxt typecheck` and the test suite at each step.

---

## Performance and SEO audit matrix

| Layer       | Check                                                | Tool / command                              |
|-------------|------------------------------------------------------|---------------------------------------------|
| Routing     | Each path has a route rule (or default is correct)   | review `routeRules` against sitemap         |
| SSR/Nitro   | No fetch waterfalls; payload < 100KB gzipped         | server-side timing logs + view source       |
| Hydration   | No mismatch warnings in production build             | `pnpm nuxt build && pnpm nuxt preview`      |
| SEO         | `<title>`, OG, JSON-LD present in raw HTML           | `curl` + Rich Results Test                  |
| Sitemap     | All published URLs present, lastmod current          | open `/sitemap.xml`, diff vs DB             |
| Bundle      | No accidental client imports of server-only deps     | `nuxt build --analyze`                      |
| LCP         | < 2.5s on 4G mobile profile                          | unlighthouse / WebPageTest                  |
| CLS         | < 0.1                                                 | Lighthouse                                  |
| INP         | < 200ms on mid-tier Android                           | Web Vitals extension on real device         |
| Edge        | CDN caches prerendered/ISR pages, not personalized   | check `cache-control` and CDN hit headers   |
| Errors      | `error.vue` page renders correctly for 404/500       | `curl https://example.com/nope -I`          |

## Output expectation

When auditing, return:

1. **Findings table** — file:line, severity, evidence, fix.
2. **Smallest fix sequence** — minimum P0/P1 set to ship.
3. **Verification** — exact command, header, or metric that proves each fix.
4. **De-scope** — what is intentionally not touched yet, and why.

## Further reading

- `nuxt-development-detailed-playbook.md` — extended step-by-step workflow and template scaffolds.
- `bosskuai-ui-ux-design-to-code-playbook.md` — visual hierarchy, accessibility, anti-AI design checks.
- `bosskuai-seo-geo-playbook.md` — schema, internal linking, answer-engine optimization.
