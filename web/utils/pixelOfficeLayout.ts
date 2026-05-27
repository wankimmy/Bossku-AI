/** Bossku agent role → zep pixel office numeric character id. */
export const BOSSKU_AGENT_IDS = {
  orchestrator: 1,
  executor: 2,
  auditor: 3,
  'security-auditor': 4,
  'final-reviewer': 5,
  memory: 6,
  tools: 7,
} as const

export type BosskuPixelAgentRole = keyof typeof BOSSKU_AGENT_IDS

export const BOSSKU_PIXEL_AGENT_ROLES = Object.keys(BOSSKU_AGENT_IDS) as BosskuPixelAgentRole[]

const LAYOUT_STORAGE_KEY = 'bossku-pixel-layout-v3'
const LEGACY_LAYOUT_STORAGE_KEYS = ['bossku-pixel-layout-v2', 'bossku-pixel-layout-v1'] as const
const SEATS_STORAGE_KEY = 'bossku-pixel-agent-seats-v1'

/** Persisted layouts with fewer items are treated as invalid (empty edit session). */
export const MIN_OFFICE_FURNITURE_COUNT = 20

export type AgentSeatMeta = {
  palette: number
  hueShift: number
  seatId: string | null
  label: string
}

/** Chair / couch seat uids from realistic-office-layout.json (zep default). */
const ROLE_SEAT_IDS: Record<BosskuPixelAgentRole, string> = {
  orchestrator: 'f-1770831748000-ksmj',
  executor: 'f-1770831767041-zaxr',
  auditor: 'f-1771277148633-u8j9',
  'security-auditor': 'f-1770831768596-kx8j',
  'final-reviewer': 'f-1771273618430-t3u3',
  memory: 'f-1771273619213-ylzf',
  tools: 'f-1771254147816-bpkr',
}

const ROLE_PALETTES: Record<BosskuPixelAgentRole, { palette: number; hueShift: number }> = {
  orchestrator: { palette: 0, hueShift: 0 },
  executor: { palette: 1, hueShift: 0 },
  auditor: { palette: 2, hueShift: 0 },
  'security-auditor': { palette: 3, hueShift: 0 },
  'final-reviewer': { palette: 4, hueShift: 0 },
  memory: { palette: 5, hueShift: 0 },
  tools: { palette: 5, hueShift: 24 },
}

export function agentIdForRole(role: string): number | null {
  if (role in BOSSKU_AGENT_IDS) {
    return BOSSKU_AGENT_IDS[role as BosskuPixelAgentRole]
  }
  return null
}

export function roleForAgentId(id: number): BosskuPixelAgentRole | null {
  for (const [role, agentId] of Object.entries(BOSSKU_AGENT_IDS)) {
    if (agentId === id) return role as BosskuPixelAgentRole
  }
  return null
}

export function layoutFurnitureCount(layout: Record<string, unknown> | null | undefined): number {
  if (!layout) return 0
  const furniture = layout.furniture
  return Array.isArray(furniture) ? furniture.length : 0
}

export function isValidPersistedOfficeLayout(layout: Record<string, unknown> | null | undefined): boolean {
  return layoutFurnitureCount(layout) >= MIN_OFFICE_FURNITURE_COUNT
}

/** Default seat assignments per role (zep palettes + fixed seats). */
export function defaultAgentMeta(): Record<number, AgentSeatMeta> {
  const labels: Record<BosskuPixelAgentRole, string> = {
    orchestrator: 'Orchestrator',
    executor: 'Executor',
    auditor: 'Auditor',
    'security-auditor': 'Security',
    'final-reviewer': 'Reviewer',
    memory: 'Memory',
    tools: 'Tools',
  }

  const meta: Record<number, AgentSeatMeta> = {}
  for (const role of BOSSKU_PIXEL_AGENT_ROLES) {
    const id = BOSSKU_AGENT_IDS[role]
    const { palette, hueShift } = ROLE_PALETTES[role]
    meta[id] = {
      palette,
      hueShift,
      seatId: ROLE_SEAT_IDS[role],
      label: labels[role],
    }
  }
  return meta
}

export function loadPersistedSeats(): Record<number, AgentSeatMeta> {
  if (typeof localStorage === 'undefined') return defaultAgentMeta()
  try {
    const raw = localStorage.getItem(SEATS_STORAGE_KEY)
    if (!raw) return defaultAgentMeta()
    const parsed = JSON.parse(raw) as Record<string, AgentSeatMeta>
    const base = defaultAgentMeta()
    for (const [key, value] of Object.entries(parsed)) {
      const id = Number(key)
      if (base[id] && value) {
        base[id] = { ...base[id], ...value, label: base[id].label }
      }
    }
    return base
  } catch {
    return defaultAgentMeta()
  }
}

export function savePersistedSeats(seats: Record<number, { palette: number; hueShift: number; seatId: string | null }>): void {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(SEATS_STORAGE_KEY, JSON.stringify(seats))
  } catch {
    // ignore quota errors
  }
}

export function loadPersistedLayout(): Record<string, unknown> | null {
  if (typeof localStorage === 'undefined') return null
  try {
    const raw = localStorage.getItem(LAYOUT_STORAGE_KEY)
    if (!raw) return null
    const layout = JSON.parse(raw) as Record<string, unknown>
    return isValidPersistedOfficeLayout(layout) ? layout : null
  } catch {
    return null
  }
}

/** Drop saved layout (internal use only). */
export function clearPersistedOfficeLayout(): void {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.removeItem(LAYOUT_STORAGE_KEY)
    for (const key of LEGACY_LAYOUT_STORAGE_KEYS) {
      localStorage.removeItem(key)
    }
  } catch {
    // ignore
  }
}

export function savePersistedLayout(layout: Record<string, unknown>): void {
  if (!isValidPersistedOfficeLayout(layout)) return
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(LAYOUT_STORAGE_KEY, JSON.stringify(layout))
  } catch {
    // ignore
  }
}

export async function fetchDefaultOfficeLayout(): Promise<Record<string, unknown> | null> {
  try {
    const res = await fetch('/pixel-office/assets/default-layout.json')
    if (!res.ok) return null
    return (await res.json()) as Record<string, unknown>
  } catch {
    return null
  }
}

/** Valid saved layout or bundled default with full furniture. */
export async function resolveOfficeLayout(): Promise<Record<string, unknown> | null> {
  const saved = loadPersistedLayout()
  if (saved) return saved
  return fetchDefaultOfficeLayout()
}
