import { diffLines } from 'diff'

export type FileChangeEvidence = {
  path?: string
  change_type?: string
  before?: string
  after?: string
  diff?: string
}

export type FileChangeStats = {
  added: number
  removed: number
  unchanged: number
}

export type FileChangeAssessment = {
  blocked: boolean
  reason: string | null
  stats: FileChangeStats
}

const PLACEHOLDER_PATTERNS = [
  'will be determined',
  'to be determined',
  'tbd',
  'todo',
  'placeholder',
  'to be filled',
  'read the file',
  'after reading the file',
  'not yet written',
  'coming soon',
  'fill in later',
]

export function isPlaceholderText(text: string): boolean {
  const trimmed = text.trim()
  if (trimmed === '' || trimmed === '...' || trimmed === '…') {
    return true
  }

  const lower = trimmed.toLowerCase()
  const isShortProposal = trimmed.length <= 500
  return PLACEHOLDER_PATTERNS.some((p) => lower === p || (isShortProposal && lower.includes(p)))
}

function lineCount(text: string): number {
  if (!text) return 0
  const lines = text.split(/\r\n|\n|\r/)
  if (lines.length > 1 && lines[lines.length - 1] === '') {
    return lines.length - 1
  }

  return lines.length
}

export function computeFileChangeStats(before: string, after: string): FileChangeStats {
  const stats: FileChangeStats = { added: 0, removed: 0, unchanged: 0 }
  for (const part of diffLines(before, after)) {
    const lines = part.value.split(/\r\n|\n|\r/).filter((l, i, arr) => !(i === arr.length - 1 && l === ''))
    const count = lines.length
    if (part.added) stats.added += count
    else if (part.removed) stats.removed += count
    else stats.unchanged += count
  }

  return stats
}

export function validateFileChange(
  before: string,
  after: string,
  changeType: string,
  relativePath?: string,
): string | null {
  const type = (changeType || 'modified').toLowerCase()

  if (type === 'deleted') {
    return null
  }

  if (type === 'created') {
    if (!after.trim()) return 'New file proposal has empty content.'
    if (isPlaceholderText(after)) return 'New file content looks like a placeholder, not real source code.'

    return null
  }

  if (!after.trim()) {
    return 'Modified file proposal has empty after content.'
  }

  if (isPlaceholderText(after)) {
    return 'Proposed file content is placeholder text, not complete file contents.'
  }

  const beforeLines = lineCount(before)
  const afterLines = lineCount(after)
  if (beforeLines >= 20 && afterLines <= 2) {
    return 'Proposed change would replace almost the entire file — executor must supply full file contents or a valid diff.'
  }

  if (before.length >= 200 && after.length > 0 && after.length < before.length * 0.15) {
    return 'Proposed change would replace almost the entire file — executor must supply full file contents or a valid diff.'
  }

  const path = (relativePath || '').toLowerCase()
  if (type === 'modified' && (path === '' || path.endsWith('.php'))) {
    const beforePhp = before.includes('<?php') || /\b(class|function)\b/.test(before)
    const afterPhp = after.includes('<?php') || /\b(class|function)\b/.test(after)
    if (beforePhp && !afterPhp) {
      return 'Proposed PHP file content is missing expected structure (<?php, class, or function).'
    }
  }

  return null
}

export function assessFileChange(
  evidence: FileChangeEvidence,
  apiBlocked?: boolean,
  apiReason?: string | null,
): FileChangeAssessment {
  const before = String(evidence.before ?? '')
  const after = String(evidence.after ?? '')
  const changeType = String(evidence.change_type ?? 'modified')
  const path = String(evidence.path ?? '')

  const stats = computeFileChangeStats(before, after)
  const localReason = validateFileChange(before, after, changeType, path)
  const blocked = apiBlocked === true || localReason !== null

  return {
    blocked,
    reason: apiReason || localReason,
    stats,
  }
}

export function formatStatsSummary(stats: FileChangeStats, before: string, after: string): string {
  const parts: string[] = []
  if (stats.added > 0) parts.push(`+${stats.added}`)
  if (stats.removed > 0) parts.push(`-${stats.removed}`)
  if (parts.length === 0 && stats.unchanged > 0) {
    parts.push('no line changes')
  }
  const sizeHint = `${before.length.toLocaleString()} → ${after.length.toLocaleString()} bytes`

  return `${parts.join(' / ')} lines · ${sizeHint}`
}
