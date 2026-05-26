import { diffLines } from 'diff'
import type { FileChange } from '~/types/bossku'

export type DiffLineKind = 'add' | 'remove' | 'context' | 'header'

export type SplitCellKind = 'context' | 'remove' | 'add' | 'empty'

export type SplitDiffRow = {
  leftLine: string
  rightLine: string
  leftKind: SplitCellKind
  rightKind: SplitCellKind
  leftNum?: number
  rightNum?: number
}

export type DiffDisplayLine = {
  kind: DiffLineKind
  text: string
  raw: string
}

export type DiffDisplayInput = Pick<FileChange, 'path' | 'change_type' | 'diff' | 'after' | 'before'>

export function buildDisplayDiff(change: DiffDisplayInput): string | null {
  const existing = change.diff?.trim()
  if (existing) {
    return existing
  }

  const path = change.path || 'file'
  const type = change.change_type ?? 'modified'
  const after = change.after?.trim() ?? ''
  const before = change.before ?? ''

  if (type === 'created' && after !== '') {
    const lines = [`--- /dev/null`, `+++ ${path}`, ...after.split(/\r\n|\n|\r/).map(line => `+${line}`)]

    return lines.join('\n')
  }

  if (type === 'deleted' && before !== '') {
    const lines = [`--- ${path}`, `+++ /dev/null`, ...before.split(/\r\n|\n|\r/).map(line => `-${line}`)]

    return lines.join('\n')
  }

  // Modified files: rely on buildSplitRowsFromBeforeAfter (no misleading +only unified diff).

  return null
}

export function parseDiffLines(diff: string): DiffDisplayLine[] {
  if (!diff.trim()) {
    return []
  }

  return diff.split(/\r\n|\n|\r/).map((raw) => {
    if (raw.startsWith('--- ') || raw.startsWith('+++ ')) {
      return { kind: 'header', text: raw, raw }
    }
    if (raw.startsWith('+')) {
      return { kind: 'add', text: raw.slice(1), raw }
    }
    if (raw.startsWith('-')) {
      return { kind: 'remove', text: raw.slice(1), raw }
    }
    if (raw.startsWith(' ')) {
      return { kind: 'context', text: raw.slice(1), raw }
    }

    return { kind: 'context', text: raw, raw }
  })
}

export function diffLineClass(kind: DiffLineKind): string {
  switch (kind) {
    case 'add':
      return 'bg-emerald-950/40 text-emerald-300'
    case 'remove':
      return 'bg-rose-950/40 text-rose-300'
    case 'header':
      return 'text-zinc-500 text-[11px]'
    default:
      return 'text-zinc-400'
  }
}

export function diffLinePrefix(kind: DiffLineKind): string {
  switch (kind) {
    case 'add':
      return '+'
    case 'remove':
      return '-'
    case 'header':
      return ''
    default:
      return ' '
  }
}

function splitPartLines(value: string): string[] {
  if (value === '') {
    return []
  }
  const lines = value.split(/\r\n|\n|\r/)
  if (lines.length > 1 && lines[lines.length - 1] === '') {
    lines.pop()
  }

  return lines
}

export function buildSplitRowsFromBeforeAfter(before: string, after: string): SplitDiffRow[] {
  const rows: SplitDiffRow[] = []
  let leftNum = 1
  let rightNum = 1

  for (const part of diffLines(before, after)) {
    const lines = splitPartLines(part.value)
    if (part.added) {
      for (const line of lines) {
        rows.push({
          leftLine: '',
          rightLine: line,
          leftKind: 'empty',
          rightKind: 'add',
          rightNum: rightNum++,
        })
      }
    }
    else if (part.removed) {
      for (const line of lines) {
        rows.push({
          leftLine: line,
          rightLine: '',
          leftKind: 'remove',
          rightKind: 'empty',
          leftNum: leftNum++,
        })
      }
    }
    else {
      for (const line of lines) {
        rows.push({
          leftLine: line,
          rightLine: line,
          leftKind: 'context',
          rightKind: 'context',
          leftNum: leftNum++,
          rightNum: rightNum++,
        })
      }
    }
  }

  return pairAdjacentChangeRows(rows)
}

export function buildSplitRowsFromUnified(diff: string): SplitDiffRow[] {
  const parsed = parseDiffLines(diff)
  const rows: SplitDiffRow[] = []
  let leftNum = 1
  let rightNum = 1
  const pendingRemoves: string[] = []

  const flushRemove = (addLine?: string) => {
    const removed = pendingRemoves.shift() ?? ''
    if (addLine !== undefined) {
      rows.push({
        leftLine: removed,
        rightLine: addLine,
        leftKind: removed !== '' ? 'remove' : 'empty',
        rightKind: addLine !== '' ? 'add' : 'empty',
        leftNum: removed !== '' ? leftNum++ : undefined,
        rightNum: addLine !== '' ? rightNum++ : undefined,
      })

      return
    }
    if (removed !== '') {
      rows.push({
        leftLine: removed,
        rightLine: '',
        leftKind: 'remove',
        rightKind: 'empty',
        leftNum: leftNum++,
      })
    }
  }

  for (const line of parsed) {
    if (line.kind === 'header') {
      continue
    }
    if (line.kind === 'remove') {
      pendingRemoves.push(line.text)
      continue
    }
    if (line.kind === 'add') {
      if (pendingRemoves.length > 0) {
        flushRemove(line.text)
      }
      else {
        rows.push({
          leftLine: '',
          rightLine: line.text,
          leftKind: 'empty',
          rightKind: 'add',
          rightNum: rightNum++,
        })
      }
      continue
    }
    while (pendingRemoves.length > 0) {
      flushRemove()
    }
    rows.push({
      leftLine: line.text,
      rightLine: line.text,
      leftKind: 'context',
      rightKind: 'context',
      leftNum: leftNum++,
      rightNum: rightNum++,
    })
  }

  while (pendingRemoves.length > 0) {
    flushRemove()
  }

  return rows
}

/** Pair consecutive remove-only + add-only rows on the same line when counts match. */
function pairAdjacentChangeRows(rows: SplitDiffRow[]): SplitDiffRow[] {
  const out: SplitDiffRow[] = []
  let i = 0
  while (i < rows.length) {
    const row = rows[i]
    const next = rows[i + 1]
    if (
      row
      && next
      && row.leftKind === 'remove'
      && row.rightKind === 'empty'
      && next.leftKind === 'empty'
      && next.rightKind === 'add'
    ) {
      out.push({
        leftLine: row.leftLine,
        rightLine: next.rightLine,
        leftKind: 'remove',
        rightKind: 'add',
        leftNum: row.leftNum,
        rightNum: next.rightNum,
      })
      i += 2
      continue
    }
    if (row) {
      out.push(row)
    }
    i += 1
  }

  return out
}

export function buildSplitDiffRows(change: DiffDisplayInput): SplitDiffRow[] {
  const before = change.before ?? ''
  const after = change.after ?? ''
  if (before !== '' || after !== '') {
    return buildSplitRowsFromBeforeAfter(before, after)
  }

  const unified = buildDisplayDiff(change)
  if (unified) {
    return buildSplitRowsFromUnified(unified)
  }

  return []
}

export function splitCellClass(kind: SplitCellKind, side: 'left' | 'right'): string {
  if (kind === 'empty') {
    return 'bg-zinc-900/40 text-zinc-600'
  }
  if (kind === 'remove') {
    return 'bg-rose-950/50 text-rose-200'
  }
  if (kind === 'add') {
    return 'bg-emerald-950/50 text-emerald-200'
  }

  return side === 'left' ? 'bg-zinc-950/80 text-zinc-400' : 'bg-zinc-950/80 text-zinc-400'
}
