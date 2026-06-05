import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: '../public/pixel-office',
    emptyOutDir: true,
    // Binary assets merged in postbuild (copy-public-assets.mjs); avoids bind-mount EACCES on cpSync.
    copyPublicDir: false,
  },
  base: '/pixel-office/',
})
