import { TileType } from '../types.js'
import type { FloorColor, OfficeLayout, PlacedFurniture, TileType as TileTypeVal } from '../types.js'

const COLS = 21
const ROWS = 21

const COLOR_MAIN: FloorColor = { h: 28, s: 50, b: -50, c: -37 }
const COLOR_COLLAB: FloorColor = { h: 35, s: 30, b: 15, c: 0 }
const COLOR_LOUNGE: FloorColor = { h: 23, s: 21, b: 46, c: 0 }
const COLOR_DOOR: FloorColor = { h: 35, s: 25, b: 10, c: 0 }

/** Asset IDs from the Bossku furniture pack (zep pixel office). */
const A = {
  deskLarge: 'ASSET_40',
  deskMed: 'ASSET_42',
  deskBench: 'ASSET_NEW_106',
  chair: 'ASSET_18',
  monitor: 'ASSET_7',
  monitorAlt: 'ASSET_83',
  plant: 'ASSET_109',
  plantAlt: 'ASSET_99',
  plantSmall: 'ASSET_44',
  couchL: 'ASSET_140',
  couchR: 'ASSET_141',
  table: 'ASSET_101',
  whiteboard: 'ASSET_102',
  bookshelf: 'ASSET_143',
  cooler: 'ASSET_51',
  lamp: 'ASSET_72',
  rug: 'ASSET_142',
} as const

function buildTiles(): { tiles: TileTypeVal[]; tileColors: Array<FloorColor | null> } {
  const W = TileType.WALL
  const F1 = TileType.FLOOR_1
  const F2 = TileType.FLOOR_2
  const F3 = TileType.FLOOR_3
  const F4 = TileType.FLOOR_4

  const tiles: TileTypeVal[] = []
  const tileColors: Array<FloorColor | null> = []

  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c < COLS; c++) {
      if (r === 0 || r === ROWS - 1 || c === 0 || c === COLS - 1) {
        tiles.push(W)
        tileColors.push(null)
        continue
      }

      if (c === 10) {
        if (r >= 4 && r <= 6) {
          tiles.push(F4)
          tileColors.push(COLOR_DOOR)
        } else {
          tiles.push(W)
          tileColors.push(null)
        }
        continue
      }

      if (c >= 1 && c <= 9 && r >= 1 && r <= 7) {
        tiles.push(F1)
        tileColors.push(COLOR_MAIN)
        continue
      }

      if (c >= 1 && c <= 9 && r >= 8 && r <= 19) {
        tiles.push(F1)
        tileColors.push(COLOR_MAIN)
        continue
      }

      if (c >= 11 && c <= 19 && r >= 1 && r <= 9) {
        tiles.push(F2)
        tileColors.push(COLOR_COLLAB)
        continue
      }

      if (c >= 11 && c <= 19 && r >= 10 && r <= 19) {
        tiles.push(F3)
        tileColors.push(COLOR_LOUNGE)
        continue
      }

      tiles.push(W)
      tileColors.push(null)
    }
  }

  return { tiles, tileColors }
}

function deskPod(col: number, row: number, id: string): PlacedFurniture[] {
  return [
    { uid: `${id}-desk`, type: A.deskBench, col, row },
    { uid: `${id}-chair`, type: A.chair, col, row: row - 1 },
    { uid: `${id}-pc`, type: A.monitor, col: col + 1, row },
  ]
}

/**
 * Startup-style open office: exec nook, bench desks, collaboration pods, lounge.
 */
export function createStartupOfficeLayout(): OfficeLayout {
  const { tiles, tileColors } = buildTiles()

  const furniture: PlacedFurniture[] = [
    // Executive / standup nook (top-left)
    { uid: 'wb-exec', type: A.whiteboard, col: 5, row: 1 },
    { uid: 'table-exec', type: A.table, col: 4, row: 4 },
    { uid: 'couch-exec-l', type: A.couchL, col: 2, row: 5 },
    { uid: 'couch-exec-r', type: A.couchR, col: 7, row: 5 },
    { uid: 'plant-exec-1', type: A.plant, col: 1, row: 2 },
    { uid: 'plant-exec-2', type: A.plantAlt, col: 8, row: 2 },
    { uid: 'lamp-exec', type: A.lamp, col: 1, row: 6 },

    // Open workspace — bench rows
    ...deskPod(2, 11, 'pod-a'),
    ...deskPod(5, 11, 'pod-b'),
    ...deskPod(2, 14, 'pod-c'),
    ...deskPod(5, 14, 'pod-d'),
    ...deskPod(2, 17, 'pod-e'),
    { uid: 'desk-focus', type: A.deskLarge, col: 6, row: 17 },
    { uid: 'chair-focus', type: A.chair, col: 6, row: 16 },
    { uid: 'pc-focus', type: A.monitorAlt, col: 7, row: 17 },

    // Perimeter decor (main floor)
    { uid: 'shelf-main', type: A.bookshelf, col: 1, row: 10 },
    { uid: 'plant-main-1', type: A.plantSmall, col: 1, row: 18 },
    { uid: 'plant-main-2', type: A.plantSmall, col: 8, row: 18 },
    { uid: 'rug-main', type: A.rug, col: 3, row: 9 },

    // Collaboration wing (right top)
    { uid: 'wb-collab', type: A.whiteboard, col: 16, row: 1 },
    ...deskPod(12, 3, 'collab-1'),
    ...deskPod(16, 3, 'collab-2'),
    ...deskPod(12, 6, 'collab-3'),
    { uid: 'desk-pair', type: A.deskMed, col: 16, row: 6 },
    { uid: 'chair-pair-a', type: A.chair, col: 16, row: 5 },
    { uid: 'chair-pair-b', type: A.chair, col: 17, row: 6 },
    { uid: 'pc-pair', type: A.monitor, col: 17, row: 6 },
    { uid: 'plant-collab', type: A.plant, col: 18, row: 8 },

    // Lounge (right bottom)
    { uid: 'couch-lounge-l', type: A.couchL, col: 12, row: 14 },
    { uid: 'couch-lounge-r', type: A.couchR, col: 16, row: 14 },
    { uid: 'table-lounge', type: A.table, col: 14, row: 15 },
    { uid: 'cooler', type: A.cooler, col: 18, row: 11 },
    { uid: 'plant-lounge-1', type: A.plant, col: 12, row: 11 },
    { uid: 'plant-lounge-2', type: A.plantAlt, col: 18, row: 17 },
    { uid: 'rug-lounge', type: A.rug, col: 14, row: 17 },
  ]

  return {
    version: 1,
    cols: COLS,
    rows: ROWS,
    tiles,
    tileColors,
    furniture,
  }
}
