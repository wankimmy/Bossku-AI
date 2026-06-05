import { describe, expect, it, vi, beforeEach } from 'vitest'
import { apiUrl } from '../composables/useApiBase'

describe('apiUrl', () => {
  beforeEach(() => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '' } }))
  })

  it('returns relative /api path when apiBase is empty', () => {
    expect(apiUrl('/runs/1/approvals')).toBe('/api/runs/1/approvals')
  })

  it('returns absolute URL when apiBase is set', () => {
    vi.stubGlobal('useRuntimeConfig', () => ({
      public: { apiBase: 'http://localhost:28480' },
    }))
    expect(apiUrl('/runs')).toBe('http://localhost:28480/api/runs')
  })
})
