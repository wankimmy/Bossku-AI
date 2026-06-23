import { afterEach, describe, expect, it, vi } from 'vitest'
import { useDashboard } from '../composables/useDashboard'

describe('useDashboard composable', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads dashboard data through the dashboard async-data key', async () => {
    const mockResponse = {
      stats: { total_runs: 5, runs_today: 2, active_runs: 1, skills_count: 10, memory_count: 4 },
      recent_runs: [],
      agent_statuses: [],
    }
    const get = vi.fn().mockResolvedValue(mockResponse)
    const useAsyncData = vi.fn((key: string, handler: () => Promise<unknown>) => ({ key, handler }))

    vi.stubGlobal('useApi', () => ({ get }))
    vi.stubGlobal('useAsyncData', useAsyncData)

    const result = useDashboard() as { key: string; handler: () => Promise<unknown> }

    expect(useAsyncData).toHaveBeenCalledWith('dashboard', expect.any(Function))
    expect(result.key).toBe('dashboard')
    await expect(result.handler()).resolves.toEqual(mockResponse)
    expect(get).toHaveBeenCalledWith('/dashboard')
  })
})
