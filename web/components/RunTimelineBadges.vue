<script setup lang="ts">
import type { AgentMessage, AiCouncilReview, RoutingSummary } from '~/types/bossku'

const props = defineProps<{
  routing?: RoutingSummary
  messages?: AgentMessage[]
  aiCouncil?: AiCouncilReview
}>()

const specialist = computed(() => {
  for (const message of props.messages ?? []) {
    const payload = message.artifacts?.specialist_agent as Record<string, unknown> | undefined
    if (payload?.display_name) {
      return payload
    }
  }
  return null
})

const workflowLabel = computed(() => {
  const workflow = props.routing?.workflow ?? ''
  if (workflow === 'direct_answer') return 'Direct answer'
  if (workflow === 'writer_only') return 'Writer specialist'
  if (workflow === '') return 'Pipeline'
  return workflow.replaceAll('_', ' ')
})
</script>

<template>
  <section
    class="flex flex-wrap gap-2"
    data-testid="run-timeline-badges"
  >
    <span
      v-if="routing?.workflow"
      class="rounded-full border border-zinc-700 bg-zinc-900 px-2.5 py-1 text-xs text-zinc-200"
    >
      {{ workflowLabel }}
    </span>

    <span
      v-if="specialist?.display_name"
      class="rounded-full border border-emerald-800/70 bg-emerald-950/40 px-2.5 py-1 text-xs text-emerald-200"
      :title="String(specialist.match_reason ?? '')"
    >
      {{ specialist.display_name }}
      <span
        v-if="specialist.match_score"
        class="text-emerald-400/80"
      >· {{ specialist.match_score }}</span>
    </span>

    <span
      v-if="aiCouncil?.status"
      class="rounded-full border border-sky-800/70 bg-sky-950/40 px-2.5 py-1 text-xs text-sky-200"
    >
      AI council · {{ aiCouncil.voices.length }} voice(s)
    </span>

    <span
      v-if="routing?.riskLevel"
      class="rounded-full border border-amber-800/60 bg-amber-950/30 px-2.5 py-1 text-xs text-amber-100"
    >
      Risk {{ routing.riskLevel }}
    </span>
  </section>
</template>
