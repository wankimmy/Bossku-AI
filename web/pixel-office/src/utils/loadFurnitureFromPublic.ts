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
    const res = await fetch(url, { method: 'GET', cache: 'force-cache' })
    return res.ok
  } catch {
    return false
  }
}

async function firstExistingAssetUrl(candidates: string[]): Promise<string | null> {
  for (const url of candidates) {
    if (await assetUrlExists(url)) return url
  }
  return null
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
          const url = await firstExistingAssetUrl([
            `${ASSET_BASE}/${file}`,
            `${ASSET_BASE}/furniture/${file}`,
          ])
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
