<script setup lang="ts">
defineProps<{
  open?: boolean
  title?: string
}>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed right-0 top-12 bottom-0 w-[300px] bg-zinc-800 border-l border-zinc-700 z-20 flex flex-col shadow-xl"
    >
      <div class="flex items-center justify-between px-4 h-11 border-b border-zinc-700 shrink-0">
        <span class="text-sm font-semibold text-zinc-100">{{ title || 'Inspector' }}</span>
        <button
          type="button"
          class="text-zinc-400 hover:text-zinc-100 text-xs"
          @click="emit('close')"
        >
          ✕ Close
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-4">
        <slot />
      </div>
    </div>
  </Teleport>
</template>
