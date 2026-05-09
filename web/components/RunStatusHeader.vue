<script setup lang="ts">
import type { RoutingSummary } from '../types/bossku'

defineProps<{
  status?: string
  running?: boolean
  memoryUsed?: boolean
  routing?: RoutingSummary
}>()
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-lg font-semibold text-zinc-950 dark:text-zinc-50">
          BosskuAI agent workspace
        </h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
          Run a task through BosskuAI's visible agent workflow: plan, execute, audit, and finalize with Ollama models.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 text-xs">
        <span class="rounded-md border border-zinc-300 px-2 py-1 dark:border-zinc-700">
          Status: {{ running ? 'running' : (status || 'idle') }}
        </span>
        <span class="rounded-md border border-zinc-300 px-2 py-1 dark:border-zinc-700">
          Model backend: Ollama
        </span>
        <span class="rounded-md border border-zinc-300 px-2 py-1 dark:border-zinc-700">
          Memory: {{ memoryUsed ? 'used' : 'not used' }}
        </span>
      </div>
    </div>
    <dl v-if="routing" class="mt-3 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <dt class="text-zinc-500">
          Reasoning model
        </dt>
        <dd class="font-mono">
          {{ routing.reasoningModel || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-zinc-500">
          Coding model
        </dt>
        <dd class="font-mono">
          {{ routing.codingModel || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-zinc-500">
          Review model
        </dt>
        <dd class="font-mono">
          {{ routing.reviewModel || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-zinc-500">
          Fast model
        </dt>
        <dd class="font-mono">
          {{ routing.fastModel || '-' }}
        </dd>
      </div>
    </dl>
  </section>
</template>
