export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },
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
        process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000'
    }
  },
  tailwindcss: {
    cssPath: '~/assets/css/tailwind.css'
  },
  vite: {
    server: {
      // Hot reload is expensive on Docker Desktop / Windows bind mounts.
      hmr: false,
      watch: {
        ignored: ['**/*'],
      },
    },
  },
})
