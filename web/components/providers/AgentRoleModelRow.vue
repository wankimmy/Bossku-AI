<script setup lang="ts">
import type { InferenceProviderGroup } from '~/composables/useInferenceCatalog'

const props = defineProps<{
  label: string
  role: string
  modelValue: string
  providerValue: string
  groups: InferenceProviderGroup[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:providerValue': [value: string]
}>()

function onProviderChange(value: string) {
  emit('update:providerValue', value)
}
</script>

<template>
  <div class="space-y-2 rounded-md border border-zinc-800/80 bg-zinc-950/40 p-3">
    <p class="text-sm font-medium text-zinc-200">
      {{ label }}
    </p>
    <label class="block text-xs text-zinc-500">
      Provider
      <div class="mt-1">
        <ProvidersProviderSelect
          :model-value="providerValue"
          :groups="groups"
          @update:model-value="onProviderChange"
        />
      </div>
    </label>
    <label class="block text-xs text-zinc-500">
      Best model
      <div class="mt-1">
        <ProvidersModelSelect
          :model-value="modelValue"
          :provider="providerValue"
          :role="role"
          :disabled="!providerValue"
          @update:model-value="emit('update:modelValue', $event)"
        />
      </div>
    </label>
  </div>
</template>
