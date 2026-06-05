<script setup lang="ts">
import type { GraphColorMode } from '~/composables/useGraphColors'

const props = defineProps<{
  variant: 'skills' | 'knowledge'
  colorMode: GraphColorMode
  showCrossRef?: boolean
  showOverlap?: boolean
  edgeKinds?: string[]
  enabledKinds?: string[]
}>()

const emit = defineEmits<{
  'update:colorMode': [value: GraphColorMode]
  'update:showCrossRef': [value: boolean]
  'update:showOverlap': [value: boolean]
  'update:enabledKinds': [value: string[]]
}>()

function toggleKind(kind: string) {
  const set = new Set(props.enabledKinds ?? [])
  if (set.has(kind)) set.delete(kind)
  else set.add(kind)
  emit('update:enabledKinds', [...set])
}
</script>

<template>
  <div class="pointer-events-auto rounded-lg border border-zinc-700/80 bg-zinc-900/95 px-3 py-2.5 text-xs shadow-xl backdrop-blur-sm">
    <div class="font-semibold text-zinc-300 mb-2">
      Settings
    </div>

    <template v-if="variant === 'skills'">
      <label class="flex cursor-pointer items-center gap-2 py-0.5 text-zinc-400">
        <input
          type="checkbox"
          class="rounded border-zinc-600 accent-indigo-500"
          :checked="showCrossRef"
          @change="emit('update:showCrossRef', ($event.target as HTMLInputElement).checked)"
        >
        Show cross-reference edges
      </label>
      <label class="flex cursor-pointer items-center gap-2 py-0.5 text-zinc-400">
        <input
          type="checkbox"
          class="rounded border-zinc-600 accent-indigo-500"
          :checked="showOverlap"
          @change="emit('update:showOverlap', ($event.target as HTMLInputElement).checked)"
        >
        Show overlap edges
      </label>
    </template>

    <template v-else>
      <p class="text-zinc-500 mb-1">
        Edge types
      </p>
      <label
        v-for="kind in edgeKinds"
        :key="kind"
        class="flex cursor-pointer items-center gap-2 py-0.5 text-zinc-400 capitalize"
      >
        <input
          type="checkbox"
          class="rounded border-zinc-600 accent-indigo-500"
          :checked="(enabledKinds ?? []).includes(kind)"
          @change="toggleKind(kind)"
        >
        {{ kind.replaceAll('_', ' ') }}
      </label>
    </template>

    <label class="mt-2 flex items-center gap-2 text-zinc-400">
      <span>Color by</span>
      <select
        class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-zinc-200"
        :value="colorMode"
        @change="emit('update:colorMode', ($event.target as HTMLSelectElement).value as GraphColorMode)"
      >
        <option value="category">
          category
        </option>
        <option value="depth">
          depth
        </option>
      </select>
    </label>
  </div>
</template>
