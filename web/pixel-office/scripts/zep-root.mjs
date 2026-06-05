/**
 * Resolve zep-pixel-agents repo root and common asset paths.
 */
import { existsSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))

export function getZepRootCandidates() {
  const fromEnv = process.env.ZEP_PIXEL_AGENTS_ROOT?.trim()
  const candidates = [
    fromEnv,
    existsSync('/.dockerenv') ? '/workspace/zep-pixel-agents' : null,
    join(__dirname, '../../../../zep-pixel-agents'),
    join(__dirname, '../../../zep-pixel-agents'),
  ].filter(Boolean)
  return [...new Set(candidates)]
}

export function findZepRoot() {
  for (let root of getZepRootCandidates()) {
    if (!root) continue
    root = root.trim()
    if (!existsSync(root)) continue
    const checks = [
      join(root, 'scripts', '.tileset-working', 'tileset-metadata-final.json'),
      join(root, 'webview-ui', 'public', 'assets', 'furniture', 'furniture-catalog.json'),
      join(root, 'assets', 'furniture', 'furniture-catalog.json'),
      join(root, 'webview-ui', 'public', 'assets', 'characters', 'char_0.png'),
      join(root, 'assets', 'characters', 'char_0.png'),
      join(root, 'scripts', '5-export-assets.ts'),
    ]
    if (checks.some(p => existsSync(p))) return root
  }
  return null
}

export function getZepPaths(root) {
  return {
    metadata: join(root, 'scripts', '.tileset-working', 'tileset-metadata-final.json'),
    tilesetWebview: join(root, 'webview-ui', 'public', 'assets', 'office_tileset_16x16.png'),
    tilesetAssets: join(root, 'assets', 'office_tileset_16x16.png'),
    furnitureWebview: join(root, 'webview-ui', 'public', 'assets', 'furniture'),
    furnitureAssets: join(root, 'assets', 'furniture'),
    exportScript: join(root, 'scripts', '5-export-assets.ts'),
  }
}

export function findExistingFurnitureDir(root) {
  const paths = getZepPaths(root)
  if (existsSync(join(paths.furnitureWebview, 'furniture-catalog.json'))) {
    return paths.furnitureWebview
  }
  if (existsSync(join(paths.furnitureAssets, 'furniture-catalog.json'))) {
    return paths.furnitureAssets
  }
  return null
}

export function findTilesetPath(root) {
  const paths = getZepPaths(root)
  if (process.env.ZEP_OFFICE_TILESET_PATH && existsSync(process.env.ZEP_OFFICE_TILESET_PATH)) {
    return process.env.ZEP_OFFICE_TILESET_PATH
  }
  if (existsSync(paths.tilesetWebview)) return paths.tilesetWebview
  if (existsSync(paths.tilesetAssets)) return paths.tilesetAssets
  return null
}
