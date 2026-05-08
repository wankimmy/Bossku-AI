<script setup lang="ts">
defineProps<{ events: Record<string, unknown>[] }>()
</script>

<template>
  <section class="space-y-3" aria-live="polite">
    <h2 class="sr-only">
      Execution timeline
    </h2>
    <TimelineStepCard
      v-for="(evt, idx) in events"
      :key="idx"
      :payload="evt"
      :default-open="idx === events.length - 1"
    />
    <UiEmptyState
      v-if="events.length === 0"
      title="No steps yet."
      hint="Submit a prompt to stream memory, routing, planner, executor, and auditor stages."
    />
  </section>
</template>
