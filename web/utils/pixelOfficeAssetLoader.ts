/**
 * Browser-side loader for zep pixel office assets (posts to iframe via PixelOfficePanel).
 */

const PNG_ALPHA_THRESHOLD = 128
const WALL_PIECE_WIDTH = 16
const WALL_PIECE_HEIGHT = 32
const WALL_GRID_COLS = 4
const WALL_BITMASK_COUNT = 16
const FLOOR_PATTERN_COUNT = 7
const FLOOR_TILE_SIZE = 16
const CHAR_FRAME_W = 24
const CHAR_FRAME_H = 32
const CHAR_FRAMES_PER_ROW = 7
const CHAR_COUNT = 6

export type PixelOfficeAssetMessages = {
  characterSpritesLoaded?: { type: 'characterSpritesLoaded'; characters: CharacterSpritesPayload[] }
  floorTilesLoaded?: { type: 'floorTilesLoaded'; sprites: string[][][] }
  wallTilesLoaded?: { type: 'wallTilesLoaded'; sprites: string[][][] }
  furnitureAssetsLoaded?: {
    type: 'furnitureAssetsLoaded'
    catalog: FurnitureCatalogEntry[]
    sprites: Record<string, string[][]>
  }
}

type CharacterSpritesPayload = {
  down: string[][][]
  up: string[][][]
  right: string[][][]
}

type FurnitureCatalogEntry = {
  id: string
  name: string
  label: string
  category: string
  file: string
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

function normalizeFurnitureCatalog(raw: unknown): FurnitureCatalogEntry[] {
  if (Array.isArray(raw)) return raw as FurnitureCatalogEntry[]
  if (raw && typeof raw === 'object' && Array.isArray((raw as { assets?: unknown }).assets)) {
    return (raw as { assets: FurnitureCatalogEntry[] }).assets
  }
  return []
}

async function loadImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error(`Failed to load image: ${url}`))
    img.src = url
  })
}

async function pngUrlToSpriteData(url: string, width: number, height: number): Promise<string[][]> {
  const img = await loadImage(url)
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas 2d unavailable')
  ctx.drawImage(img, 0, 0, width, height)
  const { data } = ctx.getImageData(0, 0, width, height)
  const sprite: string[][] = []
  for (let y = 0; y < height; y++) {
    const row: string[] = []
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * 4
      const a = data[i + 3]
      if (a < PNG_ALPHA_THRESHOLD) {
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

async function loadCharacterSprites(base: string): Promise<CharacterSpritesPayload[] | null> {
  const directions = ['down', 'up', 'right'] as const
  const characters: CharacterSpritesPayload[] = []

  for (let ci = 0; ci < CHAR_COUNT; ci++) {
    const url = `${base}/characters/char_${ci}.png`
    try {
      const img = await loadImage(url)
      const charData: CharacterSpritesPayload = { down: [], up: [], right: [] }
      for (let dirIdx = 0; dirIdx < directions.length; dirIdx++) {
        const dir = directions[dirIdx]
        const rowOffsetY = dirIdx * CHAR_FRAME_H
        const frames: string[][][] = []
        for (let f = 0; f < CHAR_FRAMES_PER_ROW; f++) {
          const canvas = document.createElement('canvas')
          canvas.width = CHAR_FRAME_W
          canvas.height = CHAR_FRAME_H
          const ctx = canvas.getContext('2d')!
          ctx.drawImage(
            img,
            f * CHAR_FRAME_W,
            rowOffsetY,
            CHAR_FRAME_W,
            CHAR_FRAME_H,
            0,
            0,
            CHAR_FRAME_W,
            CHAR_FRAME_H,
          )
          frames.push(await canvasToSprite(canvas, CHAR_FRAME_W, CHAR_FRAME_H))
        }
        charData[dir] = frames
      }
      characters.push(charData)
    } catch {
      return null
    }
  }
  return characters
}

async function canvasToSprite(canvas: HTMLCanvasElement, width: number, height: number): Promise<string[][]> {
  const ctx = canvas.getContext('2d')!
  const { data } = ctx.getImageData(0, 0, width, height)
  const sprite: string[][] = []
  for (let y = 0; y < height; y++) {
    const row: string[] = []
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * 4
      if (data[i + 3] < PNG_ALPHA_THRESHOLD) row.push('')
      else {
        row.push(
          `#${data[i].toString(16).padStart(2, '0')}${data[i + 1].toString(16).padStart(2, '0')}${data[i + 2].toString(16).padStart(2, '0')}`.toUpperCase(),
        )
      }
    }
    sprite.push(row)
  }
  return sprite
}

async function loadFloorTiles(base: string): Promise<string[][][] | null> {
  try {
    const img = await loadImage(`${base}/floors.png`)
    const sprites: string[][][] = []
    for (let t = 0; t < FLOOR_PATTERN_COUNT; t++) {
      const canvas = document.createElement('canvas')
      canvas.width = FLOOR_TILE_SIZE
      canvas.height = FLOOR_TILE_SIZE
      const ctx = canvas.getContext('2d')!
      ctx.drawImage(
        img,
        t * FLOOR_TILE_SIZE,
        0,
        FLOOR_TILE_SIZE,
        FLOOR_TILE_SIZE,
        0,
        0,
        FLOOR_TILE_SIZE,
        FLOOR_TILE_SIZE,
      )
      sprites.push(await canvasToSprite(canvas, FLOOR_TILE_SIZE, FLOOR_TILE_SIZE))
    }
    return sprites
  } catch {
    return null
  }
}

async function loadWallTiles(base: string): Promise<string[][][] | null> {
  try {
    const img = await loadImage(`${base}/walls.png`)
    const sprites: string[][][] = []
    for (let mask = 0; mask < WALL_BITMASK_COUNT; mask++) {
      const ox = (mask % WALL_GRID_COLS) * WALL_PIECE_WIDTH
      const oy = Math.floor(mask / WALL_GRID_COLS) * WALL_PIECE_HEIGHT
      const canvas = document.createElement('canvas')
      canvas.width = WALL_PIECE_WIDTH
      canvas.height = WALL_PIECE_HEIGHT
      const ctx = canvas.getContext('2d')!
      ctx.drawImage(
        img,
        ox,
        oy,
        WALL_PIECE_WIDTH,
        WALL_PIECE_HEIGHT,
        0,
        0,
        WALL_PIECE_WIDTH,
        WALL_PIECE_HEIGHT,
      )
      sprites.push(await canvasToSprite(canvas, WALL_PIECE_WIDTH, WALL_PIECE_HEIGHT))
    }
    return sprites
  } catch {
    return null
  }
}

async function loadFurnitureAssets(base: string): Promise<{
  catalog: FurnitureCatalogEntry[]
  sprites: Record<string, string[][]>
} | null> {
  try {
    const res = await fetch(`${base}/furniture/furniture-catalog.json`)
    if (!res.ok) return null
    const catalogData = await res.json()
    const catalog = normalizeFurnitureCatalog(catalogData)
    if (catalog.length === 0) return null
    const sprites: Record<string, string[][]> = {}

    await Promise.all(
      catalog.map(async (asset) => {
        try {
          let file = asset.furniturePath ?? asset.file
          if (!file.startsWith('assets/') && !file.startsWith('furniture/')) {
            file = `furniture/${file}`
          }
          if (file.startsWith('assets/')) {
            file = file.slice('assets/'.length)
          }
          const url = `${base}/${file}`
          sprites[asset.id] = await pngUrlToSpriteData(url, asset.width, asset.height)
        } catch {
          // skip missing asset file
        }
      }),
    )

    if (Object.keys(sprites).length === 0) return null
    return { catalog, sprites }
  } catch {
    return null
  }
}

/** Load zep office assets from /pixel-office/assets (zep load order). */
export async function loadAllPixelOfficeAssets(
  base = '/pixel-office/assets',
): Promise<PixelOfficeAssetMessages[]> {
  const messages: PixelOfficeAssetMessages[] = []

  const characters = await loadCharacterSprites(base)
  if (characters?.length) {
    messages.push({ type: 'characterSpritesLoaded', characters })
  }

  const floors = await loadFloorTiles(base)
  if (floors) {
    messages.push({ type: 'floorTilesLoaded', sprites: floors })
  }

  const walls = await loadWallTiles(base)
  if (walls) {
    messages.push({ type: 'wallTilesLoaded', sprites: walls })
  }

  const furniture = await loadFurnitureAssets(base)
  if (furniture) {
    messages.push({
      type: 'furnitureAssetsLoaded',
      catalog: furniture.catalog,
      sprites: furniture.sprites,
    })
  }

  return messages
}
