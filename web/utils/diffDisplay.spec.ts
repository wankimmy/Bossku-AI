import { describe, expect, it } from 'vitest'
import {
  buildSplitDiffRows,
  buildSplitRowsFromBeforeAfter,
  buildSplitRowsFromUnified,
} from './diffDisplay'

describe('diffDisplay split view', () => {
  it('pairs removed and added lines from before/after', () => {
    const rows = buildSplitRowsFromBeforeAfter('old line\nkeep', 'new line\nkeep')
    const paired = rows.find(r => r.leftLine === 'old line' && r.rightLine === 'new line')
    expect(paired).toBeDefined()
    expect(paired?.leftKind).toBe('remove')
    expect(paired?.rightKind).toBe('add')
    const context = rows.find(r => r.leftLine === 'keep' && r.rightLine === 'keep')
    expect(context?.leftKind).toBe('context')
  })

  it('shows context on both columns', () => {
    const rows = buildSplitRowsFromBeforeAfter('alpha\nbeta', 'alpha\nbeta')
    expect(rows.every(r => r.leftKind === 'context' && r.rightKind === 'context')).toBe(true)
  })

  it('parses unified diff into split rows', () => {
    const rows = buildSplitRowsFromUnified(
      '--- app/Foo.php\n+++ app/Foo.php\n-old\n+new\n context',
    )
    const change = rows.find(r => r.leftLine === 'old' && r.rightLine === 'new')
    expect(change).toBeDefined()
    expect(rows.some(r => r.leftLine === 'context' && r.rightLine === 'context')).toBe(true)
  })

  it('buildSplitDiffRows prefers before/after over diff string', () => {
    const rows = buildSplitDiffRows({
      path: 'x.txt',
      change_type: 'modified',
      before: 'a',
      after: 'b',
      diff: '--- x\n+++ x\n-a\n+b',
    })
    expect(rows.some(r => r.leftLine === 'a' && r.rightLine === 'b')).toBe(true)
  })
})
