/**
 * After vite build, merge pixel-office/public/assets into web/public/pixel-office/assets
 * (vite bundles JS into assets/ and can omit binary files).
 */
import {
  existsSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const FILE_MODE = 0o644
const DIR_MODE = 0o755

const __dirname = dirname(fileURLToPath(import.meta.url))
const src = join(__dirname, '../public/assets')
const dest = join(__dirname, '../../public/pixel-office/assets')

if (!existsSync(src)) {
  console.warn('[copy-public-assets] No source assets dir:', src)
  process.exit(0)
}

mkdirSync(dest, { recursive: true, mode: DIR_MODE })

function copyAssetTree(from, to) {
  if (!existsSync(from)) return
  if (existsSync(to)) {
    rmSync(to, { recursive: true, force: true })
  }
  const st = statSync(from)
  if (st.isDirectory()) {
    mkdirSync(to, { recursive: true, mode: DIR_MODE })
    for (const name of readdirSync(from)) {
      copyAssetTree(join(from, name), join(to, name))
    }
    return
  }
  mkdirSync(dirname(to), { recursive: true, mode: DIR_MODE })
  writeFileSync(to, readFileSync(from), { mode: FILE_MODE })
}

for (const name of readdirSync(src)) {
  if (name.endsWith('.js') || name.endsWith('.css')) continue
  copyAssetTree(join(src, name), join(dest, name))
}

const nestedFurniture = join(dest, 'furniture', 'furniture')
if (existsSync(nestedFurniture)) {
  for (const name of readdirSync(nestedFurniture)) {
    copyAssetTree(join(nestedFurniture, name), join(dest, 'furniture', name))
  }
  rmSync(nestedFurniture, { recursive: true, force: true })
}

console.log(`[copy-public-assets] Merged ${src} -> ${dest}`)
