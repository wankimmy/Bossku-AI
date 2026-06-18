<script setup lang="ts">
import type { InferenceModelOption } from '~/composables/useInferenceCatalog'

const props = defineProps<{
  modelValue: string
  provider: string
  role: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const { fetchRecommendations } = useInferenceCatalog()

const models = ref<InferenceModelOption[]>([])
const loading = ref(false)

async function loadModels() {
  if (!props.provider) {
    models.value = []
    return
  }
  loading.value = true
  try {
    const res = await fetchRecommendations(props.role, props.provider)
    models.value = res.recommended_models ?? []
    const auto = res.auto_selected ?? models.value[0]?.id
    if (auto && (!props.modelValue || !models.value.some(m => m.id === props.modelValue))) {
      emit('update:modelValue', auto)
    }
  }
  catch {
    models.value = []
  }
  finally {
    loading.value = false
  }
}

watch(() => [props.provider, props.role], loadModels, { immediate: true })
</script>

<template>
  <select
    :value="modelValue"
    :disabled="disabled || loading || models.length === 0"
    class="w-full bg-zinc-800 border border-zinc-700 rounded px-3 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500 disabled:opacity-50"
    @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <option v-if="loading" value="" disabled>
      Loading models…
    </option>
    <option v-else-if="models.length === 0" value="" disabled>
      No models for this provider
    </option>
    <option
      v-for="m in models"
      :key="m.id"
      :value="m.id"
    >
      {{ m.label }}{{ m.auto_selected ? ' (recommended)' : '' }}
    </option>
  </select>
</template>
