<script setup lang="ts">
import type { GraphNode, KnowledgeGraphResponse } from '~/types/api'

definePageMeta({ layout: 'default' })

const CytoscapeWrapper = defineAsyncComponent(() => import('~/components/graph/CytoscapeWrapper.vue'))

const base = useApiBase()

const { data: graphData, pending } = await useFetch<KnowledgeGraphResponse>(`${base}/api/skills-graph`, { server: false })

const selectedNode = ref<GraphNode | null>(null)
const filter = ref<{ types: string[]; onlyConflicts: boolean }>({ types: ['skill'], onlyConflicts: false })

const filteredNodes = computed(() => {
  return (graphData.value?.nodes ?? []).filter((n) => {
    if (filter.value.onlyConflicts && !n.has_conflict) return false
    return true
  })
})

const filteredNodeIds = computed(() => new Set(filteredNodes.value.map(n => n.id)))

const filteredEdges = computed(() => {
  return (graphData.value?.edges ?? []).filter(e =>
    filteredNodeIds.value.has(e.source_id) && filteredNodeIds.value.has(e.target_id),
  )
})

const cyElements = computed(() => ({
  nodes: filteredNodes.value.map(n => ({
    data: {
      id: n.id,
      // Show quality_score in label if available
      label: n.metadata?.quality_score !== undefined
        ? `${n.label} (${Math.round((n.metadata.quality_score as number) * 100)}%)`
        : n.label,
      type: n.type ?? 'skill',
      confidence: n.confidence,
      has_conflict: n.has_conflict,
    },
  })),
  edges: filteredEdges.value.map(e => ({
    data: {
      id: e.id,
      source: e.source_id,
      target: e.target_id,
      relation: e.relation,
      is_conflict: e.is_conflict,
    },
  })),
}))

function onNodeClick(node: unknown) {
  const d = node as Record<string, unknown>
  const raw = (graphData.value?.nodes ?? []).find(n => n.id === d.id)
  selectedNode.value = raw ?? null
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-xl font-semibold">Skills Graph</h1>
      <p class="text-sm text-zinc-400">Skill relationships with quality score overlays.</p>
    </div>

    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-zinc-800 bg-zinc-900/50 px-4 py-3">
      <GraphFilterBar :node-types="['skill']" v-model="filter" />
      <div class="ml-auto"><GraphLegend /></div>
    </div>

    <UiSkeleton v-if="pending" class="h-96 w-full" />

    <div v-else class="flex gap-4">
      <div class="min-w-0 flex-1">
        <ClientOnly>
          <CytoscapeWrapper :elements="cyElements" layout="fcose" height="600px" @node-click="onNodeClick" />
        </ClientOnly>
      </div>
      <GraphNodeInspector :node="selectedNode" />
    </div>
  </div>
</template>
