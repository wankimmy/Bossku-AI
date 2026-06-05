/**
 * Copy zep tileset binaries (floors.png, walls.png, characters/) into pixel-office public assets.
 */
import { copyFileSync, cpSync, existsSync, mkdirSync, readdirSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { PNG } from 'pngjs'

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

/** Asset dirs that may contain floors.png (zep repo or VSIX extract). */
function collectAssetDirCandidates(searchRoot) {
  const candidates = new Set()
  const fixedBases = [
    join(searchRoot, 'assets'),
    join(searchRoot, 'webview-ui', 'public', 'assets'),
    join(searchRoot, 'extension', 'webview-ui', 'public', 'assets'),
    join(searchRoot, 'extension', 'assets'),
  ]
  for (const base of fixedBases) {
    if (existsSync(join(base, 'floors.png')) || existsSync(join(base, 'walls.png'))) {
      candidates.add(base)
    }
  }

  const stack = [searchRoot]
  const visited = new Set()
  while (stack.length > 0) {
    const dir = stack.pop()
    if (!dir || visited.has(dir)) continue
    visited.add(dir)
    let entries
    try {
      entries = readdirSync(dir)
    } catch {
      continue
    }
    if (existsSync(join(dir, 'floors.png')) || existsSync(join(dir, 'walls.png'))) {
      candidates.add(dir)
    }
    for (const name of entries) {
      if (name === 'node_modules' || name.startsWith('.')) continue
      const p = join(dir, name)
      try {
        if (statSync(p).isDirectory() && visited.size < 500) stack.push(p)
      } catch {
        // skip
      }
    }
  }

  return [...candidates]
}

/**
 * @param {string} searchRoot zep-pixel-agents root or VSIX extract directory
 * @param {string} destRoot e.g. pixel-office/public/assets
 * @param {{ log?: (msg: string) => void }} [options]
 * @returns {{ floors: boolean, walls: boolean, characters: boolean }}
 */
export function copyZepTilesetAssets(searchRoot, destRoot, options = {}) {
  const log = options.log ?? (() => {})
  const copied = { floors: false, walls: false, characters: false }

  if (!searchRoot || !existsSync(searchRoot)) {
    return copied
  }

  mkdirSync(destRoot, { recursive: true })
  const assetDirs = collectAssetDirCandidates(searchRoot)

  for (const assetsDir of assetDirs) {
    if (!copied.floors && safeCopyFile(join(assetsDir, 'floors.png'), join(destRoot, 'floors.png'))) {
      copied.floors = true
      log(`  copied ${join(destRoot, 'floors.png')}`)
    }
    if (!copied.walls && safeCopyFile(join(assetsDir, 'walls.png'), join(destRoot, 'walls.png'))) {
      copied.walls = true
      log(`  copied ${join(destRoot, 'walls.png')}`)
    }
    const charSrc = join(assetsDir, 'characters')
    if (!copied.characters && safeCopyDir(charSrc, join(destRoot, 'characters'))) {
      copied.characters = true
      log(`  copied ${join(destRoot, 'characters')}/`)
    }
    if (copied.floors && copied.walls && copied.characters) break
  }

  if (!copied.floors) {
    ensureFloorsPng(destRoot, options)
    copied.floors = true
  }

  return copied
}

export function tilesetsPresent(destRoot) {
  return (
    existsSync(join(destRoot, 'floors.png'))
    && existsSync(join(destRoot, 'walls.png'))
    && existsSync(join(destRoot, 'characters', 'char_0.png'))
  )
}

/** 7×16px grayscale strip matching pixel-office FALLBACK_FLOOR_COLOR (#808080). */
export function writeFallbackFloorsPng(destPath) {
  const tileW = 16
  const patternCount = 7
  const width = tileW * patternCount
  const height = tileW
  const png = new PNG({ width, height })
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const idx = (width * y + x) << 2
      png.data[idx] = 0x80
      png.data[idx + 1] = 0x80
      png.data[idx + 2] = 0x80
      png.data[idx + 3] = 0xff
    }
  }
  mkdirSync(dirname(destPath), { recursive: true })
  writeFileSync(destPath, PNG.sync.write(png))
}

/**
 * Ensure floors.png exists (generate fallback if zep/VSIX did not provide one).
 * @param {string} destRoot
 * @param {{ log?: (msg: string) => void }} [options]
 */
export function ensureFloorsPng(destRoot, options = {}) {
  const dest = join(destRoot, 'floors.png')
  if (existsSync(dest)) {
    return true
  }
  writeFallbackFloorsPng(dest)
  const log = options.log ?? (() => {})
  log(`  wrote fallback ${dest}`)
  return true
}
