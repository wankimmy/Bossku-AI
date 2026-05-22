export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  // DevTools only when explicitly enabled (slow in Docker). Production uses npm run build.
  devtools: { enabled: process.env.NUXT_DEVTOOLS === 'true' },
  app: {
    head: {
      title: 'BosskuAI',
      htmlAttrs: { class: 'dark' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Skill-aware observable AI orchestrator' },
      ],
    },
  },
  modules: ['@nuxtjs/tailwindcss'],
  runtimeConfig: {
    public: {
      // Empty = browser calls same-origin /api (proxied to Laravel in Docker).
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? '',
      apiToken: process.env.NUXT_PUBLIC_API_TOKEN || '',
    },
  },
  tailwindcss: {
    cssPath: '~/assets/css/tailwind.css',
  },
  // Avoid stale HTML in the browser referencing deleted hashed chunks after rebuild.
  routeRules: {
    '/api/**': {
      proxy:
        process.env.NUXT_API_PROXY_TARGET || 'http://127.0.0.1:28480/api/**',
    },
    '/**': { headers: { 'cache-control': 'no-cache' } },
    '/_nuxt/**': {
      headers: { 'cache-control': 'public, max-age=31536000, immutable' },
    },
  },
})
