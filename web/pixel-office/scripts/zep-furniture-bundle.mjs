/**
 * Shared helpers: furniture catalog + PNG sprite install/validation.
 */
import { copyFileSync, cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync } from 'node:fs'
import { join } from 'node:path'

export const FURNITURE_CATALOG_NAME = 'furniture-catalog.json'

const BUNDLE_META_SKIP = new Set([
  FURNITURE_CATALOG_NAME,
  'asset-index.json',
  'default-layout-1.json',
  'characters',
  'floors',
  'walls',
])

export function catalogPath(dir) {
  return join(dir, FURNITURE_CATALOG_NAME)
}

export function dirHasPngSprites(dir) {
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

export function parseCatalogAssets(catalogFile) {
  try {
    const data = JSON.parse(readFileSync(catalogFile, 'utf-8'))
    if (Array.isArray(data)) return data
    if (data?.assets && Array.isArray(data.assets)) return data.assets
  } catch {
    // skip invalid json
  }
  return []
}

function relativeSpritePath(asset) {
  let file = asset.furniturePath ?? asset.file ?? `${asset.id}.png`
  if (file.startsWith('assets/')) file = file.slice('assets/'.length)
  if (file.startsWith('furniture/')) file = file.slice('furniture/'.length)
  return file
}

/** True when a catalog entry's PNG exists under bundleDir (flat zep layout). */
export function catalogAssetPngExists(bundleDir, asset) {
  const rel = relativeSpritePath(asset)
  const candidates = [
    join(bundleDir, rel),
    join(bundleDir, 'furniture', rel),
  ]
  return candidates.some(p => existsSync(p))
}

/** Merge furniture/BIN → BIN when VSIX/vendor used a nested furniture/ folder. */
export function flattenNestedFurnitureSprites(destDir) {
  const nested = join(destDir, 'furniture')
  if (!existsSync(nested) || !statSync(nested).isDirectory()) return
  for (const name of readdirSync(nested)) {
    const from = join(nested, name)
    const to = join(destDir, name)
    if (existsSync(to)) {
      cpSync(from, to, { recursive: true, force: true })
    } else {
      cpSync(from, to, { recursive: true })
    }
  }
  rmSync(nested, { recursive: true, force: true })
}

/**
 * Copy catalog + sprite category folders into destDir (flat furniture/BIN/BIN.png layout).
 * Skips VSIX bundle extras (characters, floors, walls at export root).
 */
export function installFurnitureBundle(sourceRoot, destDir) {
  const catalogFile = catalogPath(sourceRoot)
  if (!existsSync(catalogFile)) {
    throw new Error(`Missing ${FURNITURE_CATALOG_NAME} in ${sourceRoot}`)
  }

  if (existsSync(destDir)) rmSync(destDir, { recursive: true, force: true })
  mkdirSync(destDir, { recursive: true })
  copyFileSync(catalogFile, catalogPath(destDir))

  function copySpriteEntries(fromDir) {
    if (!existsSync(fromDir)) return
    for (const name of readdirSync(fromDir)) {
      if (BUNDLE_META_SKIP.has(name)) continue
      if (name.endsWith('.js') || name.endsWith('.css')) continue
      if (name === 'furniture' && fromDir === sourceRoot) {
        copySpriteEntries(join(fromDir, name))
        continue
      }
      const src = join(fromDir, name)
      const dest = join(destDir, name)
      try {
        if (statSync(src).isDirectory()) {
          cpSync(src, dest, { recursive: true, force: true })
        } else if (name.endsWith('.png')) {
          mkdirSync(destDir, { recursive: true })
          copyFileSync(src, join(destDir, name))
        }
      } catch {
        // skip unreadable entry
      }
    }
  }

  copySpriteEntries(sourceRoot)
  flattenNestedFurnitureSprites(destDir)
}

/** True when catalog exists and at least one catalog sprite PNG is on disk. */
export function hasValidFurnitureBundle(dir) {
  if (!existsSync(catalogPath(dir))) return false
  const assets = parseCatalogAssets(catalogPath(dir))
  if (assets.length === 0) return false
  return assets.some(asset => catalogAssetPngExists(dir, asset))
}
