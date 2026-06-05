<script setup lang="ts">
import type { SimulationLinkDatum, SimulationNodeDatum } from 'd3'
import type { WorkspaceGraphEdge, WorkspaceGraphNode } from '~/types/api'
import { nodeFill, type GraphColorMode } from '~/composables/useGraphColors'
import { nodeRadius } from '~/composables/useD3Graph'

type SimNode = WorkspaceGraphNode & SimulationNodeDatum
type SimLink = SimulationLinkDatum<SimNode> & { kind: string }

const props = defineProps<{
  nodes: WorkspaceGraphNode[]
  edges: WorkspaceGraphEdge[]
  colorMode: GraphColorMode
  selectedId?: string | null
}>()

const emit = defineEmits<{
  select: [node: WorkspaceGraphNode | null]
}>()

const containerRef = ref<HTMLDivElement | null>(null)
const ready = ref(false)

let simulation: ReturnType<typeof import('d3')['forceSimulation']> | null = null
let svgEl: SVGSVGElement | null = null
let gEl: SVGGElement | null = null
let zoomBehavior: ReturnType<typeof import('d3')['zoom']> | null = null

async function render() {
  if (!import.meta.client || !containerRef.value) return

  const d3 = await import('d3')
  const width = containerRef.value.clientWidth || 800
  const height = containerRef.value.clientHeight || 600

  d3.select(containerRef.value).selectAll('svg').remove()

  const nodes: SimNode[] = props.nodes.map(n => ({ ...n }))
  const links: SimLink[] = props.edges.map(e => ({
    source: e.source,
    target: e.target,
    kind: e.kind,
  })) as SimLink[]

  svgEl = d3.select(containerRef.value)
    .append('svg')
    .attr('width', width)
    .attr('height', height)
    .attr('class', 'cursor-grab active:cursor-grabbing')
    .node()

  if (!svgEl) return

  gEl = d3.select(svgEl).append('g').node()

  zoomBehavior = d3.zoom<SVGSVGElement, unknown>()
    .scaleExtent([0.3, 3])
    .on('zoom', (event) => {
      if (gEl) d3.select(gEl).attr('transform', event.transform)
    })

  d3.select(svgEl).call(zoomBehavior)

  simulation = d3.forceSimulation(nodes)
    .force('link', d3.forceLink<SimNode, SimLink>(links)
      .id(d => d.id)
      .distance(d => (d.kind === 'overlap' ? 80 : 60))
      .strength(d => (d.kind === 'overlap' ? 0.05 : 0.4)))
    .force('charge', d3.forceManyBody().strength(-180))
    .force('center', d3.forceCenter(width / 2, height / 2))
    .force('collision', d3.forceCollide<SimNode>().radius(d => nodeRadius(d) + 4))

  const link = d3.select(gEl)
    .append('g')
    .attr('class', 'links')
    .selectAll('line')
    .data(links)
    .join('line')
    .attr('stroke', d => (d.kind === 'overlap' ? '#52525b' : '#4f8cff'))
    .attr('stroke-opacity', d => (d.kind === 'overlap' ? 0.35 : 0.65))
    .attr('stroke-width', d => (d.kind === 'overlap' ? 1 : 1.5))

  const nodeG = d3.select(gEl)
    .append('g')
    .attr('class', 'nodes')
    .selectAll('g')
    .data(nodes)
    .join('g')
    .attr('cursor', 'pointer')
    .call(d3.drag<SVGGElement, SimNode>()
      .on('start', (event, d) => {
        if (!event.active && simulation) simulation.alphaTarget(0.3).restart()
        d.fx = d.x
        d.fy = d.y
      })
      .on('drag', (event, d) => {
        d.fx = event.x
        d.fy = event.y
      })
      .on('end', (event, d) => {
        if (!event.active && simulation) simulation.alphaTarget(0)
        d.fx = null
        d.fy = null
      }))

  nodeG.append('circle')
    .attr('r', d => nodeRadius(d))
    .attr('fill', d => nodeFill(props.colorMode, d))
    .attr('stroke', d => (props.selectedId === d.id ? '#fafafa' : d.is_marquee ? 'rgba(255,255,255,0.85)' : 'transparent'))
    .attr('stroke-width', d => (props.selectedId === d.id ? 3 : d.is_marquee ? 2 : 0))
    .on('click', (_event, d) => emit('select', d))

  nodeG.append('title')
    .text(d => `${d.id}\n${d.depth} · ${d.category}\n${d.total_lines ?? 0} lines`)

  nodeG.append('text')
    .attr('dx', d => nodeRadius(d) + 4)
    .attr('dy', '0.35em')
    .attr('fill', '#a1a1aa')
    .attr('font-size', 10)
    .attr('pointer-events', 'none')
    .text(d => (d.label.length > 22 ? `${d.label.slice(0, 21)}…` : d.label))

  simulation.on('tick', () => {
    link
      .attr('x1', d => (d.source as SimNode).x ?? 0)
      .attr('y1', d => (d.source as SimNode).y ?? 0)
      .attr('x2', d => (d.target as SimNode).x ?? 0)
      .attr('y2', d => (d.target as SimNode).y ?? 0)
    nodeG.attr('transform', d => `translate(${d.x ?? 0},${d.y ?? 0})`)
  })

  ready.value = true
}

function fitView() {
  if (!import.meta.client || !svgEl || !gEl || !containerRef.value) return
  import('d3').then((d3) => {
    const width = containerRef.value!.clientWidth
    const height = containerRef.value!.clientHeight
    const bounds = (gEl as SVGGElement).getBBox()
    const fullWidth = bounds.width || width
    const fullHeight = bounds.height || height
    const midX = bounds.x + fullWidth / 2
    const midY = bounds.y + fullHeight / 2
    const scale = 0.85 / Math.max(fullWidth / width, fullHeight / height, 1)
    const transform = d3.zoomIdentity
      .translate(width / 2, height / 2)
      .scale(scale)
      .translate(-midX, -midY)
    d3.select(svgEl).transition().duration(400).call(zoomBehavior!.transform, transform)
  })
}

function destroy() {
  simulation?.stop()
  simulation = null
  if (containerRef.value) {
    import('d3').then(d3 => d3.select(containerRef.value).selectAll('svg').remove())
  }
}

watch(
  () => [props.nodes, props.edges, props.colorMode, props.selectedId],
  () => { destroy(); render() },
  { deep: true },
)

onMounted(async () => {
  await nextTick()
  await render()
  window.addEventListener('resize', onResize)
})

function onResize() {
  destroy()
  render()
}

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  destroy()
})

defineExpose({ fitView })
</script>

<template>
  <div
    ref="containerRef"
    class="relative h-full w-full min-h-[480px] bg-zinc-950"
  >
    <div
      v-if="!ready && nodes.length === 0"
      class="absolute inset-0 flex items-center justify-center text-sm text-zinc-500"
    >
      No graph data
    </div>
  </div>
</template>
