import { describe, expect, it } from 'vitest'
import { normalizeUsageEvent, normalizeUsageEvents, normalizeUsageSummary } from '../utils/usageNormalize'

describe('usageNormalize', () => {
  it('maps API summary fields to UI shape', () => {
    const summary = normalizeUsageSummary({
      total_input_tokens: 100,
      total_output_tokens: 50,
      total_cost_usd: 0.0123,
      breakdown: [
        { provider: 'ollama', input_tokens: 80, output_tokens: 40, cost_usd: 0.01 },
        { provider: 'openai', input_tokens: 20, output_tokens: 10, cost_usd: 0.0023 },
      ],
    })

    expect(summary?.total_tokens).toBe(150)
    expect(summary?.total_cost).toBeCloseTo(0.0123)
    expect(summary?.by_provider?.ollama.tokens).toBe(120)
  })

  it('maps API event token and cost fields', () => {
    const ev = normalizeUsageEvent({
      id: 'u1',
      model: 'llama3',
      input_tokens: 10,
      output_tokens: 5,
      cost_usd: 0.001,
    })

    expect(ev.prompt_tokens).toBe(10)
    expect(ev.completion_tokens).toBe(5)
    expect(ev.total_tokens).toBe(15)
    expect(ev.cost).toBeCloseTo(0.001)
  })

  it('unwraps paginated usage events', () => {
    const events = normalizeUsageEvents({
      data: [{ id: 'u1', input_tokens: 1, output_tokens: 2, cost_usd: 0 }],
    })
    expect(events).toHaveLength(1)
    expect(events[0].total_tokens).toBe(3)
  })
})
