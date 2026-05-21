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
              <p v-if="request.summary" class="mt-2 text-sm text-zinc-300 leading-relaxed">
                {{ request.summary }}
              </p>
            </div>
          </div>
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
