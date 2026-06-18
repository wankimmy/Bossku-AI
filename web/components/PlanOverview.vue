<script setup lang="ts">
import { computed } from 'vue'
import type { PlanOverview } from '~/types/bossku'

const props = withDefaults(
  defineProps<{
    plan?: PlanOverview
    compact?: boolean
  }>(),
  { compact: false },
)

const hasPlan = computed(() => {
  const p = props.plan
  if (!p) return false
  return Boolean(
    p.goal
    || p.keyDesignDecisions.length
    || p.flowDiagram
    || p.flowSteps.length
    || p.notes.length
    || p.risks.length
    || p.todos.length
    || p.councilReview
    || p.staffCouncil
    || p.aiCouncil,
  )
})

const sectionClass = computed(() =>
  props.compact ? 'space-y-3' : 'space-y-5',
)

const headingClass = 'text-xs font-semibold uppercase tracking-wider text-zinc-500'
</script>

<template>
  <section
    v-if="hasPlan"
    class="rounded-lg border border-zinc-800 bg-zinc-900"
    :class="compact ? 'p-3' : 'p-4'"
    data-testid="plan-overview"
  >
    <header
      v-if="!compact"
      class="mb-4 border-b border-zinc-800 pb-3"
    >
      <h2 class="text-sm font-semibold text-zinc-100">
        Execution plan
      </h2>
      <p
        v-if="plan?.taskSummary && plan.taskSummary !== plan?.goal"
        class="mt-1 text-xs text-zinc-500"
      >
        {{ plan.taskSummary }}
      </p>
    </header>
    <p
      v-else
      class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-500/90"
    >
      Plan
    </p>

    <div :class="sectionClass">
      <div v-if="plan?.goal">
        <h3 :class="headingClass">
          Goal
        </h3>
        <p
          class="mt-1.5 text-sm leading-relaxed text-zinc-200"
          :class="compact ? 'line-clamp-3' : ''"
        >
          {{ plan.goal }}
        </p>
      </div>

      <div v-if="plan?.keyDesignDecisions?.length">
        <h3 :class="headingClass">
          Key design decisions
        </h3>
        <ul
          class="mt-1.5 list-disc space-y-1 pl-5 text-sm text-zinc-300"
          :class="compact ? 'max-h-28 overflow-y-auto' : ''"
        >
          <li
            v-for="(item, idx) in plan.keyDesignDecisions"
            :key="idx"
          >
            {{ item }}
          </li>
        </ul>
      </div>

      <div v-if="plan?.flowDiagram || plan?.flowSteps?.length">
        <h3 :class="headingClass">
          Flow
        </h3>
        <div class="mt-1.5">
          <ClientOnly>
            <MermaidDiagram
              :source="plan?.flowDiagram ?? ''"
              :fallback-steps="plan?.flowSteps ?? []"
            />
            <template #fallback>
              <ol
                v-if="plan?.flowSteps?.length"
                class="list-decimal space-y-1 pl-5 text-sm text-zinc-300"
              >
                <li
                  v-for="(step, idx) in plan.flowSteps"
                  :key="idx"
                >
                  {{ step }}
                </li>
              </ol>
            </template>
          </ClientOnly>
        </div>
      </div>

      <div v-if="plan?.notes?.length || plan?.risks?.length">
        <h3 :class="headingClass">
          Notes &amp; risks
        </h3>
        <div
          class="mt-1.5 space-y-2 text-sm text-zinc-300"
          :class="compact ? 'max-h-32 overflow-y-auto' : ''"
        >
          <div v-if="plan?.notes?.length">
            <p class="text-[11px] font-medium text-zinc-500">
              Notes
            </p>
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5">
              <li
                v-for="(note, idx) in plan.notes"
                :key="`n-${idx}`"
              >
                {{ note }}
              </li>
            </ul>
          </div>
          <div v-if="plan?.risks?.length">
            <p class="text-[11px] font-medium text-amber-500/80">
              Risks
            </p>
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5 text-amber-100/90">
              <li
                v-for="(risk, idx) in plan.risks"
                :key="`r-${idx}`"
              >
                {{ risk }}
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div v-if="plan?.councilReview">
        <h3 :class="headingClass">
          Council review
        </h3>
        <div
          class="mt-1.5 space-y-3 text-sm text-zinc-300"
          :class="compact ? 'max-h-48 overflow-y-auto' : ''"
        >
          <p
            v-if="plan.councilReview.consensus"
            class="leading-relaxed text-zinc-300"
          >
            {{ plan.councilReview.consensus }}
          </p>
          <div v-if="plan.councilReview.strongest_dissent">
            <p class="text-[11px] font-medium text-amber-500/80">
              Strongest dissent
            </p>
            <p class="mt-0.5 leading-relaxed text-amber-100/90">
              {{ plan.councilReview.strongest_dissent }}
            </p>
          </div>
          <div v-if="plan.councilReview.recommended_adjustments?.length">
            <p class="text-[11px] font-medium text-zinc-500">
              Recommended adjustments
            </p>
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5">
              <li
                v-for="(item, idx) in plan.councilReview.recommended_adjustments"
                :key="`ca-${idx}`"
              >
                {{ item }}
              </li>
            </ul>
          </div>
          <div v-if="plan.councilReview.stop_conditions?.length">
            <p class="text-[11px] font-medium text-zinc-500">
              Stop conditions
            </p>
            <ul class="mt-0.5 list-disc space-y-0.5 pl-5">
              <li
                v-for="(item, idx) in plan.councilReview.stop_conditions"
                :key="`cs-${idx}`"
              >
                {{ item }}
              </li>
            </ul>
          </div>
          <div v-if="plan.councilReview.voices?.length">
            <p class="text-[11px] font-medium text-zinc-500">
              Voices
            </p>
            <ul class="mt-0.5 space-y-1">
              <li
                v-for="voice in plan.councilReview.voices"
                :key="voice.id"
                class="leading-relaxed"
              >
                <span class="font-medium text-zinc-200">{{ voice.label }}:</span>
                <span> {{ voice.position }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <StaffCouncilPanel
        v-if="plan?.staffCouncil"
        :council="plan.staffCouncil"
        :compact="compact"
      />

      <AiCouncilPanel
        v-if="plan?.aiCouncil"
        :council="plan.aiCouncil"
        :compact="compact"
      />

      <div v-if="plan?.todos?.length">
        <h3 :class="headingClass">
          To-dos
        </h3>
        <div class="mt-2">
          <PlanChecklist
            :items="plan.todos"
            :embedded="true"
          />
        </div>
      </div>
    </div>
  </section>
  <UiEmptyState
    v-else
    title="No plan yet."
    hint="The orchestrator plan will appear after planning completes."
  />
</template>
