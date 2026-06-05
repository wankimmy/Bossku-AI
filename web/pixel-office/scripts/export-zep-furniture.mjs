/**
 * Export zep furniture PNGs + furniture-catalog.json into pixel-office/public/assets/furniture.
 *
 * Order: existing dest catalog -> vendor/ -> zep pre-exported -> tileset export (tsx or pngjs).
 *
 * Usage:
 *   ZEP_PIXEL_AGENTS_ROOT=../../zep-pixel-agents node scripts/export-zep-furniture.mjs
 *   BOSSKU_FORCE_EXPORT=1 node scripts/export-zep-furniture.mjs
 */
import { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import {
  findExistingFurnitureDir,
  findTilesetPath,
  findZepRoot,
  getZepPaths,
} from './zep-root.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const destFurniture = join(__dirname, '../public/assets/furniture')
const vendorFurniture = join(__dirname, '../vendor/zep-furniture')
const catalogName = 'furniture-catalog.json'

function catalogPath(dir) {
  return join(dir, catalogName)
}

function dirHasPngSprites(dir) {
  if (!existsSync(dir)) return false
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    try {
      if (statSync(p).isDirectory()) {
        if (dirHasPngSprites(p)) return true
      } else if (name.endsWith('.png')) {
        return true
      }
    } catch {
      // skip
    }
  }
  return false
}

function hasCatalog(dir) {
  if (!existsSync(catalogPath(dir))) return false
  if (!dirHasPngSprites(dir)) return false
  try {
    const data = JSON.parse(readFileSync(catalogPath(dir), 'utf-8'))
    const assets = Array.isArray(data) ? data : (data.assets ?? [])
    return assets.length > 0
  } catch {
    return false
  }
}

function copyFurnitureTree(src, dest) {
  mkdirSync(dirname(dest), { recursive: true })
  if (existsSync(dest)) rmSync(dest, { recursive: true })
  cpSync(src, dest, { recursive: true })
}

function isStrict() {
  return process.env.BOSSKU_PIXEL_OFFICE_STRICT === '1'
}

function skipExportAllowed() {
  return process.env.BOSSKU_PIXEL_OFFICE_SKIP_ASSETS === '1'
}

function gracefulExportAllowed() {
  if (isStrict()) return false
  if (skipExportAllowed()) return true
  if (process.env.BOSSKU_PIXEL_OFFICE_GRACEFUL === '1') return true
  return existsSync('/.dockerenv')
}

async function finishWithoutFurniture(reason) {
  if (await tryFetchBundle() && hasCatalog(destFurniture)) {
    return
  }
  if (gracefulExportAllowed()) {
    console.warn(`[export-zep-furniture] ${reason} Continuing without furniture (graceful mode; container will still start).`)
    return
  }
  printTilesetHelp()
  process.exit(1)
}

function printTilesetHelp() {
  console.error(`
[export-zep-furniture] Missing office furniture assets.

The realistic office layout needs furniture-catalog.json and PNG sprites exported
from the zep-pixel-agents tileset pipeline.

Prerequisites:
  1. Clone zep-pixel-agents and set ZEP_PIXEL_AGENTS_ROOT (or place it next to Bossku-AI).
  2. Purchase the Office Interior Tileset (16x16) and run zep's import pipeline:
       cd zep-pixel-agents && npm install && npm run import-tileset
     This produces webview-ui/public/assets/office_tileset_16x16.png
  3. Re-run: npm run export:zep-furniture

Alternatively, copy a pre-exported furniture/ tree to:
  web/pixel-office/vendor/zep-furniture/

Or set ZEP_OFFICE_TILESET_PATH to your office_tileset_16x16.png file.

Local dev only (sparse office, build continues): BOSSKU_PIXEL_OFFICE_SKIP_ASSETS=1
`)
}

async function exportWithPngjs(metadataPath, tilesetPath, assetsDir) {
  const { PNG } = await import('pngjs')

  const metadata = JSON.parse(readFileSync(metadataPath, 'utf-8'))
  const assets = metadata.assets.filter(a => !a.discard)

  const tileset = PNG.sync.read(readFileSync(tilesetPath))
  const { width: tilesetWidth, height: tilesetHeight, data: tilesetData } = tileset

  function extractAssetPng(asset) {
    const w = asset.paddedWidth
    const h = asset.paddedHeight
    const assetPng = new PNG({ width: w, height: h })
    const erasedSet = new Set((asset.erasedPixels || []).map(p => `${p.x},${p.y}`))

    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const sourceX = asset.paddedX + x
        const sourceY = asset.paddedY + y
        const isErased = erasedSet.has(`${x},${y}`)
        const dstIdx = (y * w + x) << 2

        if (
          sourceX < 0 ||
          sourceX >= tilesetWidth ||
          sourceY < 0 ||
          sourceY >= tilesetHeight ||
          isErased
        ) {
          assetPng.data[dstIdx] = 0
          assetPng.data[dstIdx + 1] = 0
          assetPng.data[dstIdx + 2] = 0
          assetPng.data[dstIdx + 3] = 0
        } else {
          const srcIdx = (sourceY * tilesetWidth + sourceX) << 2
          assetPng.data[dstIdx] = tilesetData[srcIdx]
          assetPng.data[dstIdx + 1] = tilesetData[srcIdx + 1]
          assetPng.data[dstIdx + 2] = tilesetData[srcIdx + 2]
          assetPng.data[dstIdx + 3] = tilesetData[srcIdx + 3]
        }
      }
    }

    return PNG.sync.write(assetPng)
  }

  if (existsSync(assetsDir)) rmSync(assetsDir, { recursive: true })
  mkdirSync(assetsDir, { recursive: true })

  const categories = new Set(assets.map(a => a.category))
  for (const category of categories) {
    mkdirSync(join(assetsDir, category), { recursive: true })
  }

  const catalog = []

  for (const asset of assets) {
    const categoryDir = join(assetsDir, asset.category)
    const filename = `${asset.name}.png`
    const filepath = join(categoryDir, filename)
    const relativePath = `furniture/${asset.category}/${filename}`

    const pngBuffer = extractAssetPng(asset)
    writeFileSync(filepath, pngBuffer)

    const entry = {
      id: asset.id,
      name: asset.name,
      label: asset.label,
      category: asset.category,
      file: relativePath,
      width: asset.paddedWidth,
      height: asset.paddedHeight,
      footprintW: asset.footprintW,
      footprintH: asset.footprintH,
      isDesk: asset.isDesk,
    }

    if (asset.canPlaceOnWalls) entry.canPlaceOnWalls = true
    if (asset.canPlaceOnSurfaces) entry.canPlaceOnSurfaces = true
    if (asset.backgroundTiles && asset.backgroundTiles > 0) {
      entry.backgroundTiles = asset.backgroundTiles
    }
    if (asset.groupId) {
      entry.groupId = asset.groupId
      if (asset.orientation) {
        entry.orientation = asset.orientation
      } else {
        const suffix = asset.name.split('_').pop()?.toLowerCase()
        if (suffix && ['front', 'back', 'left', 'right'].includes(suffix)) {
          entry.orientation = suffix
        }
      }
    }
    if (asset.state) entry.state = asset.state

    catalog.push(entry)
  }

  const catalogOutput = {
    version: 1,
    timestamp: new Date().toISOString(),
    totalAssets: catalog.length,
    categories: Array.from(categories).sort(),
    assets: catalog.sort((a, b) => a.id.localeCompare(b.id)),
  }

  writeFileSync(catalogPath(assetsDir), JSON.stringify(catalogOutput, null, 2))
  console.log(`[export-zep-furniture] Exported ${catalog.length} assets to ${assetsDir}`)
}

function spawnZepExport(root) {
  console.log(`[export-zep-furniture] Running zep export via tsx in ${root}`)
  const result = spawnSync('npx', ['tsx', 'scripts/5-export-assets.ts'], {
    cwd: root,
    stdio: 'inherit',
    shell: true,
  })
  return result.status === 0
}

async function exportFromTileset(root) {
  const paths = getZepPaths(root)
  const metadataPath = paths.metadata
  const tilesetPath = findTilesetPath(root)

  if (!existsSync(metadataPath)) {
    console.error(`[export-zep-furniture] Missing metadata: ${metadataPath}`)
    return false
  }
  if (!tilesetPath) {
    console.error('[export-zep-furniture] Missing office_tileset_16x16.png in zep repo.')
    return false
  }

  console.log(`[export-zep-furniture] Tileset: ${tilesetPath}`)
  console.log(`[export-zep-furniture] Metadata: ${metadataPath}`)

  if (existsSync(paths.exportScript)) {
    const ok = spawnZepExport(root)
    if (ok) {
      const zepFurniture = findExistingFurnitureDir(root)
      if (zepFurniture) {
        copyFurnitureTree(zepFurniture, destFurniture)
        return true
      }
    }
    console.warn('[export-zep-furniture] zep tsx export failed; falling back to pngjs.')
  }

  await exportWithPngjs(metadataPath, tilesetPath, destFurniture)
  return true
}

async function tryFetchBundle() {
  if (process.env.BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE === '0') return false
  try {
    const { fetchFurnitureBundle } = await import('./fetch-furniture-bundle.mjs')
    return await fetchFurnitureBundle()
  } catch (err) {
    console.warn('[export-zep-furniture] Bundle fetch failed:', err.message || err)
    return false
  }
}

async function main() {
  if (hasCatalog(destFurniture) && process.env.BOSSKU_FORCE_EXPORT !== '1') {
    console.log(`[export-zep-furniture] Catalog already exists at ${destFurniture}; skipping.`)
    console.log('  Set BOSSKU_FORCE_EXPORT=1 to re-export.')
    return
  }

  // Always attempt open bundle fetch (graceful = no crash, not skip furniture).
  if (await tryFetchBundle() && hasCatalog(destFurniture)) {
    return
  }

  if (hasCatalog(vendorFurniture)) {
    console.log(`[export-zep-furniture] Using vendor bundle: ${vendorFurniture}`)
    copyFurnitureTree(vendorFurniture, destFurniture)
    return
  }

  const root = findZepRoot()
  if (root) {
    const existing = findExistingFurnitureDir(root)
    if (existing) {
      console.log(`[export-zep-furniture] Copying pre-exported furniture from zep: ${existing}`)
      copyFurnitureTree(existing, destFurniture)
      return
    }

    const exported = await exportFromTileset(root)
    if (exported && hasCatalog(destFurniture)) return
  }

  await finishWithoutFurniture('No furniture catalog available (tileset not imported in zep).')
}

main().catch(err => {
  console.error('[export-zep-furniture] Fatal:', err)
  process.exit(1)
})
