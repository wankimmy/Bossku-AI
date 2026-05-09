<script setup lang="ts">
const props = defineProps<{
  title: string
  contextEvents: Record<string, unknown>[]
}>()

const artifacts = computed(() => useRunArtifacts(props.contextEvents))

const filesInspected = computed(() => artifacts.value.filesRead)
const memory = computed(() => props.contextEvents.flatMap((event) => {
  const direct = Array.isArray(event.memory_used) ? event.memory_used : []
  const nested = event.artifacts && typeof event.artifacts === 'object' && Array.isArray((event.artifacts as Record<string, unknown>).memory_used)
    ? (event.artifacts as Record<string, unknown>).memory_used as unknown[]
    : []
  return [...direct, ...nested]
}))
</script>

<template>
  <aside class="space-y-4">
    <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
      {{ title }}
    </h2>
    <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Context used
      </h3>
      <dl class="mt-3 space-y-3 text-sm">
        <div>
          <dt class="font-medium">
            Memory used
          </dt>
          <dd class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ artifacts.memoryUsed ? `${memory.length || 'Some'} item(s)` : 'No memory recorded' }}
          </dd>
        </div>
        <div>
          <dt class="font-medium">
            Skills used
          </dt>
          <dd class="mt-1">
            {{ artifacts.routingSummary.skill || 'Not recorded' }}
          </dd>
        </div>
        <div>
          <dt class="font-medium">
            Files inspected
          </dt>
          <dd class="mt-1">
            <ul v-if="filesInspected.length" class="space-y-1 font-mono text-xs">
              <li v-for="file in filesInspected" :key="file.path">
                {{ file.path }}
              </li>
            </ul>
            <span v-else class="text-zinc-500">No inspected files recorded</span>
          </dd>
        </div>
        <div>
          <dt class="font-medium">
            Routing reason
          </dt>
          <dd class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ artifacts.routingSummary.workflow || 'Waiting for routing.' }}
          </dd>
        </div>
      </dl>
      <details class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
        <summary class="cursor-pointer text-sm font-medium">
          Raw context
        </summary>
        <JsonViewer class="mt-2 max-h-[420px]" :data="contextEvents" />
      </details>
    </section>
  </aside>
</template>
