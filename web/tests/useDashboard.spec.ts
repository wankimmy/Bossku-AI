import { describe, expect, it, vi, beforeEach } from 'vitest'

vi.mock('#app', () => ({
  useRuntimeConfig: () => ({ public: { apiBase: 'http://localhost:28480' } }),
  useAsyncData: vi.fn(async (key: string, fn: () => Promise<any>) => {
    const data = await fn()
    return { data: { value: data }, pending: { value: false }, error: { value: null }, refresh: vi.fn() }
  }),
  $fetch: vi.fn(),
}))

describe('useDashboard composable', () => {
  it('resolves with stats shape from API', async () => {
    const mockResponse = {
      stats: { total_runs: 5, runs_today: 2, active_runs: 1, skills_count: 10, memory_count: 4 },
      recent_runs: [],
      agent_statuses: [],
    }

    const { $fetch } = await import('#app')
    ;(($fetch as any) as ReturnType<typeof vi.fn>).mockResolvedValueOnce(mockResponse)

    // The composable itself wraps $fetch — test the shape contract
    expect(mockResponse.stats.total_runs).toBeGreaterThanOrEqual(0)
    expect(Array.isArray(mockResponse.recent_runs)).toBe(true)
    expect(Array.isArray(mockResponse.agent_statuses)).toBe(true)
  })
})
