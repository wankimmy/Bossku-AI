<script setup lang="ts">
import type { WorkspaceGraphNode } from '~/types/api'

defineProps<{
  node: WorkspaceGraphNode | null
}>()

function depthClass(depth: string) {
  if (depth === 'DEEP') return 'bg-emerald-900/40 text-emerald-300 border-emerald-800'
  if (depth === 'OK') return 'bg-amber-900/40 text-amber-300 border-amber-800'
  return 'bg-rose-900/40 text-rose-300 border-rose-800'
}
</script>

<template>
  <aside class="flex h-full w-[360px] shrink-0 flex-col overflow-y-auto border-l border-zinc-800 bg-zinc-900/80 p-4">
    <UiEmptyState
      v-if="!node"
      title="Select a node"
      hint="Click a skill on the graph to inspect triggers, keywords, and playbooks."
    />

    <template v-else>
      <h2 class="text-lg font-semibold text-zinc-100 break-all">
        {{ node.id }}
      </h2>

      <div class="mt-2 flex flex-wrap gap-1.5">
        <span class="rounded-full border px-2 py-0.5 text-xs font-medium" :class="depthClass(node.depth)">
          {{ node.depth }}
        </span>
        <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs text-zinc-300 capitalize">
          {{ node.category }}
        </span>
        <span
          v-if="node.is_marquee"
          class="rounded-full border border-indigo-700 bg-indigo-950/60 px-2 py-0.5 text-xs text-indigo-300"
        >
          MARQUEE
        </span>
        <span
          v-if="node.is_core"
          class="rounded-full border border-zinc-600 px-2 py-0.5 text-xs text-zinc-400"
        >
          CORE
        </span>
        <span
          v-if="node.has_conflict"
          class="rounded-full border border-red-800 bg-red-950/50 px-2 py-0.5 text-xs text-red-300"
        >
          CONFLICT
        </span>
      </div>

      <div class="mt-4 space-y-4 text-sm">
        <div>
          <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
            Description
          </div>
          <p class="mt-1 text-zinc-300 leading-relaxed">
            {{ node.description || '—' }}
          </p>
        </div>

        <div>
          <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
            Lines (skill + playbook)
          </div>
          <p class="mt-1 text-zinc-200">
            {{ node.skill_lines ?? 0 }} + {{ node.playbook_lines ?? 0 }} =
            <strong>{{ node.total_lines ?? 0 }}</strong>
          </p>
        </div>

        <div>
          <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
            Triggers ({{ node.trigger_count ?? node.triggers?.length ?? 0 }})
          </div>
          <div class="mt-1.5 flex flex-wrap gap-1">
            <span
              v-for="t in (node.triggers ?? [])"
              :key="t"
              class="rounded-full bg-zinc-800 px-2 py-0.5 text-xs text-zinc-300"
            >{{ t }}</span>
            <span v-if="!(node.triggers?.length)" class="text-zinc-500">none</span>
          </div>
        </div>

        <div>
          <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
            Keywords
          </div>
          <p class="mt-1 text-xs text-zinc-400 font-mono leading-relaxed">
            {{ (node.keywords ?? []).join(', ') || 'none' }}
          </p>
        </div>

        <div>
          <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
            Referenced playbooks
          </div>
          <ul class="mt-1 list-inside list-disc text-xs text-zinc-400 font-mono space-y-0.5">
            <li v-for="ref in (node.playbook_refs ?? [])" :key="ref">
              {{ ref }}
            </li>
            <li v-if="!(node.playbook_refs?.length)" class="list-none text-zinc-500">
              none
            </li>
          </ul>
        </div>

        <NuxtLink
          v-if="node.source_id"
          to="/skills"
          class="inline-block text-xs text-emerald-400 hover:underline"
        >
          Open skills list →
        </NuxtLink>
      </div>
    </template>
  </aside>
</template>
