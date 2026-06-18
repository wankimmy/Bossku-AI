<script setup lang="ts">
import { computed } from 'vue'
import type { StaffCouncilReview } from '~/types/bossku'

const props = withDefaults(
  defineProps<{
    council?: StaffCouncilReview
    compact?: boolean
  }>(),
  { compact: false },
)

const hasContent = computed(() => {
  const council = props.council
  if (!council) return false
  return Boolean(
    council.consensus
    || council.voices.length
    || council.staff_recommendations.length
    || council.issue_breakdown.length
    || council.stop_conditions.length,
  )
})

const bodyClass = computed(() =>
  props.compact ? 'max-h-64 overflow-y-auto space-y-3' : 'space-y-4',
)
</script>

<template>
  <section
    v-if="hasContent"
    class="rounded-md border border-emerald-900/50 bg-emerald-950/20 p-3"
    data-testid="staff-council-panel"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-300">
        Staff council
      </h3>
      <span
        v-if="council?.status"
        class="rounded border border-emerald-800/70 bg-emerald-950 px-2 py-0.5 text-[11px] text-emerald-200"
      >
        {{ council.status }}
      </span>
    </div>

    <div
      class="mt-3 text-sm text-zinc-300"
      :class="bodyClass"
    >
      <p
        v-if="council?.consensus"
        class="leading-relaxed text-zinc-200"
      >
        {{ council.consensus }}
      </p>

      <div v-if="council?.voices?.length">
        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          Voices
        </p>
        <ul class="mt-1.5 space-y-2">
          <li
            v-for="voice in council.voices"
            :key="voice.role_slug"
            class="rounded border border-zinc-800 bg-zinc-950/60 p-2"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="font-medium text-zinc-100">
                {{ voice.display_name }}
              </p>
              <span
                v-if="voice.runtime_mode"
                class="rounded bg-zinc-900 px-1.5 py-0.5 text-[11px] text-zinc-400"
              >
                {{ voice.runtime_mode }}
              </span>
            </div>
            <p
              v-if="voice.position"
              class="mt-1 leading-relaxed text-zinc-300"
            >
              {{ voice.position }}
            </p>
            <ul
              v-if="voice.recommendations?.length"
              class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-zinc-400"
            >
              <li
                v-for="(item, idx) in voice.recommendations"
                :key="`${voice.role_slug}-rec-${idx}`"
              >
                {{ item }}
              </li>
            </ul>
          </li>
        </ul>
      </div>

      <div v-if="council?.staff_recommendations?.length">
        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          Recommendations
        </p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
          <li
            v-for="(item, idx) in council.staff_recommendations"
            :key="`staff-rec-${idx}`"
          >
            {{ item }}
          </li>
        </ul>
      </div>

      <div v-if="council?.issue_breakdown?.length">
        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          Issue breakdown
        </p>
        <ul class="mt-1 space-y-1.5">
          <li
            v-for="issue in council.issue_breakdown"
            :key="issue.plan_item_id"
            class="flex flex-wrap items-center justify-between gap-2 rounded border border-zinc-800 bg-zinc-950/50 px-2 py-1.5"
          >
            <span class="min-w-0 flex-1 truncate text-zinc-200">{{ issue.title }}</span>
            <span class="text-xs text-zinc-500">{{ issue.assignee_role_slug }}</span>
            <span class="rounded bg-zinc-900 px-1.5 py-0.5 text-[11px] text-zinc-400">
              {{ issue.priority }}
            </span>
          </li>
        </ul>
      </div>

      <div v-if="council?.stop_conditions?.length">
        <p class="text-[11px] font-medium uppercase tracking-wider text-amber-400/90">
          Stop conditions
        </p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-amber-100/90">
          <li
            v-for="(item, idx) in council.stop_conditions"
            :key="`staff-stop-${idx}`"
          >
            {{ item }}
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
