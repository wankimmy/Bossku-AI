<script setup lang="ts">
const props = defineProps<{
  title: string
  contextEvents: Record<string, unknown>[]
}>()

function contextFromEvents(evts: Record<string, unknown>[]) {
  const lastPlanner = [...evts].reverse().find(e => String(e?.type || '') === 'planner_done')
  const router = [...evts].reverse().find(e => String(e?.type || '') === 'skill_router_done')
  return { planner: lastPlanner?.output ?? null, routerOutput: router?.output ?? router }
}

const snippet = computed(() => contextFromEvents(props.contextEvents))
</script>

<template>
  <aside class="space-y-4">
    <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
      {{ title }}
    </h2>
    <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
      <details class="group">
        <summary class="cursor-pointer border-b border-zinc-100 px-3 py-2 text-sm dark:border-zinc-800">
          Router / planner excerpt
        </summary>
        <div class="p-3 space-y-2">
          <p class="text-xs font-semibold uppercase text-zinc-500">
            Planner output
          </p>
          <pre class="overflow-auto whitespace-pre-wrap break-words text-xs">{{ snippet.planner }}</pre>
        </div>
      </details>
    </div>
  </aside>
</template>
