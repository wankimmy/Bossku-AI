<script setup lang="ts">
import type { ChatTurn } from '~/composables/useLandingChat'

defineProps<{
  turn: ChatTurn
}>()
</script>

<template>
  <div class="flex gap-3">
    <div
      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm"
      :class="turn.role === 'user' ? 'bg-emerald-800 text-white' : 'bg-zinc-700 text-zinc-200'"
      aria-hidden="true"
    >
      {{ turn.role === 'user' ? 'You' : 'AI' }}
    </div>
    <div
      class="max-w-[min(100%,42rem)] rounded-lg px-3 py-2 text-sm leading-relaxed"
      :class="turn.role === 'user'
        ? 'bg-emerald-900/50 text-emerald-50 border border-emerald-800/40'
        : 'bg-zinc-800/80 text-zinc-100 border border-zinc-700/50'"
    >
      <pre class="whitespace-pre-wrap font-sans">{{ turn.content }}</pre>
    </div>
  </div>
</template>
