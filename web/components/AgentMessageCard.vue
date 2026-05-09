<script setup lang="ts">
import type { AgentMessage } from '../types/bossku'

defineProps<{ message: AgentMessage }>()
</script>

<template>
  <article class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-sm font-semibold">
          {{ message.title }}
        </h2>
        <div class="mt-1 flex flex-wrap gap-2 text-xs text-zinc-500">
          <span>Role: {{ message.agent }}</span>
          <span v-if="message.model_role">Model role: {{ message.model_role }}</span>
          <span v-if="message.model" class="font-mono">{{ message.model }}</span>
        </div>
      </div>
      <span class="rounded border border-zinc-300 px-2 py-0.5 font-mono text-xs dark:border-zinc-700">{{ message.status }}</span>
    </div>
    <p v-if="message.summary" class="mt-3 text-sm leading-relaxed">
      {{ message.summary }}
    </p>
    <p v-if="message.message && message.message !== message.summary" class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
      {{ message.message }}
    </p>
    <div v-if="message.to_agent || message.latency_ms || message.token_estimate" class="mt-3 flex flex-wrap gap-2 text-xs text-zinc-500">
      <span v-if="message.to_agent">Handoff: {{ message.to_agent }}</span>
      <span v-if="message.latency_ms !== undefined">{{ message.latency_ms }}ms</span>
      <span v-if="message.token_estimate !== undefined">{{ message.token_estimate }} tokens est.</span>
    </div>
  </article>
</template>
