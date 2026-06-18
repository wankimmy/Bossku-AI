<script setup lang="ts">
import type { InferenceProviderGroup } from '~/composables/useInferenceCatalog'

const props = defineProps<{
  modelValue: string
  groups: InferenceProviderGroup[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const options = computed(() =>
  props.groups.map(g => ({
    value: g.provider,
    label: g.name,
    disabled: !g.configured,
    hint: g.hint,
    auth: g.auth,
  })),
)

const selectedHint = computed(() => {
  const g = props.groups.find(g => g.provider === props.modelValue)
  return g?.hint
})
</script>

<template>
  <div class="space-y-1">
    <select
      :value="modelValue"
      :disabled="disabled"
      class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500"
      @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option value="" disabled>
        Select provider…
      </option>
      <option
        v-for="o in options"
        :key="o.value"
        :value="o.value"
        :disabled="o.disabled"
      >
        {{ o.label }}{{ o.disabled ? ' (not configured)' : '' }}{{ o.auth === 'oauth' ? ' · OAuth' : '' }}
      </option>
    </select>
    <p v-if="selectedHint" class="text-xs text-amber-400">
      {{ selectedHint }}
    </p>
  </div>
</template>
