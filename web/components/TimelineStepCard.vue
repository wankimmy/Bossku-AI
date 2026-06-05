<script setup lang="ts">
const props = withDefaults(
  defineProps<{ payload: Record<string, unknown>; defaultOpen?: boolean }>(),
  { defaultOpen: false },
)

const expanded = ref(props.defaultOpen)
</script>

<template>
  <article class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <button
      type="button"
      class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
      @click="expanded = !expanded"
    >
      <span class="font-medium capitalize">{{ String(payload.type || 'event').replaceAll('_', ' ') }}</span>
      <UiStepStatusBadge :status="payload.status ? String(payload.status) : ''" />
    </button>
    <div v-show="expanded" class="border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
      <JsonViewer class="max-h-[min(420px,50vh)]" :data="payload" />
    </div>
  </article>
</template>
