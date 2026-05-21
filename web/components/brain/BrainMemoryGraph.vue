<script setup lang="ts">
import type { GraphNode, KnowledgeGraphResponse } from '~/types/api'
import { filterGraph, graphStats, toCyElements } from '~/composables/useGraphView'

const CytoscapeWrapper = defineAsyncComponent(() => import('~/components/graph/CytoscapeWrapper.vue'))

const base = useApiBase()
const { data, pending, refresh } = await useFetch<KnowledgeGraphResponse>(
  `${base}/api/brain/memory-graph`,
  { server: false },
)

const selected = ref<GraphNode | null>(null)
const filter = ref({ types: ['core', 'memory', 'memory_type'], onlyConflicts: false })

const filtered = computed(() => filterGraph(data.value, filter.value))
const cyElements = computed(() => toCyElements(filtered.value.nodes, filtered.value.edges))
const stats = computed(() => graphStats(data.value))

function onNodeClick(node: unknown) {
  const d = node as Record<string, unknown>
  selected.value = (data.value?.nodes ?? []).find(n => n.id === d.id) ?? null
}
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm text-violet-200/70">
      Each memory is a neuron linked to the BosskuAI core and grouped by type. Larger nodes mean higher confidence.
    </p>

    <div class="flex flex-wrap gap-2 text-xs">
      <span class="rounded-full border border-violet-800/60 bg-violet-950/50 px-3 py-1 text-violet-200">
        {{ stats.nodes }} neurons
      </span>
      <span class="rounded-full border border-violet-800/60 bg-violet-950/50 px-3 py-1 text-violet-200">
        {{ stats.edges }} synapses
      </span>
    </div>

    <UiSkeleton v-if="pending" class="h-[min(65vh,640px)] w-full" />

    <UiEmptyState
      v-else-if="stats.nodes <= 1"
      title="No memories yet"
      hint="Run agent tasks with memory enabled. Accepted learnings from the Learning Inbox also feed the brain."
    />

    <div v-else class="flex gap-4">
      <div class="min-w-0 flex-1">
        <ClientOnly>
          <CytoscapeWrapper
            :elements="cyElements"
            variant="brain"
            height="min(65vh, 640px)"
            @node-click="onNodeClick"
          />
        </ClientOnly>
      </div>
      <GraphNodeInspector :node="selected" />
    </div>

    <div class="flex justify-end">
      <button
        type="button"
        class="text-xs text-violet-400 hover:text-violet-200"
        @click="refresh()"
      >
        Refresh memory graph
      </button>
    </div>
  </div>
</template>
