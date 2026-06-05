import { BOSSKU_AGENT_IDS, type BosskuPixelAgentRole } from './pixelOfficeLayout'

const CHAR_SHEET_FRAMES = 7
const CHAR_FRAME_W = 24
const CHAR_FRAME_H = 32
const CHAR_SHEET_ROWS = 3
const MAX_CHAR_INDEX = 5

const ROLE_ALIASES: Record<string, BosskuPixelAgentRole | 'executor'> = {
  planner: 'orchestrator',
  'code-reviewer': 'final-reviewer',
  reviewer: 'final-reviewer',
  evaluator: 'auditor',
  router: 'orchestrator',
  system: 'executor',
}

export function normalizeAgentRole(role: string): string {
  const key = role.trim().toLowerCase()
  if (key in BOSSKU_AGENT_IDS) return key
  if (key in ROLE_ALIASES) return ROLE_ALIASES[key] as string
  return key
}

export function agentRoleToCharIndex(role: string): number {
  const normalized = normalizeAgentRole(role)
  const mapped = BOSSKU_AGENT_IDS[normalized as BosskuPixelAgentRole]
  if (mapped !== undefined) {
    return Math.min(mapped, MAX_CHAR_INDEX)
  }
  return BOSSKU_AGENT_IDS.executor
}

export function characterSpriteUrl(charIndex: number): string {
  const idx = Math.max(0, Math.min(charIndex, MAX_CHAR_INDEX))
  return `/pixel-office/assets/characters/char_${idx}.png`
}

export function characterSpriteStyle(scale: number, charIndex: number): Record<string, string> {
  const s = Math.max(1, scale)
  const sheetW = CHAR_SHEET_FRAMES * CHAR_FRAME_W * s
  const sheetH = CHAR_SHEET_ROWS * CHAR_FRAME_H * s
  return {
    width: `${CHAR_FRAME_W * s}px`,
    height: `${CHAR_FRAME_H * s}px`,
    backgroundImage: `url(${characterSpriteUrl(charIndex)})`,
    backgroundPosition: '0 0',
    backgroundSize: `${sheetW}px ${sheetH}px`,
    backgroundRepeat: 'no-repeat',
    imageRendering: 'pixelated',
  }
}
