import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import {
  BOSSKU_AGENT_IDS,
  MIN_OFFICE_FURNITURE_COUNT,
  defaultAgentMeta,
  isValidPersistedOfficeLayout,
  layoutFurnitureCount,
} from '../utils/pixelOfficeLayout'

const __dirname = dirname(fileURLToPath(import.meta.url))
const realisticPath = join(__dirname, '../pixel-office/public/assets/realistic-office-layout.json')

describe('pixel office realistic layout', () => {
  it('bundled realistic layout has full furniture', () => {
    const layout = JSON.parse(readFileSync(realisticPath, 'utf8')) as Record<string, unknown>
    expect(layoutFurnitureCount(layout)).toBeGreaterThanOrEqual(MIN_OFFICE_FURNITURE_COUNT)
    expect(layoutFurnitureCount(layout)).toBeGreaterThanOrEqual(50)
  })

  it('rejects sparse persisted layouts', () => {
    expect(isValidPersistedOfficeLayout({ furniture: [] })).toBe(false)
    expect(isValidPersistedOfficeLayout({ furniture: new Array(25).fill({}) })).toBe(true)
  })

  it('default agent meta uses fixed seat uids from realistic layout', () => {
    const layout = JSON.parse(readFileSync(realisticPath, 'utf8')) as {
      furniture: Array<{ uid: string }>
    }
    const uids = new Set(layout.furniture.map(f => f.uid))
    const meta = defaultAgentMeta()
    for (const id of Object.values(BOSSKU_AGENT_IDS)) {
      const seatId = meta[id]?.seatId
      expect(seatId).toBeTruthy()
      expect(uids.has(seatId!)).toBe(true)
    }
  })
})
