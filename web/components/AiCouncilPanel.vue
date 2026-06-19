<script setup lang="ts">
import { computed } from 'vue'
import type { AiCouncilReview } from '~/types/bossku'

const props = withDefaults(
  defineProps<{
    council?: AiCouncilReview
    compact?: boolean
  }>(),
  { compact: false },
)

const hasContent = computed(() => {
  const council = props.council
  if (!council) return false
  return Boolean(
    council.consensus
    || council.reason
    || council.voices.length
    || (council.questions?.length ?? 0) > 0,
  )
})

const bodyClass = computed(() =>
  props.compact ? 'max-h-64 overflow-y-auto space-y-3' : 'space-y-4',
)
</script>

<template>
  <section
    v-if="hasContent"
    class="rounded-md border border-sky-900/50 bg-sky-950/20 p-3"
    data-testid="ai-council-panel"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-xs font-semibold uppercase tracking-wider text-sky-300">
        AI council
      </h3>
      <span
        v-if="council?.status"
        class="rounded border border-sky-800/70 bg-sky-950 px-2 py-0.5 text-[11px] text-sky-200"
      >
        {{ council.status }}
      </span>
    </div>

    <div
      class="mt-3 text-sm text-zinc-300"
      :class="bodyClass"
    >
      <p
        v-if="council?.intent"
        class="text-[11px] uppercase tracking-wider text-zinc-500"
      >
        Intent: {{ council.intent }}
      </p>

      <p
        v-if="council?.consensus"
        class="leading-relaxed text-zinc-200"
      >
        {{ council.consensus }}
      </p>

      <p
        v-else-if="council?.reason"
        class="leading-relaxed text-zinc-300"
      >
        {{ council.reason }}
      </p>

      <div v-if="council?.voices?.length">
        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          Staff voices
        </p>
        <ul class="mt-1.5 space-y-2">
          <li
            v-for="(voice, index) in council.voices"
            :key="`${voice.role_slug}-${index}`"
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
              v-if="voice.critique"
              class="mt-1 leading-relaxed text-zinc-300"
            >
              {{ voice.critique }}
            </p>
          </li>
        </ul>
      </div>

      <div v-if="council?.questions?.length">
        <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          Questions
        </p>
        <ul class="mt-1.5 space-y-2">
          <li
            v-for="(question, index) in council.questions"
            :key="String(question.id ?? index)"
            class="rounded border border-zinc-800 bg-zinc-950/60 p-2 text-xs text-zinc-300"
          >
            {{ question.prompt ?? question.id }}
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
