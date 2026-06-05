import { describe, expect, it, vi } from 'vitest'
import {
  createProceduralFloorSprites,
  FALLBACK_FLOOR_COLOR,
} from '../utils/pixelOfficeAssetLoader'

describe('createProceduralFloorSprites', () => {
  it('builds seven 16x16 tiles without network', () => {
    const sprites = createProceduralFloorSprites()
    expect(sprites).toHaveLength(7)
    expect(sprites[0]).toHaveLength(16)
    expect(sprites[0][0]).toHaveLength(16)
    expect(sprites[0][0][0]).toBe(FALLBACK_FLOOR_COLOR)
  })
})

describe('loadFloorTiles procedural fallback', () => {
  it('uses procedural tiles when asset URLs are missing', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: false, status: 404 }),
    )

    const { loadFloorTiles } = await import('../utils/pixelOfficeAssetLoader')
    const tiles = await loadFloorTiles('/pixel-office/assets-missing-test')

    expect(tiles).toHaveLength(7)
    expect(tiles?.[0][0][0]).toBe(FALLBACK_FLOOR_COLOR)

    vi.unstubAllGlobals()
  })
})
