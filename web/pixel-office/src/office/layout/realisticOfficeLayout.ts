import type { OfficeLayout } from '../types.js'
import realisticLayoutJson from '../../../public/assets/realistic-office-layout.json'

/** Zep default office (multi-room, ~70 furniture items). */
export function createRealisticOfficeLayout(): OfficeLayout {
  return realisticLayoutJson as OfficeLayout
}
