import type { BrainData } from '~/types/api'

type RawBrain = {
  learning_events_by_status?: Record<string, number>
  skill_candidates_by_status?: Record<string, number>
  feedback_unprocessed_count?: number
  memory_confidence?: { avg?: number | null; min?: number | null; max?: number | null }
  conflict_count?: number
  knowledge_node_count?: number
  memory_count?: number
}

export function normalizeBrainData(raw: RawBrain | null | undefined): BrainData | null {
  if (!raw) return null

  const le = raw.learning_events_by_status ?? {}
  const sc = raw.skill_candidates_by_status ?? {}

  const sum = (o: Record<string, number>) => Object.values(o).reduce((a, b) => a + b, 0)

  return {
    learning_events: {
      pending: Number(le.pending ?? 0),
      accepted: Number(le.accepted ?? 0),
      rejected: Number(le.rejected ?? 0),
      total: sum(le as Record<string, number>),
    },
    skill_candidates: {
      pending: Number(sc.pending_review ?? sc.pending ?? 0),
      approved: Number(sc.approved ?? 0),
      rejected: Number(sc.rejected ?? 0),
      total: sum(sc as Record<string, number>),
    },
    unprocessed_feedback: raw.feedback_unprocessed_count ?? 0,
    knowledge_nodes: raw.knowledge_node_count ?? 0,
    conflicts: raw.conflict_count ?? 0,
    memory_count: raw.memory_count ?? 0,
    memory_confidence: raw.memory_confidence ?? undefined,
  }
}
