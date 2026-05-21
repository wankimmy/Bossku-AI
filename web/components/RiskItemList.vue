<script setup lang="ts">
import type { RiskItem } from '../utils/humanizeOutput'
import { severityBadgeClass } from '../utils/humanizeOutput'

defineProps<{ risks: RiskItem[]; compact?: boolean }>()
</script>

<template>
  <ul class="space-y-2" :class="compact ? 'text-xs' : 'text-sm'">
    <li
      v-for="(risk, idx) in risks"
      :key="`${risk.issue}-${idx}`"
      class="rounded-lg border border-zinc-700/80 bg-zinc-900/50 p-3"
    >
      <div class="flex flex-wrap items-center gap-2">
        <span
          class="rounded border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
          :class="severityBadgeClass(risk.severity)"
        >
          {{ risk.severity }}
        </span>
        <span class="font-medium text-zinc-100">{{ risk.issue }}</span>
      </div>
      <p v-if="risk.location" class="mt-1.5 font-mono text-xs text-cyan-400/90">
        {{ risk.location }}
      </p>
      <p v-if="risk.description && risk.description !== risk.issue" class="mt-1.5 leading-relaxed text-zinc-400">
        {{ risk.description }}
      </p>
      <p v-if="risk.recommendation" class="mt-1.5 text-zinc-300">
        <span class="text-zinc-500">Fix:</span> {{ risk.recommendation }}
      </p>
    </li>
  </ul>
</template>
