import { describe, expect, it } from 'vitest'
import {
  buildDisplayDiff,
  diffLineClass,
  parseDiffLines,
} from '../utils/diffDisplay'

describe('diffDisplay', () => {
  it('parses add and remove lines', () => {
    const lines = parseDiffLines(`--- a.txt
+++ a.txt
-old
+new
 context`)
    expect(lines[0].kind).toBe('header')
    expect(lines.find(l => l.kind === 'remove')?.text).toBe('old')
    expect(lines.find(l => l.kind === 'add')?.text).toBe('new')
    expect(lines.find(l => l.kind === 'context')?.text).toBe('context')
  })

  it('synthesizes created file diff from after content', () => {
    const diff = buildDisplayDiff({
      path: 'src/New.vue',
      change_type: 'created',
      after: 'line one\nline two',
    })
    expect(diff).toContain('+++ src/New.vue')
    expect(diff).toContain('+line one')
    expect(diff).toContain('+line two')

    const lines = parseDiffLines(diff!)
    expect(lines.every(l => l.kind === 'add' || l.kind === 'header')).toBe(true)
  })

  it('synthesizes deleted file diff from before content', () => {
    const diff = buildDisplayDiff({
      path: 'src/Old.vue',
      change_type: 'deleted',
      before: 'gone',
    })
    expect(diff).toContain('-gone')
    expect(linesKind(diff!).remove).toBeGreaterThan(0)
  })

  it('uses existing diff when present', () => {
    const raw = '--- f\n+++ f\n+x'
    expect(buildDisplayDiff({ path: 'f', change_type: 'modified', diff: raw })).toBe(raw)
  })

  it('maps line kinds to tailwind classes', () => {
    expect(diffLineClass('add')).toContain('emerald')
    expect(diffLineClass('remove')).toContain('rose')
  })
})

function linesKind(diff: string) {
  const lines = parseDiffLines(diff)
  return {
    add: lines.filter(l => l.kind === 'add').length,
    remove: lines.filter(l => l.kind === 'remove').length,
  }
}
