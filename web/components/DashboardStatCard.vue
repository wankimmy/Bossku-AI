<script setup lang="ts">
import { computed } from 'vue'
import {
  getDashboardCardStyle,
  type DashboardCardVariant,
} from '~/utils/dashboardCardVariants'

const props = defineProps<{
  label: string
  value: string
  variant: DashboardCardVariant
  active?: boolean
  pulse?: boolean
}>()

const style = computed(() => getDashboardCardStyle(props.variant, { active: props.active }))
</script>

<template>
  <div
    class="relative rounded-lg border px-3 py-2.5 shadow-sm"
    :class="style.container"
    :data-variant="variant"
  >
    <div class="flex items-start justify-between gap-2">
      <p
        class="text-[10px] font-semibold uppercase tracking-wide"
        :class="style.label"
      >
        {{ label }}
      </p>
      <span
        v-if="style.icon"
        class="text-sm leading-none opacity-80"
        aria-hidden="true"
      >{{ style.icon }}</span>
    </div>
    <p
      class="mt-1 truncate font-mono text-sm font-medium"
      :class="style.value"
    >
      <span
        v-if="pulse && style.dot"
        class="mr-1.5 inline-block h-2 w-2 animate-pulse rounded-full align-middle"
        :class="style.dot"
        aria-hidden="true"
      />
      {{ value }}
    </p>
  </div>
</template>
