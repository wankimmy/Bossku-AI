<script setup lang="ts">
import { computed } from 'vue'

/**
 * Read-only routing summary from Run.metadata (Laravel API).
 */
const props = defineProps<{
  metadata?: Record<string, unknown> | null
}>()

const decision = computed(() => props.metadata?.routing_decision as Record<string, unknown> | undefined)
const models = computed(() => props.metadata?.models_resolved as Record<string, string> | undefined)
const security = computed(() => props.metadata?.security_audit as Record<string, unknown> | undefined)
const finalRev = computed(() => props.metadata?.final_reviewer as Record<string, unknown> | undefined)

/** Dashboard spec: none / skip / no => Memory Used: no. */
const memoryUsed = computed(() => {
  const m = String(decision.value?.memory_mode ?? '').toLowerCase().trim()
  return Boolean(m && m !== 'skip' && m !== 'none' && m !== 'no')
})

const why = computed(() => {
  if (!decision.value) {
    return 'No routing metadata on this run (may be an older run).'
  }
  const r = decision.value
  const parts: string[] = []
  parts.push(`Workflow ${String(r.workflow ?? '—')} was selected for task type ${String(r.task_type ?? '—')} with risk ${String(r.risk_level ?? '—')}.`)
  if (r.reason) { parts.push(String(r.reason)) }
  if (models.value?.executor) {
    parts.push(`Executor profile ${String(r.executor_profile ?? '—')} maps to model ${models.value.executor} (stronger model when risk is high).`)
  }
  if (!(r.needs_final_reviewer ?? false)) {
    parts.push('Final reviewer was skipped because this task is not high-risk.')
  }
  return parts.join(' ')
})
</script>

<template>
  <section
    v-if="decision || models"
    class="rounded-lg border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900"
  >
    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
      Routing
    </h2>
    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Workflow
        </dt>
        <dd class="mt-0.5 font-mono text-zinc-800 dark:text-zinc-200">
          {{ decision?.workflow ?? '—' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Skill
        </dt>
        <dd class="mt-0.5">
          {{ decision?.skill ?? '—' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Risk
        </dt>
        <dd class="mt-0.5">
          <span class="rounded border border-zinc-300 px-2 py-0.5 text-xs dark:border-zinc-600">{{ decision?.risk_level ?? '—' }}</span>
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Executor profile
        </dt>
        <dd class="mt-0.5 font-mono">
          {{ decision?.executor_profile ?? '—' }}
        </dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Models
        </dt>
        <dd class="mt-1 space-y-1 font-mono text-xs text-zinc-700 dark:text-zinc-300">
          <div v-if="models?.router">
            router={{ models.router }}
          </div>
          <div v-if="models?.orchestrator">
            orchestrator={{ models.orchestrator }}
          </div>
          <div v-if="models?.executor">
            executor={{ models.executor }}
          </div>
          <div v-if="models?.auditor">
            auditor={{ models.auditor }}
          </div>
          <div v-if="models?.security_auditor">
            security_auditor={{ models.security_auditor }}
          </div>
          <div v-if="models?.final_reviewer">
            final_reviewer={{ models.final_reviewer }}
          </div>
          <div v-if="models?.writer">
            writer={{ models.writer }}
          </div>
          <div v-if="models?.direct_answer">
            direct_answer={{ models.direct_answer }}
          </div>
        </dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Flags
        </dt>
        <dd class="mt-1 flex flex-wrap gap-2 text-xs">
          <span v-if="decision?.needs_executor" class="rounded bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800">executor</span>
          <span v-if="decision?.needs_auditor" class="rounded bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800">auditor</span>
          <span v-if="decision?.needs_security_auditor" class="rounded bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800">security</span>
          <span v-if="decision?.needs_final_reviewer" class="rounded bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800">final reviewer</span>
          <span class="rounded bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800">memory: {{ decision?.memory_mode ?? '—' }}</span>
          <span class="rounded bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800">Memory Used: {{ memoryUsed ? 'yes' : 'no' }}</span>
        </dd>
      </div>
    </dl>

    <div v-if="security" class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Security audit
      </h3>
      <p class="mt-1 font-mono text-xs">
        {{ security.status }} — {{ security.summary }}
      </p>
    </div>
    <div v-if="finalRev" class="mt-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Final reviewer
      </h3>
      <p class="mt-1 font-mono text-xs">
        {{ finalRev.decision }} — {{ finalRev.reason }}
      </p>
    </div>

    <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Why this route?
      </h3>
      <p class="mt-2 leading-relaxed text-zinc-700 dark:text-zinc-300">
        {{ why }}
      </p>
    </div>
  </section>
</template>
