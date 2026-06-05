<script setup lang="ts">
import type { GraphNode, KnowledgeGraphResponse } from '~/types/api'
import { filterGraph, graphStats, toCyElements, type GraphFilter } from '~/composables/useGraphView'

const CytoscapeWrapper = defineAsyncComponent(() => import('~/components/graph/CytoscapeWrapper.vue'))

const props = withDefaults(defineProps<{
  title: string
  description: string
  data: KnowledgeGraphResponse | null | undefined
  pending?: boolean
  height?: string
  variant?: 'default' | 'brain'
  showRebuild?: boolean
  rebuildLabel?: string
  nodeTypesHint?: string[]
}>(), {
  height: 'min(72vh, 720px)',
  variant: 'default',
  showRebuild: false,
  rebuildLabel: 'Rebuild graph',
})

const emit = defineEmits<{
  rebuild: []
  nodeClick: [node: GraphNode | null]
}>()

const cyRef = ref<{ fitGraph: () => void; zoomIn: () => void; zoomOut: () => void } | null>(null)
const selectedNode = ref<GraphNode | null>(null)

const stats = computed(() => graphStats(props.data))

const allNodeTypes = computed(() => {
  if (props.nodeTypesHint?.length) return props.nodeTypesHint
  return stats.value.types
})

const filter = ref<GraphFilter>({ types: [], onlyConflicts: false })

watch(allNodeTypes, (types) => {
  if (filter.value.types.length === 0 && types.length) {
    filter.value = { ...filter.value, types: [...types] }
  }
}, { immediate: true })

const filtered = computed(() => filterGraph(props.data, filter.value))

const cyElements = computed(() => toCyElements(filtered.value.nodes, filtered.value.edges, {
  labelFn: (n) => {
    if (n.type === 'skill' && n.metadata?.quality_score !== undefined) {
      const pct = Math.round(Number(n.metadata.quality_score) * 100)
      return `${n.label} (${pct}%)`
    }
    return n.label
  },
}))

const isEmpty = computed(() => !props.pending && stats.value.nodes === 0)

function onNodeClick(node: unknown) {
  const d = node as Record<string, unknown>
  const raw = (props.data?.nodes ?? []).find(n => n.id === d.id) ?? null
  selectedNode.value = raw
  emit('nodeClick', raw)
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-zinc-100">
          {{ title }}
        </h1>
        <p class="mt-1 max-w-2xl text-sm text-zinc-400">
          {{ description }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800"
          @click="cyRef?.fitGraph()"
        >
          Fit view
        </button>
        <button
          type="button"
          class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800"
          @click="cyRef?.zoomIn()"
        >
          Zoom +
        </button>
        <button
          type="button"
          class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800"
          @click="cyRef?.zoomOut()"
        >
          Zoom −
        </button>
        <button
          v-if="showRebuild"
          type="button"
          class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-600 disabled:opacity-50"
          @click="emit('rebuild')"
        >
          {{ rebuildLabel }}
        </button>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs text-zinc-300">
        {{ stats.nodes }} nodes
      </span>
      <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs text-zinc-300">
        {{ stats.edges }} edges
      </span>
      <span
        v-if="stats.conflicts"
        class="rounded-full border border-red-900/60 bg-red-950/40 px-3 py-1 text-xs text-red-300"
      >
        {{ stats.conflicts }} conflicts
      </span>
      <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs text-zinc-500">
        Showing {{ filtered.nodes.length }} / {{ stats.nodes }}
      </span>
    </div>

    <div
      v-if="!isEmpty"
      class="flex flex-wrap items-center gap-4 rounded-xl border border-zinc-800 bg-zinc-900/50 px-4 py-3"
    >
      <GraphFilterBar :node-types="allNodeTypes" v-model="filter" />
      <div class="ml-auto">
        <GraphLegend :variant="variant" />
      </div>
    </div>

    <UiSkeleton v-if="pending" class="w-full" :style="{ height }" />

    <UiEmptyState
      v-else-if="isEmpty"
      title="Graph is empty"
      hint="Run a few agent tasks or click Rebuild graph to populate nodes from skills, memories, and runs."
    >
      <button
        v-if="showRebuild"
        type="button"
        class="mt-3 rounded-lg bg-indigo-700 px-4 py-2 text-sm text-white"
        @click="emit('rebuild')"
      >
        {{ rebuildLabel }}
      </button>
    </UiEmptyState>

    <div v-else class="flex gap-4">
      <div class="min-w-0 flex-1">
        <ClientOnly>
          <CytoscapeWrapper
            ref="cyRef"
            :elements="cyElements"
            :height="height"
            :variant="variant"
            @node-click="onNodeClick"
          />
        </ClientOnly>
      </div>
      <GraphNodeInspector :node="selectedNode" />
    </div>
  </div>
</template>
