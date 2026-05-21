<script setup lang="ts">
interface CyElements {
  nodes: unknown[]
  edges: unknown[]
}

const props = withDefaults(defineProps<{
  elements: CyElements
  layout?: string
  height?: string
}>(), {
  layout: 'fcose',
  height: '600px',
})

const emit = defineEmits<{
  nodeClick: [node: unknown]
}>()

const containerRef = ref<HTMLDivElement | null>(null)
const isClient = ref(false)
let cy: unknown = null

const stylesheet = [
  { selector: 'node', style: { label: 'data(label)', 'font-size': 11, 'text-valign': 'bottom', 'text-halign': 'center', 'background-color': '#52525b', color: '#e4e4e7', 'text-outline-width': 1, 'text-outline-color': '#18181b' } },
  { selector: 'node[type="skill"]', style: { 'background-color': '#059669' } },
  { selector: 'node[type="run"]', style: { 'background-color': '#2563eb' } },
  { selector: 'node[type="memory"]', style: { 'background-color': '#7c3aed' } },
  { selector: 'node[?has_conflict]', style: { 'background-color': '#dc2626', 'border-color': '#fca5a5', 'border-width': 2 } },
  { selector: 'edge', style: { width: 1, 'line-color': '#3f3f46', 'target-arrow-color': '#3f3f46', 'target-arrow-shape': 'triangle', 'curve-style': 'bezier', label: 'data(relation)', 'font-size': 9, color: '#71717a' } },
  { selector: 'edge[?is_conflict]', style: { 'line-color': '#dc2626', 'target-arrow-color': '#dc2626' } },
]

async function initCytoscape() {
  if (!import.meta.client || !containerRef.value) return

  const [cytoscapeModule, fcoseModule] = await Promise.all([
    import('cytoscape'),
    import('cytoscape-fcose'),
  ])

  const cytoscape = cytoscapeModule.default
  const fcose = fcoseModule.default

  try { cytoscape.use(fcose) }
  catch { /* already registered */ }

  if (cy) {
    ;(cy as { destroy: () => void }).destroy()
    cy = null
  }

  cy = cytoscape({
    container: containerRef.value,
    elements: {
      nodes: props.elements.nodes,
      edges: props.elements.edges,
    },
    style: stylesheet,
    layout: { name: props.layout ?? 'fcose' },
    userZoomingEnabled: true,
    userPanningEnabled: true,
  })

  ;(cy as { on: (event: string, selector: string, fn: (evt: unknown) => void) => void }).on('tap', 'node', (evt: unknown) => {
    const target = (evt as { target: { data: () => unknown } }).target
    emit('nodeClick', target.data())
  })
}

watch(() => props.elements, () => {
  initCytoscape()
}, { deep: true })

onMounted(async () => {
  isClient.value = true
  await nextTick()
  initCytoscape()
})

onUnmounted(() => {
  if (cy) {
    ;(cy as { destroy: () => void }).destroy()
    cy = null
  }
})
</script>

<template>
  <div class="w-full overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950">
    <div v-if="isClient" ref="containerRef" :style="{ height: height ?? '600px' }" />
    <div
      v-else
      class="flex items-center justify-center text-sm text-zinc-500"
      :style="{ height: height ?? '600px' }"
    >
      Graph loading…
    </div>
  </div>
</template>
