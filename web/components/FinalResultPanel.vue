<script setup lang="ts">
import type { FinalResult } from '../types/bossku'
defineProps<{ result: FinalResult }>()
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-semibold">
        Final result
      </h2>
      <span class="rounded border border-zinc-300 px-2 py-0.5 font-mono text-xs dark:border-zinc-700">{{ result.status || 'pending' }}</span>
    </div>
    <p v-if="result.summary" class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">
      {{ result.summary }}
    </p>
    <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Files changed
        </dt>
        <dd class="mt-1">
          <ul v-if="result.filesChanged.length" class="space-y-1 font-mono text-xs">
            <li v-for="file in result.filesChanged" :key="file">
              {{ file }}
            </li>
          </ul>
          <span v-else class="text-zinc-500">None recorded</span>
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Checks run
        </dt>
        <dd class="mt-1">
          <ul v-if="result.checksRun.length" class="space-y-1 font-mono text-xs">
            <li v-for="check in result.checksRun" :key="check">
              {{ check }}
            </li>
          </ul>
          <span v-else class="text-zinc-500">None recorded</span>
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Audit result
        </dt>
        <dd class="mt-1 capitalize">
          {{ (result.auditResult || 'not recorded').replaceAll('_', ' ') }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Next step
        </dt>
        <dd class="mt-1">
          {{ result.nextStep || 'No next step recorded.' }}
        </dd>
      </div>
    </dl>
    <div v-if="result.remainingRisks.length" class="mt-4">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Remaining risks
      </h3>
      <RiskItemList class="mt-2" :risks="result.remainingRisks" />
    </div>
  </section>
</template>
