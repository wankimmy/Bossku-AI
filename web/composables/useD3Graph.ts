import type { WorkspaceGraphEdge, WorkspaceGraphNode } from '~/types/api'

export type SkillsEdgeFilter = {
  showCrossRef: boolean
  showOverlap: boolean
}

export function filterSkillsEdges(
  edges: WorkspaceGraphEdge[],
  filter: SkillsEdgeFilter,
): WorkspaceGraphEdge[] {
  return edges.filter((e) => {
    if (e.kind === 'cross_ref') return filter.showCrossRef
    if (e.kind === 'overlap') return filter.showOverlap
    return filter.showCrossRef || filter.showOverlap
  })
}

export function filterKnowledgeEdges(
  edges: WorkspaceGraphEdge[],
  enabledKinds: Set<string>,
): WorkspaceGraphEdge[] {
  if (enabledKinds.size === 0) return edges
  return edges.filter(e => enabledKinds.has(e.kind))
}

export function collectEdgeKinds(edges: WorkspaceGraphEdge[]): string[] {
  return [...new Set(edges.map(e => e.kind))].sort()
}

export function nodeRadius(node: WorkspaceGraphNode): number {
  if (node.id === 'cofounder') return 16
  if (node.is_marquee) return 12
  const tc = node.trigger_count ?? 0
  return 6 + Math.min(6, Math.sqrt(tc))
}
