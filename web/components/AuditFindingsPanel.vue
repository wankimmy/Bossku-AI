<script setup lang="ts">
import type { AuditFinding } from '../types/bossku'

defineProps<{ status?: string; findings: AuditFinding[] }>()

function severityClass(severity: string) {
  if (severity === 'critical' || severity === 'high') return 'border-rose-400 text-rose-700 dark:text-rose-300'
  if (severity === 'medium') return 'border-amber-400 text-amber-700 dark:text-amber-300'
  return 'border-zinc-300 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300'
}
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-semibold">
        Audit findings
      </h2>
      <span class="rounded border border-zinc-300 px-2 py-0.5 font-mono text-xs dark:border-zinc-700">{{ status || 'not_run' }}</span>
    </div>
    <div v-if="findings.length" class="mt-3 space-y-2">
      <article v-for="finding in findings" :key="finding.id || finding.title" class="rounded-md border border-zinc-200 p-2 dark:border-zinc-800">
        <div class="flex flex-wrap items-center gap-2">
          <span class="rounded border px-1.5 py-0.5 text-[11px]" :class="severityClass(String(finding.severity))">{{ finding.severity }}</span>
          <span v-if="finding.category" class="rounded bg-zinc-100 px-1.5 py-0.5 text-[11px] dark:bg-zinc-800">{{ finding.category }}</span>
          <span class="font-medium">{{ finding.title }}</span>
        </div>
        <p v-if="finding.description" class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
          {{ finding.description }}
        </p>
        <p v-if="finding.suggested_fix" class="mt-2 text-sm">
          Suggested fix: {{ finding.suggested_fix }}
        </p>
        <p v-if="finding.status" class="mt-1 font-mono text-xs text-zinc-500">
          {{ finding.status }}
        </p>
      </article>
    </div>
    <UiEmptyState v-else title="No audit findings." hint="Audit feedback appears here when the auditor runs." />
  </section>
</template>
