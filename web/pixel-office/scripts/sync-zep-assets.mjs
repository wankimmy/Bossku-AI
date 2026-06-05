/**
 * Copy zep-pixel-agents binary assets into pixel-office/public/assets.
 * Run export-zep-furniture first (via npm run sync:zep-assets).
 *
 * Usage: ZEP_PIXEL_AGENTS_ROOT=../zep-pixel-agents node scripts/sync-zep-assets.mjs
 */
import { cpSync, existsSync, mkdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { findExistingFurnitureDir, findZepRoot, getZepRootCandidates } from './zep-root.mjs'
import { hasValidFurnitureBundle, installFurnitureBundle } from './zep-furniture-bundle.mjs'
import { copyZepTilesetAssets, ensureFloorsPng } from './zep-tileset-copy.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const destRoot = join(__dirname, '../public/assets')
const vendorFurniture = join(__dirname, '../vendor/zep-furniture')
const catalogDest = join(destRoot, 'furniture', 'furniture-catalog.json')

const copied = { floors: false, walls: false, characters: false, furniture: false }

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

function mergeTilesetCopied(tileset) {
  copied.floors = copied.floors || tileset.floors
  copied.walls = copied.walls || tileset.walls
  copied.characters = copied.characters || tileset.characters
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
  mergeTilesetCopied(copyZepTilesetAssets(root, destRoot, { log: msg => console.log(msg) }))

  const furnDir = findExistingFurnitureDir(root)
  if (furnDir && hasValidFurnitureBundle(furnDir) && !existsSync(catalogDest)) {
    cpSync(furnDir, join(destRoot, 'furniture'), { recursive: true, force: true })
    copied.furniture = true
    console.log('  copied furniture/ from zep')
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

if (!copied.floors) {
  ensureFloorsPng(destRoot, { log: msg => console.log(msg) })
  copied.floors = true
}

console.log('[sync-zep-assets] Done.')
