/**
 * Download zep-format furniture from Open VSX extension VSIX into vendor/ and public/assets/furniture.
 *
 * Usage: node scripts/fetch-furniture-bundle.mjs
 * Env:
 *   BOSSKU_FURNITURE_VSIX_URL — direct VSIX URL
 *   BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE=0 — skip fetch
 *   BOSSKU_ZEP_FURNITURE_CACHE=0 — do not write vendor/ cache (default: cache on)
 */
import { cpSync, createWriteStream, existsSync, mkdirSync, readdirSync, rmSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { pipeline } from 'node:stream/promises'
import { Readable } from 'node:stream'
import {
  catalogPath,
  dirHasPngSprites,
  hasValidFurnitureBundle,
  installFurnitureBundle,
  parseCatalogAssets,
} from './zep-furniture-bundle.mjs'
import { copyZepTilesetAssets, tilesetsPresent } from './zep-tileset-copy.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const destAssetsRoot = join(__dirname, '../public/assets')
const destFurniture = join(destAssetsRoot, 'furniture')
const vendorFurniture = join(__dirname, '../vendor/zep-furniture')
const tmpDir = join(__dirname, '../.tmp-furniture-fetch')

const EXTENSION_CANDIDATES = [
  { publisher: 'pablodelucca', name: 'zep-agents' },
  { publisher: 'pablodelucca', name: 'pixel-agents' },
  { publisher: 'ZepAgents', name: 'zep-agents' },
]

function shouldFetch() {
  if (process.env.BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE === '0') return false
  return true
}

function shouldCacheVendor() {
  if (process.env.BOSSKU_ZEP_FURNITURE_CACHE === '0') return false
  return true
}

function scoreCatalogDir(dir) {
  const assets = parseCatalogAssets(catalogPath(dir))
  if (assets.length === 0) return 0
  const zepBonus = assets.some(a => String(a.id).startsWith('ASSET_')) ? 100_000 : 0
  const pngBonus = dirHasPngSprites(dir) ? 10_000 : 0
  return zepBonus + pngBonus + assets.length
}

function findBestCatalogDir(root) {
  let bestDir = null
  let bestScore = 0
  const stack = [root]
  while (stack.length > 0) {
    const dir = stack.pop()
    if (existsSync(catalogPath(dir))) {
      const score = scoreCatalogDir(dir)
      if (score > bestScore) {
        bestScore = score
        bestDir = dir
      }
    }
    for (const name of readdirSync(dir)) {
      const p = join(dir, name)
      try {
        if (statSync(p).isDirectory()) stack.push(p)
      } catch {
        // skip
      }
    }
  }
  return bestScore > 0 ? bestDir : null
}

/** Prefer the furniture/ folder that contains catalog + PNG category trees. */
function resolveFurnitureExportDir(catalogDir) {
  if (dirHasPngSprites(catalogDir)) return catalogDir
  let dir = catalogDir
  for (let i = 0; i < 6; i++) {
    const parent = dirname(dir)
    if (parent === dir) break
    if (hasValidFurnitureBundle(parent)) return parent
    dir = parent
  }
  return catalogDir
}

async function downloadToFile(url, dest) {
  const res = await fetch(url, { redirect: 'follow' })
  if (!res.ok) throw new Error(`HTTP ${res.status} for ${url}`)
  mkdirSync(dirname(dest), { recursive: true })
  const body = res.body
  if (!body) throw new Error('Empty response body')
  await pipeline(Readable.fromWeb(body), createWriteStream(dest))
}

async function resolveVsixUrl(publisher, name) {
  if (process.env.BOSSKU_FURNITURE_VSIX_URL) {
    return process.env.BOSSKU_FURNITURE_VSIX_URL
  }
  const metaUrl = `https://open-vsx.org/api/${publisher}/${name}/latest`
  const res = await fetch(metaUrl)
  if (!res.ok) return null
  const meta = await res.json()
  return meta?.files?.download ?? null
}

async function extractVsix(vsixPath, outDir) {
  const { default: AdmZip } = await import('adm-zip')
  const zip = new AdmZip(vsixPath)
  zip.extractAllTo(outDir, true)
}

async function copyTilesetsFromExtract(extractDir) {
  const tileset = copyZepTilesetAssets(extractDir, destAssetsRoot, {
    log: msg => console.log(`[fetch-furniture-bundle]${msg}`),
  })
  if (tileset.floors || tileset.walls || tileset.characters) {
    console.log('[fetch-furniture-bundle] Copied tileset assets from VSIX.')
  }
  return tileset
}

export async function fetchFurnitureBundle() {
  if (!shouldFetch()) {
    console.log('[fetch-furniture-bundle] Skipped (BOSSKU_AUTO_FETCH_FURNITURE_BUNDLE=0).')
    return false
  }

  if (hasValidFurnitureBundle(destFurniture)) {
    const count = parseCatalogAssets(catalogPath(destFurniture)).length
    console.log(`[fetch-furniture-bundle] Valid bundle at public/assets/furniture (${count} assets, PNGs present).`)
    if (!tilesetsPresent(destAssetsRoot)) {
      console.log('[fetch-furniture-bundle] Tilesets missing; fetching VSIX for floors/walls/characters...')
    } else {
      return true
    }
  }

  if (existsSync(destFurniture) && !hasValidFurnitureBundle(destFurniture)) {
    console.warn('[fetch-furniture-bundle] Incomplete bundle at dest; removing and re-fetching.')
    rmSync(destFurniture, { recursive: true, force: true })
  }

  if (hasValidFurnitureBundle(vendorFurniture) && tilesetsPresent(destAssetsRoot)) {
    console.log('[fetch-furniture-bundle] Using vendor cache.')
    installFurnitureBundle(vendorFurniture, destFurniture)
    return true
  }

  if (hasValidFurnitureBundle(vendorFurniture) && !tilesetsPresent(destAssetsRoot)) {
    console.log('[fetch-furniture-bundle] Vendor furniture OK; tilesets still missing, will fetch VSIX.')
  }

  if (existsSync(tmpDir)) rmSync(tmpDir, { recursive: true })
  mkdirSync(tmpDir, { recursive: true })

  const vsixPath = join(tmpDir, 'extension.vsix')

  for (const { publisher, name } of EXTENSION_CANDIDATES) {
    try {
      const url = await resolveVsixUrl(publisher, name)
      if (!url) {
        console.warn(`[fetch-furniture-bundle] No VSIX URL for ${publisher}.${name}`)
        continue
      }
      console.log(`[fetch-furniture-bundle] Downloading ${publisher}.${name}...`)
      await downloadToFile(url, vsixPath)

      const extractDir = join(tmpDir, 'extract')
      if (existsSync(extractDir)) rmSync(extractDir, { recursive: true })
      mkdirSync(extractDir, { recursive: true })
      await extractVsix(vsixPath, extractDir)
      await copyTilesetsFromExtract(extractDir)

      const catalogDir = findBestCatalogDir(extractDir)
      if (!catalogDir) {
        console.warn(`[fetch-furniture-bundle] No furniture-catalog.json in ${publisher}.${name} VSIX.`)
        continue
      }

      const exportDir = resolveFurnitureExportDir(catalogDir)
      const assets = parseCatalogAssets(catalogPath(exportDir))
      const count = assets.length
      if (count === 0 || !dirHasPngSprites(exportDir)) {
        console.warn(
          `[fetch-furniture-bundle] ${publisher}.${name}: catalog has ${count} assets but no PNG sprites in tree.`,
        )
        continue
      }
      console.log(`[fetch-furniture-bundle] Extracted furniture catalog (${count} assets) from ${publisher}.${name}.`)

      if (shouldCacheVendor()) {
        mkdirSync(dirname(vendorFurniture), { recursive: true })
        if (existsSync(vendorFurniture)) rmSync(vendorFurniture, { recursive: true, force: true })
        cpSync(exportDir, vendorFurniture, { recursive: true })
      }
      installFurnitureBundle(exportDir, destFurniture)

      if (existsSync(tmpDir)) rmSync(tmpDir, { recursive: true })
      return hasValidFurnitureBundle(destFurniture) || tilesetsPresent(destAssetsRoot)
    } catch (err) {
      console.warn(`[fetch-furniture-bundle] ${publisher}.${name} failed:`, err.message || err)
    }
  }

  if (existsSync(tmpDir)) rmSync(tmpDir, { recursive: true })
  console.warn('[fetch-furniture-bundle] Could not fetch furniture from VSIX.')
  return false
}

const isMain =
  process.argv[1] &&
  import.meta.url === pathToFileURL(process.argv[1]).href
if (isMain) {
  fetchFurnitureBundle()
    .then(ok => process.exit(ok ? 0 : 1))
    .catch(err => {
      console.error('[fetch-furniture-bundle] Fatal:', err)
      process.exit(1)
    })
}
