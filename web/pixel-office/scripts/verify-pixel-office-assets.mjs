/**
 * Verify pixel-office public assets after build/sync.
 */
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const assetsRoot = join(__dirname, '../public/assets')
const publicRoot = join(__dirname, '../../public/pixel-office/assets')
const layoutPath = join(assetsRoot, 'realistic-office-layout.json')
const catalogPaths = [
  join(assetsRoot, 'furniture', 'furniture-catalog.json'),
  join(publicRoot, 'furniture', 'furniture-catalog.json'),
]

function countPngs(dir) {
  if (!existsSync(dir)) return 0
  let count = 0
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    const st = statSync(p)
    if (st.isDirectory()) count += countPngs(p)
    else if (name.endsWith('.png')) count += 1
  }
  return count
}

function loadLayoutAssetIds() {
  if (!existsSync(layoutPath)) return []
  const layout = JSON.parse(readFileSync(layoutPath, 'utf-8'))
  const ids = new Set()
  for (const item of layout.furniture || []) {
    if (item?.type) ids.add(item.type)
  }
  return [...ids]
}

function verifySkipped() {
  if (process.env.BOSSKU_PIXEL_OFFICE_SKIP_ASSETS === '1') return true
  if (process.env.BOSSKU_PIXEL_OFFICE_GRACEFUL === '1') return true
  if (existsSync('/.dockerenv') && process.env.BOSSKU_PIXEL_OFFICE_STRICT !== '1') return true
  return false
}

function main() {
  if (verifySkipped()) {
    console.warn('[verify-pixel-office-assets] Skipped (graceful / skip mode).')
    return
  }

  const catalogPath = catalogPaths.find(p => existsSync(p))
  if (!catalogPath) {
    console.error('[verify-pixel-office-assets] Missing furniture-catalog.json')
    console.error('  Run: npm run export:zep-furniture (from web/ or pixel-office/)')
    process.exit(1)
  }

  const catalog = JSON.parse(readFileSync(catalogPath, 'utf-8'))
  const catalogIds = new Set((catalog.assets || []).map(a => a.id))
  const furnitureDir = dirname(catalogPath)
  const pngCount = countPngs(furnitureDir)

  const layoutIds = loadLayoutAssetIds()
  const missing = layoutIds.filter(id => !catalogIds.has(id))

  console.log(`[verify-pixel-office-assets] Catalog: ${catalog.assets?.length ?? 0} entries, ${pngCount} PNGs`)

  if (pngCount < 10) {
    console.error('[verify-pixel-office-assets] Too few furniture PNGs (expected dozens after export).')
    process.exit(1)
  }

  if (missing.length > 0) {
    console.warn(
      `[verify-pixel-office-assets] Layout references ${missing.length} asset id(s) not in catalog (first 5):`,
      missing.slice(0, 5).join(', '),
    )
  }

  const required = ['characters/char_0.png', 'walls.png']
  for (const rel of required) {
    const p = join(assetsRoot, rel)
    if (!existsSync(p) && !existsSync(join(publicRoot, rel))) {
      console.warn(`[verify-pixel-office-assets] Missing ${rel} (run sync:zep-assets)`)
    }
  }

  console.log('[verify-pixel-office-assets] OK')
}

main()
