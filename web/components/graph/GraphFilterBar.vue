<script setup lang="ts">
interface FilterValue {
  types: string[]
  onlyConflicts: boolean
}

const props = defineProps<{
  nodeTypes: string[]
  modelValue: FilterValue
}>()

const emit = defineEmits<{
  'update:modelValue': [value: FilterValue]
}>()

function toggleType(type: string) {
  const current = props.modelValue.types
  const next = current.includes(type)
    ? current.filter(t => t !== type)
    : [...current, type]
  emit('update:modelValue', { ...props.modelValue, types: next })
}

function toggleConflicts() {
  emit('update:modelValue', { ...props.modelValue, onlyConflicts: !props.modelValue.onlyConflicts })
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <span class="text-xs font-medium text-zinc-400">Filter:</span>

    <label
      v-for="type in nodeTypes"
      :key="type"
      class="flex cursor-pointer items-center gap-1.5 text-xs text-zinc-300"
    >
      <input
        type="checkbox"
        :checked="modelValue.types.includes(type)"
        class="rounded border-zinc-600 bg-zinc-900 accent-indigo-500"
        @change="toggleType(type)"
      >
      {{ type }}
    </label>

    <label class="flex cursor-pointer items-center gap-1.5 text-xs text-red-400">
      <input
        type="checkbox"
        :checked="modelValue.onlyConflicts"
        class="rounded border-zinc-600 bg-zinc-900 accent-red-500"
        @change="toggleConflicts"
      >
      Conflicts only
    </label>
  </div>
</template>
