<script setup lang="ts">
import type { WorkspaceGraphNode } from '~/types/api'

defineProps<{
  node: WorkspaceGraphNode | null
}>()
</script>

<template>
  <aside class="flex h-full w-[360px] shrink-0 flex-col overflow-y-auto border-l border-zinc-800 bg-zinc-900/80 p-4">
    <UiEmptyState
      v-if="!node"
      title="Select a node"
      hint="Click a node to see type, confidence, and links to runs or skills."
    />

    <template v-else>
      <h2 class="text-lg font-semibold text-zinc-100 truncate">
        {{ node.label }}
      </h2>

      <div class="mt-2 flex flex-wrap gap-1.5">
        <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs capitalize text-zinc-300">
          {{ node.category || node.type }}
        </span>
        <span
          v-if="node.depth"
          class="rounded-full border border-zinc-700 px-2 py-0.5 text-xs text-zinc-400"
        >
          {{ node.depth }}
        </span>
        <span
          v-if="node.has_conflict"
          class="rounded-full border border-red-800 bg-red-950/50 px-2 py-0.5 text-xs text-red-300"
        >
          conflict
        </span>
      </div>

      <dl class="mt-4 space-y-3 text-sm">
        <div v-if="node.confidence !== undefined">
          <dt class="text-xs text-zinc-500">
            Confidence
          </dt>
          <dd class="text-zinc-200">
            {{ Math.round((node.confidence ?? 0) * 100) }}%
          </dd>
        </div>

        <div v-if="node.description">
          <dt class="text-xs text-zinc-500">
            Description
          </dt>
          <dd class="text-zinc-300 leading-relaxed">
            {{ node.description }}
          </dd>
        </div>

        <div v-if="node.source_type">
          <dt class="text-xs text-zinc-500">
            Source
          </dt>
          <dd class="text-zinc-400 capitalize">
            {{ node.source_type }}
            <span v-if="node.source_id" class="font-mono text-xs block truncate">{{ node.source_id }}</span>
          </dd>
        </div>

        <div v-if="node.properties && Object.keys(node.properties).length">
          <dt class="text-xs text-zinc-500">
            Properties
          </dt>
          <dd class="mt-1 rounded-lg border border-zinc-800 bg-zinc-950 p-2 font-mono text-xs text-zinc-400 overflow-x-auto">
            <pre>{{ JSON.stringify(node.properties, null, 2) }}</pre>
          </dd>
        </div>
      </dl>

      <div class="mt-4 space-y-2">
        <NuxtLink
          v-if="node.type === 'skill' || node.source_type === 'skill'"
          to="/skills"
          class="block rounded-lg bg-zinc-800 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700"
        >
          Open skills →
        </NuxtLink>
        <NuxtLink
          v-if="node.type === 'run' && node.source_id"
          :to="`/runs/${node.source_id}`"
          class="block rounded-lg bg-zinc-800 px-3 py-2 text-sm text-zinc-200 hover:bg-zinc-700"
        >
          Open run →
        </NuxtLink>
      </div>
    </template>
  </aside>
</template>
