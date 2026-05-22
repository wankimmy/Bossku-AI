<script setup lang="ts">
import { onUnmounted, watch } from 'vue'
import type { ClarificationAnswer, ClarificationRequest } from '~/types/clarification'

const props = defineProps<{
  open: boolean
  request: ClarificationRequest
  submitting?: boolean
}>()

const emit = defineEmits<{
  submit: [answers: ClarificationAnswer[]]
}>()

watch(
  () => props.open,
  (isOpen) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = isOpen ? 'hidden' : ''
  },
  { immediate: true },
)

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="clarification-modal-title"
      data-testid="clarification-modal"
    >
      <div
        class="flex w-full max-w-lg max-h-[90vh] flex-col overflow-hidden rounded-xl border border-amber-500/50 bg-zinc-900 shadow-2xl"
        @click.stop
      >
        <div class="shrink-0 border-b border-zinc-700 px-5 py-4">
          <div class="flex items-start gap-2">
            <span class="text-lg text-amber-400" aria-hidden="true">?</span>
            <div class="min-w-0 flex-1">
              <h2 id="clarification-modal-title" class="text-sm font-semibold text-zinc-100">
                BosskuAI needs your input
              </h2>
              <p v-if="request.stage" class="mt-0.5 text-xs text-zinc-500 capitalize">
                {{ request.stage.replaceAll('_', ' ') }}
              </p>
              <p v-if="request.from_agent" class="mt-1 text-xs text-amber-400/90">
                From {{ request.from_agent }}
                <span v-if="request.origin && request.origin !== request.stage">
                  · {{ request.origin.replaceAll('_', ' ') }}
                </span>
              </p>
              <p v-if="request.summary" class="mt-2 text-sm text-zinc-300 leading-relaxed">
                {{ request.summary }}
              </p>
            </div>
          </div>
        </div>

        <div
          v-if="request.proof && (request.proof.proof_files?.length || request.proof.blockers?.length || request.proof.findings?.length)"
          class="shrink-0 border-b border-zinc-700 px-5 py-3"
        >
          <details class="group">
            <summary class="cursor-pointer text-xs font-medium text-zinc-400 hover:text-zinc-200">
              Why I'm asking (proof)
            </summary>
            <div class="mt-2 space-y-2 text-xs text-zinc-400">
              <div v-if="request.proof.proof_files?.length">
                <span class="text-zinc-500">Files:</span>
                <span
                  v-for="path in request.proof.proof_files.slice(0, 8)"
                  :key="path"
                  class="ml-1 inline-block rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-zinc-300"
                >{{ path }}</span>
              </div>
              <ul v-if="request.proof.blockers?.length" class="list-disc pl-4 space-y-0.5">
                <li v-for="(b, i) in request.proof.blockers" :key="i">{{ b }}</li>
              </ul>
              <ul v-if="request.proof.findings?.length" class="list-disc pl-4 space-y-0.5">
                <li
                  v-for="(f, i) in (request.proof.findings as Record<string, unknown>[]).slice(0, 5)"
                  :key="i"
                >
                  {{ f.title || f.description }}
                </li>
              </ul>
            </div>
          </details>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
          <ClarificationPanel
            embedded
            :run-id="request.runId"
            :stage="request.stage"
            :assumptions="request.assumptions"
            :questions="request.questions"
            :submitting="submitting"
            @submit="emit('submit', $event)"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>
