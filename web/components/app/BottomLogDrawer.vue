<script setup lang="ts">
defineProps<{ open?: boolean }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const { logs } = useLogs()
const recent = computed(() => (logs.value ?? []).slice(-20))
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 z-20 bg-zinc-900 border-t border-zinc-800 lg:pl-[220px]">
    <div
      class="flex items-center justify-between px-4 h-8 cursor-pointer select-none"
      @click="emit('update:open', !open)"
    >
      <span class="text-xs text-zinc-500 font-mono">
        {{ open ? '▼ Logs' : '▲ Logs' }} — {{ recent.length }} entries
      </span>
      <span class="text-xs text-zinc-600">{{ open ? 'collapse' : 'expand' }}</span>
    </div>
    <div v-if="open" class="h-40 overflow-y-auto px-4 pb-3">
      <div
        v-for="(log, i) in recent"
        :key="i"
        class="font-mono text-xs py-0.5 text-zinc-400"
      >
        <span class="text-zinc-600 mr-2">{{ log.timestamp || '' }}</span>
        <span
          class="mr-2 uppercase"
          :class="{
            'text-red-400': log.level === 'error',
            'text-yellow-400': log.level === 'warning',
            'text-blue-400': log.level === 'info',
            'text-zinc-500': log.level === 'debug',
          }"
        >{{ log.level || 'info' }}</span>
        {{ log.message || String(log) }}
      </div>
      <div v-if="recent.length === 0" class="text-xs text-zinc-600 py-2">No log entries.</div>
    </div>
  </div>
</template>
