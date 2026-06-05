<script setup lang="ts">
import type { SseEvent } from '~/composables/useRunStream'
import type { HandoffNode } from '~/types/bossku'

defineProps<{
  events: SseEvent[]
  running: boolean
  handoffNodes: HandoffNode[]
}>()
</script>

<template>
  <div class="space-y-3">
    <AgentHandoffFlow layout="vertical" :nodes="handoffNodes" />
    <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
      <AgentActivityFeed :events="events as Record<string, unknown>[]" :running="running" />
    </div>
  </div>
</template>
