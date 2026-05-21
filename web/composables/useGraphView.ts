import type { GraphEdge, GraphNode, KnowledgeGraphResponse } from '~/types/api'

export type GraphFilter = {
  types: string[]
  onlyConflicts: boolean
}

export function toCyElements(
  nodes: GraphNode[],
  edges: GraphEdge[],
  options?: { labelFn?: (n: GraphNode) => string },
) {
  return {
    nodes: nodes.map(n => ({
      data: {
        id: n.id,
        label: options?.labelFn ? options.labelFn(n) : n.label,
        type: n.type ?? 'unknown',
        confidence: n.confidence,
        has_conflict: n.has_conflict,
      },
    })),
    edges: edges.map(e => ({
      data: {
        id: e.id,
        source: e.source_id,
        target: e.target_id,
        relation: e.relation,
        is_conflict: e.is_conflict,
        weight: e.weight,
      },
    })),
  }
}

export function filterGraph(
  data: KnowledgeGraphResponse | null | undefined,
  filter: GraphFilter,
) {
  const allNodes = data?.nodes ?? []
  const types = filter.types.length
    ? filter.types
    : [...new Set(allNodes.map(n => n.type ?? 'unknown'))]

  const nodes = allNodes.filter((n) => {
    if (!types.includes(n.type ?? 'unknown')) return false
    if (filter.onlyConflicts && !n.has_conflict) return false
    return true
  })

  const ids = new Set(nodes.map(n => n.id))
  const edges = (data?.edges ?? []).filter(
    e => ids.has(e.source_id) && ids.has(e.target_id),
  )

  return { nodes, edges }
}

export function graphStats(data: KnowledgeGraphResponse | null | undefined) {
  const nodes = data?.nodes ?? []
  const edges = data?.edges ?? []
  return {
    nodes: nodes.length,
    edges: edges.length,
    conflicts: nodes.filter(n => n.has_conflict).length,
    types: [...new Set(nodes.map(n => n.type ?? 'unknown'))],
  }
}
