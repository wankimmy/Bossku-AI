import type { FileChange } from '~/types/bossku'

export type DiffLineKind = 'add' | 'remove' | 'context' | 'header'

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

  if (after !== '') {
    const lines = [`--- ${path}`, `+++ ${path}`, ...after.split(/\r\n|\n|\r/).map(line => `+${line}`)]

    return lines.join('\n')
  }

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
