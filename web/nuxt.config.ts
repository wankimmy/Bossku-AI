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
      apiBase:
        process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:28480'
    }
  },
  tailwindcss: {
    cssPath: '~/assets/css/tailwind.css'
  },
})

