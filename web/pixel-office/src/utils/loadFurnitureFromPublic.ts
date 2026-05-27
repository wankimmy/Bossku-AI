import type { LoadedAssetData } from '../office/layout/furnitureCatalog.js'
import type { SpriteData } from '../office/types.js'

const PNG_ALPHA_THRESHOLD = 128
const ASSET_BASE = '/pixel-office/assets'

type CatalogEntry = {
  id: string
  name?: string
  label: string
  category: string
  file?: string
  furniturePath?: string
  width: number
  height: number
  footprintW: number
  footprintH: number
  isDesk: boolean
  canPlaceOnWalls?: boolean
  groupId?: string
  orientation?: string
  state?: string
  canPlaceOnSurfaces?: boolean
  backgroundTiles?: number
}

function normalizeCatalog(raw: unknown): CatalogEntry[] {
  if (Array.isArray(raw)) return raw as CatalogEntry[]
  if (raw && typeof raw === 'object' && Array.isArray((raw as { assets?: unknown }).assets)) {
    return (raw as { assets: CatalogEntry[] }).assets
  }
  return []
}

async function loadImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error(`Failed to load: ${url}`))
    img.src = url
  })
}

async function assetUrlExists(url: string): Promise<boolean> {
  try {
    const res = await fetch(url, { method: 'HEAD', cache: 'force-cache' })
    if (res.ok) return true
    if (res.status === 405) {
      const getRes = await fetch(url, { method: 'GET', cache: 'force-cache' })
      return getRes.ok
    }
    return false
  } catch {
    return false
  }
}

function furnitureAssetUrlCandidates(file: string): string[] {
  const normalized = file.startsWith('assets/') ? file.slice('assets/'.length) : file
  const withoutFurniturePrefix = normalized.replace(/^furniture\//, '')
  const candidates = [
    `${ASSET_BASE}/${normalized}`,
    `${ASSET_BASE}/furniture/${normalized}`,
    `${ASSET_BASE}/furniture/${withoutFurniturePrefix}`,
  ]
  return [...new Set(candidates)]
}

async function firstExistingAssetUrl(candidates: string[]): Promise<string | null> {
  for (const url of candidates) {
    if (await assetUrlExists(url)) return url
  }
  return null
}

/** Quick probe: catalog exists and at least one sprite PNG is reachable. */
export async function furnitureSpritesReachable(): Promise<boolean> {
  try {
    const res = await fetch(`${ASSET_BASE}/furniture/furniture-catalog.json`)
    if (!res.ok) return false
    const catalog = normalizeCatalog(await res.json())
    const sample = catalog[0]
    if (!sample) return false
    let file = sample.furniturePath ?? sample.file ?? `furniture/${sample.id}.png`
    if (!file.startsWith('assets/') && !file.startsWith('furniture/')) {
      file = `furniture/${file}`
    }
    return (await firstExistingAssetUrl(furnitureAssetUrlCandidates(file))) != null
  } catch {
    return false
  }
}

async function pngUrlToSprite(url: string, width: number, height: number): Promise<SpriteData> {
  const img = await loadImage(url)
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas 2d unavailable')
  ctx.drawImage(img, 0, 0, width, height)
  const { data } = ctx.getImageData(0, 0, width, height)
  const sprite: SpriteData = []
  for (let y = 0; y < height; y++) {
    const row: string[] = []
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * 4
      if (data[i + 3] < PNG_ALPHA_THRESHOLD) {
        row.push('')
      } else {
        const r = data[i].toString(16).padStart(2, '0')
        const g = data[i + 1].toString(16).padStart(2, '0')
        const b = data[i + 2].toString(16).padStart(2, '0')
        row.push(`#${r}${g}${b}`.toUpperCase())
      }
    }
    sprite.push(row)
  }
  return sprite
}

/** Iframe fallback when host has not posted furnitureAssetsLoaded yet. */
export async function loadFurnitureFromPublic(): Promise<LoadedAssetData | null> {
  try {
    const res = await fetch(`${ASSET_BASE}/furniture/furniture-catalog.json`)
    if (!res.ok) return null
    const catalog = normalizeCatalog(await res.json())
    if (catalog.length === 0) return null

    if (!(await furnitureSpritesReachable())) {
      console.warn(
        '[pixel-office] furniture-catalog.json is present but no furniture PNG sprites were found under /pixel-office/assets. Run npm run build:pixel-office from web/.',
      )
      return null
    }

    const sprites: Record<string, SpriteData> = {}
    await Promise.all(
      catalog.map(async (asset) => {
        try {
          let file = asset.furniturePath ?? asset.file ?? `furniture/${asset.id}.png`
          if (!file.startsWith('assets/') && !file.startsWith('furniture/')) {
            file = `furniture/${file}`
          }
          if (file.startsWith('assets/')) {
            file = file.slice('assets/'.length)
          }
          const url = await firstExistingAssetUrl(furnitureAssetUrlCandidates(file))
          if (!url) return
          sprites[asset.id] = await pngUrlToSprite(url, asset.width, asset.height)
        } catch {
          // skip missing sprite
        }
      }),
    )

    if (Object.keys(sprites).length === 0) return null
    return { catalog, sprites }
  } catch {
    return null
  }
}
