/**
 * Copy zep-pixel-agents binary assets into pixel-office/public/assets.
 * Run export-zep-furniture first (via npm run sync:zep-assets).
 *
 * Usage: ZEP_PIXEL_AGENTS_ROOT=../zep-pixel-agents node scripts/sync-zep-assets.mjs
 */
import { copyFileSync, cpSync, existsSync, mkdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { findExistingFurnitureDir, findZepRoot, getZepRootCandidates } from './zep-root.mjs'
import { hasValidFurnitureBundle, installFurnitureBundle } from './zep-furniture-bundle.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const destRoot = join(__dirname, '../public/assets')
const vendorFurniture = join(__dirname, '../vendor/zep-furniture')
const catalogDest = join(destRoot, 'furniture', 'furniture-catalog.json')

const copied = { floors: false, walls: false, characters: false, furniture: false }

function safeCopyFile(src, dest) {
  if (!existsSync(src)) return false
  mkdirSync(dirname(dest), { recursive: true })
  copyFileSync(src, dest)
  return true
}

function safeCopyDir(src, dest) {
  if (!existsSync(src)) return false
  cpSync(src, dest, { recursive: true, force: true })
  return true
}

function isStrict() {
  return process.env.BOSSKU_PIXEL_OFFICE_STRICT === '1'
}

function skipAllowed() {
  return process.env.BOSSKU_PIXEL_OFFICE_SKIP_ASSETS === '1'
}

function gracefulAllowed() {
  if (isStrict()) return false
  if (skipAllowed()) return true
  if (process.env.BOSSKU_PIXEL_OFFICE_GRACEFUL === '1') return true
  return existsSync('/.dockerenv')
}

function hasPartialAssets() {
  return copied.floors || copied.walls || copied.characters
}

async function ensureFurnitureCatalog() {
  if (existsSync(catalogDest)) return
  if (existsSync(join(vendorFurniture, 'furniture-catalog.json'))) return
  if (process.env.BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE === '0') return
  try {
    const { fetchFurnitureBundle } = await import('./fetch-furniture-bundle.mjs')
    await fetchFurnitureBundle()
  } catch (err) {
    console.warn('[sync-zep-assets] Furniture bundle fetch failed:', err.message || err)
  }
}

const root = findZepRoot()

await ensureFurnitureCatalog()

if (root) {
  console.log(`[sync-zep-assets] Using zep root: ${root}`)
  mkdirSync(destRoot, { recursive: true })

  const filePairs = [
    ['floors', join(root, 'assets', 'floors.png'), join(destRoot, 'floors.png')],
    ['floors', join(root, 'webview-ui', 'public', 'assets', 'floors.png'), join(destRoot, 'floors.png')],
    ['walls', join(root, 'assets', 'walls.png'), join(destRoot, 'walls.png')],
    ['walls', join(root, 'webview-ui', 'public', 'assets', 'walls.png'), join(destRoot, 'walls.png')],
  ]

  for (const [kind, src, dest] of filePairs) {
    if (safeCopyFile(src, dest)) {
      copied[kind] = true
      console.log(`  copied ${dest}`)
    }
  }

  const charSources = [
    join(root, 'assets', 'characters'),
    join(root, 'webview-ui', 'public', 'assets', 'characters'),
  ]
  for (const src of charSources) {
    if (safeCopyDir(src, join(destRoot, 'characters'))) {
      copied.characters = true
      console.log('  copied characters/')
      break
    }
  }

  const furnDir = findExistingFurnitureDir(root)
  if (furnDir && hasValidFurnitureBundle(furnDir) && !existsSync(catalogDest)) {
    if (safeCopyDir(furnDir, join(destRoot, 'furniture'))) {
      copied.furniture = true
      console.log('  copied furniture/ from zep')
    }
  } else if (furnDir && !hasValidFurnitureBundle(furnDir)) {
    console.warn('[sync-zep-assets] zep furniture catalog has no PNG sprites; skipping furniture copy.')
  }
} else if (!existsSync(catalogDest) && hasValidFurnitureBundle(vendorFurniture)) {
  console.log(`[sync-zep-assets] Using vendor furniture: ${vendorFurniture}`)
  mkdirSync(destRoot, { recursive: true })
  try {
    installFurnitureBundle(vendorFurniture, join(destRoot, 'furniture'))
    copied.furniture = true
  } catch (err) {
    console.warn('[sync-zep-assets] Vendor furniture install failed:', err.message || err)
  }
} else if (!existsSync(catalogDest) && existsSync(join(vendorFurniture, 'furniture-catalog.json'))) {
  console.warn(
    '[sync-zep-assets] Vendor furniture-catalog.json exists but PNG sprites are missing; not copying incomplete bundle.',
  )
} else if (!root) {
  console.warn('[sync-zep-assets] zep-pixel-agents root not found.')
  console.warn(`  ZEP_PIXEL_AGENTS_ROOT=${process.env.ZEP_PIXEL_AGENTS_ROOT ?? '(unset)'}`)
  console.warn(`  Tried: ${getZepRootCandidates().join(', ')}`)
  console.warn('  Set ZEP_PIXEL_AGENTS_ROOT or clone zep next to Bossku-AI (Docker: /workspace/zep-pixel-agents).')
}

if (!existsSync(catalogDest)) {
  if (hasPartialAssets() && gracefulAllowed()) {
    console.warn('[sync-zep-assets] No furniture catalog; copied characters/walls/floors only (graceful mode).')
    process.exit(0)
  }
  if (skipAllowed()) {
    console.warn('[sync-zep-assets] No furniture catalog; BOSSKU_PIXEL_OFFICE_SKIP_ASSETS=1 set.')
    process.exit(0)
  }
  console.error('[sync-zep-assets] Missing furniture/furniture-catalog.json after sync.')
  console.error('  Run: npm run export:zep-furniture')
  process.exit(1)
}

console.log('[sync-zep-assets] Done.')
