import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: '../public/pixel-office',
    emptyOutDir: true,
  },
  base: '/pixel-office/',
})
