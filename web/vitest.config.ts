import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  test: {
    include: ['tests/**/*.spec.ts'],
    environment: 'happy-dom',
    passWithNoTests: true,
  },
})
