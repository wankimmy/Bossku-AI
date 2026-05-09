<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  metadata?: Record<string, unknown> | null
}>()

const decision = computed(() => props.metadata?.routing_decision as Record<string, unknown> | undefined)
const models = computed(() => props.metadata?.models_resolved as Record<string, string> | undefined)

const memoryUsed = computed(() => {
  const m = String(decision.value?.memory_mode ?? '').toLowerCase().trim()
  return Boolean(m && m !== 'skip' && m !== 'none' && m !== 'no')
})

const roleModels = computed(() => ({
  reasoning: models.value?.orchestrator || models.value?.final_reviewer || models.value?.writer || '',
  coding: models.value?.executor || '',
  review: models.value?.auditor || models.value?.security_auditor || models.value?.final_reviewer || '',
  fast: models.value?.router || models.value?.direct_answer || '',
}))

const why = computed(() => {
  if (!decision.value) return 'No routing metadata on this run. Older runs may only have raw step data.'
  const parts = [
    `Workflow ${String(decision.value.workflow ?? '-')} was selected for ${String(decision.value.task_type ?? 'this task')}.`,
    `Risk level is ${String(decision.value.risk_level ?? '-')}.`,
  ]
  if (decision.value.reason) parts.push(String(decision.value.reason))
  return parts.join(' ')
})
</script>

<template>
  <section
    v-if="decision || models"
    class="rounded-lg border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900"
  >
    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
      Model routing
    </h2>
    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Model backend
        </dt>
        <dd class="mt-0.5 font-medium">
          Ollama
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Workflow
        </dt>
        <dd class="mt-0.5 font-mono">
          {{ decision?.workflow ?? '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Reasoning model
        </dt>
        <dd class="mt-0.5 font-mono">
          {{ roleModels.reasoning || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Coding model
        </dt>
        <dd class="mt-0.5 font-mono">
          {{ roleModels.coding || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Review model
        </dt>
        <dd class="mt-0.5 font-mono">
          {{ roleModels.review || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Fast model
        </dt>
        <dd class="mt-0.5 font-mono">
          {{ roleModels.fast || '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Skill
        </dt>
        <dd class="mt-0.5">
          {{ decision?.skill ?? '-' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">
          Memory used
        </dt>
        <dd class="mt-0.5">
          {{ memoryUsed ? 'yes' : 'no' }}
        </dd>
      </div>
    </dl>
    <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Routing reason
      </h3>
      <p class="mt-2 leading-relaxed text-zinc-700 dark:text-zinc-300">
        {{ why }}
      </p>
    </div>
  </section>
</template>
