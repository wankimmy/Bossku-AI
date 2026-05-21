<script setup lang="ts">
import type { GraphNode, GraphEdge, KnowledgeGraphResponse } from '~/types/api'

definePageMeta({ layout: 'default' })

const CytoscapeWrapper = defineAsyncComponent(() => import('~/components/graph/CytoscapeWrapper.vue'))

const api = useApi()
const base = useApiBase()

const { data: graphData, pending, refresh } = await useFetch<KnowledgeGraphResponse>(`${base}/api/knowledge-graph`, { server: false })

const rebuilding = ref(false)
const selectedNode = ref<GraphNode | null>(null)

const allNodeTypes = computed(() => {
  const types = new Set((graphData.value?.nodes ?? []).map(n => n.type ?? 'unknown'))
  return [...types]
})

const filter = ref<{ types: string[]; onlyConflicts: boolean }>({ types: [], onlyConflicts: false })

watch(allNodeTypes, (types) => {
  filter.value.types = [...types]
}, { immediate: true })

const filteredNodes = computed(() => {
  const nodes = graphData.value?.nodes ?? []
  return nodes.filter((n) => {
    if (filter.value.types.length && !filter.value.types.includes(n.type ?? 'unknown')) return false
    if (filter.value.onlyConflicts && !n.has_conflict) return false
    return true
  })
})

const filteredNodeIds = computed(() => new Set(filteredNodes.value.map(n => n.id)))

const filteredEdges = computed(() => {
  const edges = graphData.value?.edges ?? []
  return edges.filter(e => filteredNodeIds.value.has(e.source_id) && filteredNodeIds.value.has(e.target_id))
})

const cyElements = computed(() => ({
  nodes: filteredNodes.value.map(n => ({
    data: {
      id: n.id,
      label: n.label,
      type: n.type,
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

async function rebuildGraph() {
  rebuilding.value = true
  try {
    await api.post('/knowledge-graph/rebuild')
    await refresh()
  }
  finally {
    rebuilding.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold">Knowledge Graph</h1>
        <p class="text-sm text-zinc-400">Visual map of memories, skills, and runs.</p>
      </div>
      <button
        type="button"
        :disabled="rebuilding"
        class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-600 disabled:opacity-50 transition"
        @click="rebuildGraph"
      >
        {{ rebuilding ? 'Rebuilding…' : 'Rebuild Graph' }}
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-zinc-800 bg-zinc-900/50 px-4 py-3">
      <GraphFilterBar :node-types="allNodeTypes" v-model="filter" />
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
