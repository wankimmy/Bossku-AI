<script setup lang="ts">
import type { WorkspaceGraphNode, WorkspaceGraphResponse } from '~/types/api'
import type { GraphColorMode } from '~/composables/useGraphColors'
import { collectEdgeKinds, filterKnowledgeEdges, filterSkillsEdges } from '~/composables/useD3Graph'

const D3ForceGraph = defineAsyncComponent(() => import('~/components/graph/D3ForceGraph.vue'))

const props = withDefaults(defineProps<{
  title: string
  description: string
  variant: 'skills' | 'knowledge'
  data: WorkspaceGraphResponse | null | undefined
  pending?: boolean
  showRebuild?: boolean
  rebuildLabel?: string
  bootstrapLoading?: boolean
}>(), {
  showRebuild: false,
  rebuildLabel: 'Rebuild graph',
  bootstrapLoading: false,
})

const emit = defineEmits<{
  rebuild: []
  bootstrapSkills: []
}>()

const graphRef = ref<{ fitView: () => void } | null>(null)
const selectedNode = ref<WorkspaceGraphNode | null>(null)

const colorMode = ref<GraphColorMode>('category')
const showCrossRef = ref(true)
const showOverlap = ref(true)

const edgeKinds = computed(() => collectEdgeKinds(props.data?.edges ?? []))
const enabledKinds = ref<string[]>([])

watch(edgeKinds, (kinds) => {
  if (enabledKinds.value.length === 0 && kinds.length) {
    enabledKinds.value = [...kinds]
  }
}, { immediate: true })

const filteredEdges = computed(() => {
  const edges = props.data?.edges ?? []
  if (props.variant === 'skills') {
    return filterSkillsEdges(edges, {
      showCrossRef: showCrossRef.value,
      showOverlap: showOverlap.value,
    })
  }
  return filterKnowledgeEdges(edges, new Set(enabledKinds.value))
})

const statusLine = computed(() => {
  const n = props.data?.node_count ?? props.data?.nodes?.length ?? 0
  const e = props.data?.edge_count ?? props.data?.edges?.length ?? 0
  const ver = props.data?.version ? `v${props.data.version}` : ''
  const source =
    props.variant === 'skills' && props.data?.skills_source === 'toolkit'
      ? ' · Bossku-AI toolkit'
      : ''
  return `${n} nodes · ${e} relations${ver ? ` · ${ver}` : ''}${source}`
})

const skillsToolkitNotice = computed(() => {
  if (props.variant !== 'skills' || props.data?.skills_source !== 'toolkit') return ''
  return 'Showing skills from the Bossku-AI toolkit repo because the active project has no skill-index.json.'
})

const isEmpty = computed(() => !props.pending && (props.data?.error || (props.data?.nodes?.length ?? 0) === 0))

const skillsIndexError = computed(() => {
  const err = props.data?.error ?? ''
  return props.variant === 'skills'
    && (err === 'skill-index.json missing' || err === 'skill-index.json invalid')
})

const canBootstrapSkills = computed(
  () => skillsIndexError.value && Boolean(props.data?.active_repo_root),
)

const needsActiveProject = computed(
  () => skillsIndexError.value && !props.data?.active_repo_root,
)

function onSelect(node: WorkspaceGraphNode | null) {
  selectedNode.value = node
}
</script>

<template>
  <div class="flex h-full min-h-0 w-full flex-col">
    <header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-800 bg-zinc-950 px-4 py-3">
      <div>
        <h1 class="text-lg font-semibold text-zinc-100">
          {{ title }}
        </h1>
        <p class="text-sm text-zinc-500">
          {{ description }}
        </p>
        <p v-if="skillsToolkitNotice" class="mt-1 text-xs text-amber-500/90">
          {{ skillsToolkitNotice }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs text-zinc-400">{{ statusLine }}</span>
        <button
          type="button"
          class="rounded-lg border border-zinc-700 px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800"
          @click="graphRef?.fitView()"
        >
          Fit view
        </button>
        <button
          v-if="showRebuild"
          type="button"
          class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-600"
          @click="emit('rebuild')"
        >
          {{ rebuildLabel }}
        </button>
      </div>
    </header>

    <UiSkeleton v-if="pending" class="flex-1 w-full min-h-[480px]" />

    <UiEmptyState
      v-else-if="isEmpty"
      class="flex-1 m-4"
      :title="data?.error || 'Graph is empty'"
      :hint="variant === 'skills'
        ? 'Register Bossku-AI at /project (host path to this repo) and activate it, or ensure BOSSKU_REPO_PATH (/repo in Docker) contains skill-index.json.'
        : 'Run agent tasks or click Rebuild graph to populate nodes from skills, memories, and runs.'"
    >
      <div class="mt-4 flex flex-col items-center gap-2 sm:flex-row sm:flex-wrap sm:justify-center">
        <button
          v-if="canBootstrapSkills"
          type="button"
          class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-600 disabled:opacity-50"
          :disabled="props.bootstrapLoading"
          @click="emit('bootstrapSkills')"
        >
          {{ props.bootstrapLoading ? 'Installing skills…' : 'Install BosskuAI skills in active project' }}
        </button>
        <NuxtLink
          v-if="needsActiveProject"
          to="/project"
          class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-800"
        >
          Open Project paths
        </NuxtLink>
        <NuxtLink
          v-else-if="canBootstrapSkills"
          to="/project"
          class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-400 hover:bg-zinc-800"
        >
          Project settings
        </NuxtLink>
        <button
          v-if="showRebuild"
          type="button"
          class="rounded-lg bg-indigo-700 px-4 py-2 text-sm text-white hover:bg-indigo-600"
          @click="emit('rebuild')"
        >
          {{ rebuildLabel }}
        </button>
      </div>
      <p v-if="canBootstrapSkills && data?.toolkit_repo_root" class="mt-3 max-w-md text-center text-xs text-zinc-500">
        Copies <code class="text-zinc-400">skill-index.json</code> and
        <code class="text-zinc-400">ai-assistant/skills</code> from the Bossku-AI toolkit
        into <code class="text-emerald-500/90">{{ data.active_repo_root }}</code>.
      </p>
    </UiEmptyState>

    <div v-else class="flex min-h-0 flex-1">
      <div class="relative min-w-0 flex-1">
        <ClientOnly>
          <D3ForceGraph
            ref="graphRef"
            :nodes="data?.nodes ?? []"
            :edges="filteredEdges"
            :color-mode="colorMode"
            :selected-id="selectedNode?.id"
            @select="onSelect"
          />
        </ClientOnly>

        <div class="pointer-events-none absolute left-3 top-3 z-10 flex flex-col gap-3">
          <GraphFloatingSettings
            :variant="variant"
            :color-mode="colorMode"
            :show-cross-ref="showCrossRef"
            :show-overlap="showOverlap"
            :edge-kinds="edgeKinds"
            :enabled-kinds="enabledKinds"
            @update:color-mode="colorMode = $event"
            @update:show-cross-ref="showCrossRef = $event"
            @update:show-overlap="showOverlap = $event"
            @update:enabled-kinds="enabledKinds = $event"
          />
        </div>

        <div class="pointer-events-none absolute bottom-3 left-3 z-10">
          <GraphFloatingLegend :color-mode="colorMode" />
        </div>
      </div>

      <GraphSkillDetailPanel v-if="variant === 'skills'" :node="selectedNode" />
      <GraphKnowledgeDetailPanel v-else :node="selectedNode" />
    </div>
  </div>
</template>
