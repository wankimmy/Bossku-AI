<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    source: string
    fallbackSteps?: string[]
  }>(),
  { fallbackSteps: () => [] },
)

const renderError = ref(false)
const renderedSvg = ref('')

let renderToken = 0

async function renderDiagram() {
  const source = props.source.trim()
  if (!source || !import.meta.client) {
    renderedSvg.value = ''
    renderError.value = false
    return
  }

  const token = ++renderToken
  renderError.value = false

  try {
    const mermaid = (await import('mermaid')).default
    mermaid.initialize({
      startOnLoad: false,
      theme: 'dark',
      securityLevel: 'strict',
      fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    })

    const id = `mermaid-${token}-${Date.now()}`
    const { svg } = await mermaid.render(id, source)
    if (token !== renderToken) return
    renderedSvg.value = svg
    renderError.value = false
  }
  catch {
    if (token !== renderToken) return
    renderedSvg.value = ''
    renderError.value = true
  }
}

onMounted(() => {
  void renderDiagram()
})

watch(
  () => [props.source, props.fallbackSteps.join('|')] as const,
  () => {
    void renderDiagram()
  },
)
</script>

<template>
  <div class="mermaid-wrap">
    <div
      v-if="renderedSvg && !renderError"
      class="overflow-x-auto rounded-md border border-zinc-800 bg-zinc-950/60 p-3"
      v-html="renderedSvg"
    />
    <div
      v-else-if="source.trim() && renderError"
      class="space-y-2"
    >
      <p class="text-xs text-amber-400/90">
        Could not render diagram — showing steps instead.
      </p>
      <ol
        v-if="fallbackSteps.length"
        class="list-decimal space-y-1 pl-5 text-sm text-zinc-300"
      >
        <li
          v-for="(step, idx) in fallbackSteps"
          :key="idx"
        >
          {{ step }}
        </li>
      </ol>
      <pre
        v-else
        class="overflow-x-auto rounded-md border border-zinc-800 bg-zinc-950/80 p-2 text-xs text-zinc-400"
      >{{ source }}</pre>
    </div>
    <ol
      v-else-if="fallbackSteps.length"
      class="list-decimal space-y-1 pl-5 text-sm text-zinc-300"
    >
      <li
        v-for="(step, idx) in fallbackSteps"
        :key="idx"
      >
        {{ step }}
      </li>
    </ol>
  </div>
</template>
