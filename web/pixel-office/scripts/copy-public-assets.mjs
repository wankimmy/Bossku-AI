/**
 * After vite build, merge pixel-office/public/assets into web/public/pixel-office/assets
 * (vite bundles JS into assets/ and can omit binary files).
 */
import { cpSync, existsSync, mkdirSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const src = join(__dirname, '../public/assets')
const dest = join(__dirname, '../../public/pixel-office/assets')

if (!existsSync(src)) {
  console.warn('[copy-public-assets] No source assets dir:', src)
  process.exit(0)
}

mkdirSync(dest, { recursive: true })

function copyRecursive(from, to) {
  if (!existsSync(from)) return
  cpSync(from, to, { recursive: true, force: true })
}

for (const name of readdirSync(src)) {
  if (name.endsWith('.js') || name.endsWith('.css')) continue
  copyRecursive(join(src, name), join(dest, name))
}

console.log(`[copy-public-assets] Merged ${src} -> ${dest}`)
