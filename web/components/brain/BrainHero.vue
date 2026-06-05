<script setup lang="ts">
import type { BrainData } from '~/types/api'

defineProps<{
  stats: BrainData | null
}>()
</script>

<template>
  <div class="relative overflow-hidden rounded-2xl border border-violet-900/50 bg-gradient-to-br from-violet-950/80 via-zinc-900 to-zinc-950 p-6">
    <div
      class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-fuchsia-500/20 blur-3xl"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-violet-600/15 blur-3xl"
      aria-hidden="true"
    />

    <div class="relative flex flex-wrap items-center gap-6">
      <!-- Stylized brain silhouette -->
      <div class="relative h-28 w-28 shrink-0" aria-hidden="true">
        <svg viewBox="0 0 120 120" class="h-full w-full text-fuchsia-400/90">
          <path
            fill="currentColor"
            d="M60 8c-14 0-26 10-28 24-8 2-14 10-14 20 0 6-4 12-2 16 2 4-10 12-18 22-18 10 0 18 6 22 14 4-8 12-12 20-10 8 2 14 8 14 16 0 10-6 18-14 20-10 2-14 8-24 8-28-2-4-14-10-24-10z"
            opacity="0.35"
          />
          <circle cx="42" cy="52" r="6" fill="#ec4899" opacity="0.8" />
          <circle cx="78" cy="48" r="5" fill="#a855f7" opacity="0.7" />
          <circle cx="60" cy="68" r="7" fill="#8b5cf6" opacity="0.85" />
          <circle cx="48" cy="78" r="4" fill="#c084fc" opacity="0.6" />
          <circle cx="72" cy="72" r="5" fill="#e879f9" opacity="0.65" />
        </svg>
      </div>

      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-bold tracking-tight text-zinc-50">
          BosskuAI Brain
        </h1>
        <p class="mt-1 max-w-xl text-sm text-violet-200/80">
          Live view of durable memory, learning events, and knowledge conflicts. Open the
          <strong class="text-fuchsia-200">Memory network</strong> tab to explore neurons and links.
        </p>
        <div class="mt-4 flex flex-wrap gap-3 text-xs">
          <span class="rounded-full bg-violet-900/50 px-3 py-1 text-violet-200">
            {{ stats?.memory_count ?? 0 }} memories
          </span>
          <span class="rounded-full bg-violet-900/50 px-3 py-1 text-violet-200">
            {{ stats?.knowledge_nodes ?? 0 }} graph nodes
          </span>
          <span class="rounded-full bg-red-950/50 px-3 py-1 text-red-300">
            {{ stats?.conflicts ?? 0 }} conflicts
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
