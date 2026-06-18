<script setup lang="ts">
const props = defineProps<{
  label: string
  apiKeyMasked?: string | null
  showToggle?: boolean
}>()

const model = defineModel<string>({ default: '' })
const showKey = ref(false)
</script>

<template>
  <label class="block">
    <span class="text-xs text-zinc-500 block mb-1">{{ label }}</span>
    <input
      v-model="model"
      :type="showToggle && showKey ? 'text' : 'password'"
      :placeholder="apiKeyMasked ? `Saved: ${apiKeyMasked}` : 'sk-...'"
      class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500"
    >
    <button
      v-if="showToggle"
      type="button"
      class="mt-1 text-xs text-zinc-500 hover:text-zinc-300"
      @click="showKey = !showKey"
    >
      {{ showKey ? 'Hide' : 'Show' }}
    </button>
  </label>
</template>
