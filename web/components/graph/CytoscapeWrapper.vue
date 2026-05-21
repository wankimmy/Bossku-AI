<script setup lang="ts">
interface CyElements {
  nodes: unknown[]
  edges: unknown[]
}

const props = withDefaults(defineProps<{
  elements: CyElements
  layout?: string
  height?: string
  variant?: 'default' | 'brain'
}>(), {
  layout: 'fcose',
  height: '600px',
  variant: 'default',
})

const emit = defineEmits<{
  nodeClick: [node: unknown]
}>()

const containerRef = ref<HTMLDivElement | null>(null)
const isClient = ref(false)
let cy: { destroy: () => void; fit: (p?: unknown, n?: number) => void; zoom: (z?: number) => number; center: () => void; layout: (o: unknown) => { run: () => void }; elements: () => unknown } | null = null

const defaultStyles = [
  { selector: 'node', style: { label: 'data(label)', 'font-size': 10, 'text-wrap': 'wrap', 'text-max-width': 90, 'text-valign': 'center', 'text-halign': 'center', color: '#fafafa', 'text-outline-width': 2, 'text-outline-color': '#09090b', width: 28, height: 28 } },
  { selector: 'node[type="skill"]', style: { 'background-color': '#10b981', width: 34, height: 34 } },
  { selector: 'node[type="run"]', style: { 'background-color': '#3b82f6' } },
  { selector: 'node[type="memory"]', style: { 'background-color': '#8b5cf6', width: 'mapData(confidence, 0, 1, 22, 44)', height: 'mapData(confidence, 0, 1, 22, 44)' } },
  { selector: 'node[type="memory_type"]', style: { 'background-color': '#6366f1', shape: 'round-rectangle', width: 40, height: 24, 'font-size': 9 } },
  { selector: 'node[type="core"]', style: { 'background-color': '#ec4899', width: 56, height: 56, 'font-size': 11, 'font-weight': 'bold' } },
  { selector: 'node[type="agent"]', style: { 'background-color': '#06b6d4' } },
  { selector: 'node[type="rule"]', style: { 'background-color': '#f59e0b' } },
  { selector: 'node[type="playbook"]', style: { 'background-color': '#14b8a6' } },
  { selector: 'node[type="file"]', style: { 'background-color': '#64748b' } },
  { selector: 'node[?has_conflict]', style: { 'background-color': '#ef4444', 'border-color': '#fecaca', 'border-width': 3 } },
  { selector: 'edge', style: { width: 1.5, 'line-color': '#52525b', 'target-arrow-color': '#52525b', 'target-arrow-shape': 'triangle', 'curve-style': 'bezier', label: 'data(relation)', 'font-size': 8, color: '#a1a1aa', 'text-background-opacity': 0.85, 'text-background-color': '#18181b', 'text-background-padding': 2 } },
  { selector: 'edge[?is_conflict]', style: { 'line-color': '#ef4444', 'target-arrow-color': '#ef4444', width: 2.5 } },
]

const brainStyles = [
  { selector: 'node', style: { label: 'data(label)', 'font-size': 9, 'text-wrap': 'wrap', 'text-max-width': 72, color: '#f5d0fe', 'text-outline-width': 2, 'text-outline-color': '#3b0764' } },
  { selector: 'node[type="core"]', style: { 'background-color': '#ec4899', 'background-gradient-stop-colors': '#ec4899 #a855f7', 'background-gradient-direction': 'to-bottom-right', width: 72, height: 72, 'font-size': 11 } },
  { selector: 'node[type="memory"]', style: { 'background-color': '#a855f7', width: 'mapData(confidence, 0, 1, 18, 40)', height: 'mapData(confidence, 0, 1, 18, 40)' } },
  { selector: 'node[type="memory_type"]', style: { 'background-color': '#6366f1', shape: 'ellipse' } },
  { selector: 'edge', style: { width: 1.2, 'line-color': '#7c3aed88', 'target-arrow-color': '#c084fc', 'target-arrow-shape': 'vee', 'curve-style': 'bezier', opacity: 0.75 } },
]

const layoutOptions = computed(() => {
  if (props.variant === 'brain') {
    return {
      name: 'fcose',
      animate: true,
      randomize: true,
      nodeRepulsion: 4500,
      idealEdgeLength: 90,
      gravity: 0.25,
    }
  }

  return {
    name: 'fcose',
    animate: true,
    randomize: false,
    nodeRepulsion: 8000,
    idealEdgeLength: 120,
  }
})

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
    cy.destroy()
    cy = null
  }

  cy = cytoscape({
    container: containerRef.value,
    elements: {
      nodes: [...props.elements.nodes],
      edges: [...props.elements.edges],
    },
    style: props.variant === 'brain' ? brainStyles : defaultStyles,
    layout: layoutOptions.value,
    userZoomingEnabled: true,
    userPanningEnabled: true,
    boxSelectionEnabled: false,
    minZoom: 0.15,
    maxZoom: 3,
  }) as typeof cy

  cy.on('tap', 'node', (evt: { target: { data: () => unknown } }) => {
    emit('nodeClick', evt.target.data())
  })

  cy.fit(undefined, 48)
}

function fitGraph() {
  cy?.fit(undefined, 48)
}

function zoomIn() {
  if (!cy) return
  cy.zoom(cy.zoom() * 1.2)
  cy.center()
}

function zoomOut() {
  if (!cy) return
  cy.zoom(cy.zoom() * 0.8)
  cy.center()
}

watch(() => [props.elements, props.variant], () => {
  initCytoscape()
}, { deep: true })

onMounted(async () => {
  isClient.value = true
  await nextTick()
  await initCytoscape()
})

onUnmounted(() => {
  cy?.destroy()
  cy = null
})

defineExpose({ fitGraph, zoomIn, zoomOut })
</script>

<template>
  <div class="relative w-full overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950">
    <div
      v-if="variant === 'brain'"
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(168,85,247,0.12)_0%,transparent_65%)]"
      aria-hidden="true"
    />
    <div v-if="isClient" ref="containerRef" class="relative z-10 w-full" :style="{ height: height ?? '600px' }" />
    <div
      v-else
      class="flex items-center justify-center text-sm text-zinc-500"
      :style="{ height: height ?? '600px' }"
    >
      Loading graph…
    </div>
    <slot name="overlay" />
  </div>
</template>
