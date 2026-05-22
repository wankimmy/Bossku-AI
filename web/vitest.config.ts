import vue from '@vitejs/plugin-vue'
import path from 'node:path'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '~': path.resolve(__dirname, '.'),
    },
  },
  test: {
    include: ['tests/**/*.spec.ts', 'utils/**/*.spec.ts'],
    environment: 'happy-dom',
    passWithNoTests: true,
  },
})
