<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ClarificationAnswer, ClarificationQuestion, ClarificationReviewDecision } from '~/types/clarification'

export type { ClarificationAnswer, ClarificationQuestion }
export type { ClarificationOption } from '~/types/clarification'

const props = defineProps<{
  runId: string
  stage?: string
  summary?: string
  assumptions?: string[]
  questions: ClarificationQuestion[]
  submitting?: boolean
  /** When true, render only question UI (for use inside ClarificationModal). */
  embedded?: boolean
}>()

const isLocalCommandsStage = computed(() => props.stage === 'user_local_commands')

const emit = defineEmits<{
  submit: [payload: {
    answers: ClarificationAnswer[]
    review_decision: ClarificationReviewDecision
    code_review_comment?: string
  }]
}>()

const selections = ref<Record<string, string>>({})
const freeText = ref<Record<string, string>>({})
const codeReviewComment = ref('')

function selectOption(questionId: string, optionId: string, label: string) {
  selections.value = { ...selections.value, [questionId]: optionId }
  if (props.questions.find(q => q.id === questionId)?.allow_free_text !== false) {
    freeText.value = { ...freeText.value, [questionId]: label }
  }
}

function buildAnswers(): ClarificationAnswer[] {
  return props.questions.map(q => ({
    question_id: q.id,
    option_id: selections.value[q.id] || undefined,
    free_text: (freeText.value[q.id] ?? '').trim() || undefined,
  }))
}

function canApprove() {
  return props.questions.every((q) => {
    const hasText = Boolean((freeText.value[q.id] ?? '').trim())
    if (isLocalCommandsStage.value) {
      return hasText
    }
    const hasOption = Boolean(selections.value[q.id])
    return hasOption || (q.allow_free_text !== false && hasText)
  })
}

function submit(decision: ClarificationReviewDecision) {
  const comment = codeReviewComment.value.trim()
  if (decision === 'request_changes' && !comment) {
    return
  }

  const answers = buildAnswers()
  if (decision === 'approve' && !canApprove() && !comment) {
    return
  }

  emit('submit', {
    answers,
    review_decision: decision,
    code_review_comment: decision === 'request_changes' ? comment : (comment || undefined),
  })
}
</script>

<template>
  <component
    :is="embedded ? 'div' : 'section'"
    :class="embedded ? 'space-y-4' : 'rounded-xl border border-amber-500/40 bg-amber-950/30 p-4 space-y-4'"
    data-testid="clarification-panel"
  >
    <div v-if="!embedded">
      <p class="text-xs font-semibold uppercase tracking-wider text-amber-400/90">
        Orchestrator needs your input
        <span v-if="stage" class="normal-case text-zinc-500">· {{ stage.replaceAll('_', ' ') }}</span>
      </p>
      <p v-if="summary" class="mt-1 text-sm text-zinc-200 leading-relaxed">
        {{ summary }}
      </p>
      <ul v-if="assumptions?.length" class="mt-2 list-disc pl-5 text-xs text-zinc-400 space-y-0.5">
        <li v-for="(item, idx) in assumptions" :key="idx">
          {{ item }}
        </li>
      </ul>
    </div>

    <ul v-else-if="assumptions?.length" class="list-disc pl-5 text-xs text-zinc-400 space-y-0.5">
      <li v-for="(item, idx) in assumptions" :key="idx">
        {{ item }}
      </li>
    </ul>

    <article
      v-for="(question, qIdx) in questions"
      :key="question.id"
      class="rounded-lg border border-zinc-700/80 bg-zinc-900/60 p-3 space-y-3"
    >
      <div>
        <p v-if="questions.length > 1" class="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 mb-1">
          Question {{ qIdx + 1 }} of {{ questions.length }}
        </p>
        <p
          v-if="!isLocalCommandsStage"
          class="text-sm font-medium text-zinc-100 leading-relaxed whitespace-pre-wrap"
        >
          {{ question.prompt }}
        </p>
        <template v-else>
          <p class="text-sm font-medium text-zinc-100 leading-relaxed">
            Run this on your machine (Bossku in Docker cannot run it in the container):
          </p>
          <pre
            class="mt-2 overflow-x-auto rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-emerald-200 font-mono select-all"
          >{{ question.prompt.split('\n\n').slice(1).join('\n\n') || question.prompt }}</pre>
        </template>
        <p v-if="question.why_it_matters" class="mt-1 text-xs text-zinc-500 leading-relaxed">
          {{ question.why_it_matters }}
        </p>
      </div>

      <div
        v-if="!isLocalCommandsStage"
        class="grid grid-cols-1 gap-2"
        :class="question.options.length <= 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3'"
      >
        <button
          v-for="opt in question.options.slice(0, 3)"
          :key="opt.id"
          type="button"
          class="rounded-lg border px-3 py-2.5 text-xs font-medium transition-colors text-left min-h-[3rem] flex flex-col justify-center"
          :class="selections[question.id] === opt.id
            ? 'border-amber-500 bg-amber-900/50 text-amber-100 ring-1 ring-amber-500/50'
            : 'border-zinc-600 bg-zinc-800/80 text-zinc-300 hover:border-zinc-500 hover:bg-zinc-800'"
          @click="selectOption(question.id, opt.id, opt.label)"
        >
          <span v-if="opt.recommendation" class="text-[10px] text-amber-400 mb-0.5">Recommended</span>
          {{ opt.label }}
        </button>
      </div>

      <label v-if="question.allow_free_text !== false" class="block">
        <span class="text-xs text-zinc-400">
          {{ isLocalCommandsStage ? 'Paste terminal output' : 'Your answer' }}
          <span v-if="!isLocalCommandsStage" class="text-zinc-600">(optional if you picked a choice above)</span>
        </span>
        <textarea
          v-if="isLocalCommandsStage"
          v-model="freeText[question.id]"
          rows="8"
          class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm font-mono text-zinc-100 placeholder:text-zinc-600"
          placeholder="Paste full stdout/stderr from your terminal here…"
          data-testid="clarification-input"
        />
        <input
          v-else
          v-model="freeText[question.id]"
          type="text"
          class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600"
          placeholder="Type your answer…"
          data-testid="clarification-input"
          @keydown.enter.exact.prevent="canApprove() && !submitting && submit('approve')"
        >
      </label>
    </article>

    <div class="space-y-2 border-t border-zinc-700/80 pt-3">
      <template v-if="!isLocalCommandsStage">
        <label class="block">
          <span class="text-xs text-zinc-400">Code review instructions</span>
          <span class="text-zinc-600 text-xs"> (required for request changes)</span>
          <textarea
            v-model="codeReviewComment"
            rows="3"
            data-testid="clarification-code-review"
            class="mt-1.5 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600"
            placeholder="Tell the executor what to change before continuing…"
          />
        </label>
      </template>
      <div class="grid grid-cols-1 gap-2" :class="isLocalCommandsStage ? '' : 'sm:grid-cols-2'">
        <button
          type="button"
          class="rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-40 hover:bg-amber-600"
          data-testid="clarification-approve"
          :disabled="submitting || (!canApprove() && !codeReviewComment.trim())"
          @click="submit('approve')"
        >
          {{ submitting ? 'Continuing…' : (isLocalCommandsStage ? 'Submit output & continue' : 'Approve & continue') }}
        </button>
        <button
          v-if="!isLocalCommandsStage"
          type="button"
          class="rounded-lg bg-red-900/80 px-4 py-2.5 text-sm font-medium text-red-100 border border-red-700 disabled:opacity-40 hover:bg-red-800/80"
          data-testid="clarification-request-changes"
          :disabled="submitting || !codeReviewComment.trim()"
          @click="submit('request_changes')"
        >
          {{ submitting ? 'Sending…' : 'Request changes' }}
        </button>
      </div>
    </div>
  </component>
</template>
