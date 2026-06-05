<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import type { LogEntry } from '~/types/api'

defineProps<{ open?: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const { logs } = useLogs()

type LevelFilter = 'all' | 'info' | 'warning' | 'error' | 'debug'
const filter = ref<LevelFilter>('all')
const listRef = ref<HTMLElement | null>(null)

const counts = computed(() => {
  const arr = logs.value
  return {
    error: arr.filter(l => l.level === 'error' || l.level === 'critical').length,
    warning: arr.filter(l => l.level === 'warning').length,
  }
})

const filtered = computed<LogEntry[]>(() => {
  const arr = logs.value
  if (filter.value === 'all') return arr
  if (filter.value === 'error') return arr.filter(l => l.level === 'error' || l.level === 'critical')
  return arr.filter(l => l.level === filter.value)
})

watch(filtered, async () => {
  await nextTick()
  if (listRef.value) {
    listRef.value.scrollTop = listRef.value.scrollHeight
  }
}, { flush: 'post' })

function formatTime(ts: string | undefined): string {
  if (!ts) return ''
  // ts is "YYYY-MM-DD HH:MM:SS"
  return ts.slice(11, 19)
}

function levelClass(level: string): string {
  if (level === 'error' || level === 'critical') return 'text-red-400'
  if (level === 'warning') return 'text-yellow-400'
  if (level === 'info') return 'text-blue-400'
  return 'text-zinc-500'
}
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 z-20 bg-zinc-900 border-t border-zinc-800 lg:pl-[220px]">
    <!-- Collapsed bar (always visible) -->
    <div
      class="flex items-center gap-2 px-4 h-8 cursor-pointer select-none"
      @click="emit('update:open', !open)"
    >
      <span class="text-xs text-zinc-500 font-mono">{{ open ? '▼' : '▲' }} Logs</span>
      <span
        v-if="counts.error > 0"
        class="px-1.5 py-0 text-xs rounded font-mono bg-red-900/50 text-red-300 leading-5"
      >{{ counts.error }} error{{ counts.error !== 1 ? 's' : '' }}</span>
      <span
        v-if="counts.warning > 0"
        class="px-1.5 py-0 text-xs rounded font-mono bg-yellow-900/30 text-yellow-400 leading-5"
      >{{ counts.warning }} warn</span>
      <span class="ml-auto text-xs text-zinc-600">{{ logs.length }} entries</span>
    </div>

    <!-- Expanded panel -->
    <template v-if="open">
      <!-- Filter tabs -->
      <div class="flex items-center gap-1 px-3 py-1 border-b border-zinc-800/60">
        <button
          v-for="tab in (['all', 'info', 'warning', 'error', 'debug'] as const)"
          :key="tab"
          class="px-2 py-0.5 text-xs rounded font-mono transition-colors"
          :class="filter === tab ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-500 hover:text-zinc-300'"
          @click.stop="filter = tab"
        >
          {{ tab }}
        </button>
        <span class="ml-auto text-xs text-zinc-600 font-mono">{{ filtered.length }} shown</span>
      </div>

      <!-- Log list -->
      <div ref="listRef" class="h-52 overflow-y-auto py-1">
        <div
          v-for="(log, i) in filtered"
          :key="i"
          class="flex items-start gap-2 px-4 py-px font-mono text-xs hover:bg-zinc-800/40 group min-w-0"
        >
          <span class="shrink-0 text-zinc-600 w-20">{{ formatTime(log.timestamp) }}</span>
          <span class="shrink-0 w-16 text-right uppercase" :class="levelClass(log.level)">{{ log.level }}</span>
          <span class="text-zinc-400 min-w-0 break-all">{{ log.message }}</span>
          <span
            v-if="log.context && Object.keys(log.context).length"
            class="shrink-0 text-zinc-600 group-hover:text-zinc-400 cursor-help"
            :title="JSON.stringify(log.context, null, 2)"
          >{…}</span>
        </div>
        <div v-if="filtered.length === 0" class="px-4 py-3 text-xs text-zinc-600">
          {{ logs.length === 0 ? 'Connecting to log stream…' : 'No entries for this filter.' }}
        </div>
      </div>
    </template>
  </div>
</template>
