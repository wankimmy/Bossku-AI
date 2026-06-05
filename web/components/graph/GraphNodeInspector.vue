<script setup lang="ts">
import type { GraphNode } from '~/types/api'

const props = defineProps<{
  node: GraphNode | null
}>()
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="translate-x-4 opacity-0"
    enter-to-class="translate-x-0 opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="translate-x-0 opacity-100"
    leave-to-class="translate-x-4 opacity-0"
  >
    <aside
      v-if="node"
      class="w-72 flex-shrink-0 rounded-xl border border-zinc-800 bg-zinc-900 p-4 space-y-4"
    >
      <div class="flex items-start justify-between gap-2">
        <h3 class="font-semibold text-white truncate">{{ node.label }}</h3>
        <span
          class="rounded border px-2 py-0.5 text-xs capitalize"
          :class="{
            'border-emerald-700 text-emerald-400': node.type === 'skill',
            'border-blue-700 text-blue-400': node.type === 'run',
            'border-purple-700 text-purple-400': node.type === 'memory',
            'border-zinc-700 text-zinc-400': !['skill','run','memory'].includes(node.type ?? ''),
          }"
        >{{ node.type ?? 'unknown' }}</span>
      </div>

      <dl class="space-y-2 text-sm">
        <div v-if="node.confidence !== undefined">
          <dt class="text-xs text-zinc-500">Confidence</dt>
          <dd class="text-zinc-200">{{ Math.round((node.confidence ?? 0) * 100) }}%</dd>
        </div>

        <div>
          <dt class="text-xs text-zinc-500">Has conflict</dt>
          <dd :class="node.has_conflict ? 'text-red-400' : 'text-zinc-400'">
            {{ node.has_conflict ? 'Yes' : 'No' }}
          </dd>
        </div>

        <div v-if="node.source_type">
          <dt class="text-xs text-zinc-500">Source type</dt>
          <dd class="text-zinc-200">{{ node.source_type }}</dd>
        </div>

        <div v-if="node.source_id">
          <dt class="text-xs text-zinc-500">Source ID</dt>
          <dd class="truncate font-mono text-xs text-zinc-400">{{ node.source_id }}</dd>
        </div>
      </dl>

      <!-- Action links -->
      <div class="space-y-1.5 pt-1">
        <NuxtLink
          v-if="node.type === 'skill'"
          to="/skills"
          class="flex items-center gap-1.5 rounded-lg bg-zinc-800 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700 transition"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          Open Skill
        </NuxtLink>

        <NuxtLink
          v-if="node.type === 'run' && node.source_id"
          :to="`/runs/${node.source_id}`"
          class="flex items-center gap-1.5 rounded-lg bg-zinc-800 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700 transition"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Open Run
        </NuxtLink>
      </div>
    </aside>
  </Transition>
</template>
