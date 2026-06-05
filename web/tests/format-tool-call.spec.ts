import { describe, expect, it } from 'vitest'
import { formatToolCallSummary, formatToolCallTitle } from '../utils/formatToolCall'

describe('formatToolCall', () => {
  it('formats file read with path', () => {
    const summary = formatToolCallSummary({
      type: 'tool_call',
      tool: 'file_read_safe',
      payload: { path: 'routes/api.php' },
      status: 'ok',
    })
    expect(formatToolCallTitle({ tool: 'file_read_safe' })).toBe('Read file')
    expect(summary).toContain('routes/api.php')
  })

  it('formats file search with query', () => {
    const summary = formatToolCallSummary({
      type: 'tool_call',
      tool: 'file_search',
      payload: { q: 'HealthController', glob: '*.php' },
    })
    expect(summary).toContain('HealthController')
    expect(summary).toContain('*.php')
  })

  it('uses backend summary when present', () => {
    expect(formatToolCallSummary({
      type: 'tool_call',
      tool: 'file_read_safe',
      summary: 'Reading file: app/Models/User.php',
    })).toBe('Reading file: app/Models/User.php')
  })
})
