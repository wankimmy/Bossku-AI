import type { UsageEvent, UsageSummary } from '~/types/api'
import { paginatedItems } from '~/utils/paginatedResponse'

type ApiUsageSummary = {
  total_input_tokens?: number
  total_output_tokens?: number
  total_cost_usd?: number
  breakdown?: Array<{
    provider?: string
    model?: string
    input_tokens?: number
    output_tokens?: number
    cost_usd?: number
  }>
}

type ApiUsageEvent = Record<string, unknown> & {
  input_tokens?: number
  output_tokens?: number
  cost_usd?: number
  prompt_tokens?: number
  completion_tokens?: number
  cost?: number
}

export function normalizeUsageSummary(raw: ApiUsageSummary | null | undefined): UsageSummary | null {
  if (!raw) return null

  const input = Number(raw.total_input_tokens ?? 0)
  const output = Number(raw.total_output_tokens ?? 0)
  const byProvider: Record<string, { tokens: number; cost: number }> = {}

  for (const row of raw.breakdown ?? []) {
    const provider = String(row.provider ?? 'unknown')
    const tokens = Number(row.input_tokens ?? 0) + Number(row.output_tokens ?? 0)
    const cost = Number(row.cost_usd ?? 0)
    if (!byProvider[provider]) {
      byProvider[provider] = { tokens: 0, cost: 0 }
    }
    byProvider[provider].tokens += tokens
    byProvider[provider].cost += cost
  }

  return {
    total_tokens: input + output,
    total_cost: Number(raw.total_cost_usd ?? 0),
    by_provider: Object.keys(byProvider).length > 0 ? byProvider : undefined,
  }
}

export function normalizeUsageEvent(raw: ApiUsageEvent): UsageEvent {
  const input = Number(raw.input_tokens ?? raw.prompt_tokens ?? 0)
  const output = Number(raw.output_tokens ?? raw.completion_tokens ?? 0)
  const costRaw = raw.cost_usd ?? raw.cost

  return {
    id: String(raw.id ?? ''),
    run_id: raw.run_id != null ? String(raw.run_id) : undefined,
    provider_id: raw.provider != null ? String(raw.provider) : raw.provider_id != null ? String(raw.provider_id) : undefined,
    model: raw.model != null ? String(raw.model) : undefined,
    prompt_tokens: input,
    completion_tokens: output,
    total_tokens: input + output,
    cost: costRaw != null ? Number(costRaw) : undefined,
    created_at: raw.created_at != null ? String(raw.created_at) : undefined,
  }
}

export function normalizeUsageEvents(
  payload: UsageEvent[] | { data?: UsageEvent[] } | null | undefined,
): UsageEvent[] {
  return paginatedItems(payload as UsageEvent[] | { data: UsageEvent[] } | null).map(row =>
    normalizeUsageEvent(row as ApiUsageEvent),
  )
}
