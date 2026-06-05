<script setup lang="ts">
import { computed, ref } from 'vue'
import type { FinalResult } from '../types/bossku'

const props = defineProps<{ result: FinalResult }>()

const copied = ref(false)

const displayPrompt = computed(() => (props.result.nextPrompt ?? props.result.nextStep ?? '').trim())

const showGuidanceLine = computed(() => {
  const step = (props.result.nextStep ?? '').trim()
  const prompt = displayPrompt.value
  return step !== '' && step !== prompt
})

async function copyNextPrompt() {
  const text = displayPrompt.value
  if (!text || !navigator?.clipboard) return
  await navigator.clipboard.writeText(text)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1200)
}
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-semibold">
        Final result
      </h2>
      <span class="rounded border border-zinc-300 px-2 py-0.5 font-mono text-xs dark:border-zinc-700">{{ result.status || 'pending' }}</span>
    </div>
    <p v-if="result.summary" class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">
      {{ result.summary }}
    </p>
    <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Files changed
        </dt>
        <dd class="mt-1">
          <ul v-if="result.filesChanged.length" class="space-y-1 font-mono text-xs">
            <li v-for="file in result.filesChanged" :key="file">
              {{ file }}
            </li>
          </ul>
          <span v-else class="text-zinc-500">None recorded</span>
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Commands executed
        </dt>
        <dd class="mt-1">
          <ul v-if="result.checksRun.length" class="space-y-1 font-mono text-xs">
            <li v-for="check in result.checksRun" :key="check">
              {{ check }}
            </li>
          </ul>
          <span v-else class="text-zinc-500">None recorded</span>
        </dd>
      </div>
      <div v-if="result.gitStatusAfter" class="md:col-span-2">
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Git status after commands
        </dt>
        <dd class="mt-1 whitespace-pre-wrap font-mono text-xs text-zinc-600 dark:text-zinc-400">
          {{ result.gitStatusAfter || 'Clean working tree' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Audit result
        </dt>
        <dd class="mt-1 capitalize">
          {{ (result.auditResult || 'not recorded').replaceAll('_', ' ') }}
        </dd>
      </div>
      <div class="md:col-span-2">
        <dt class="text-xs font-semibold uppercase text-zinc-500">
          Next step
        </dt>
        <dd class="mt-1 space-y-2">
          <p v-if="showGuidanceLine" class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ result.nextStep }}
          </p>
          <div
            v-if="displayPrompt"
            class="rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950"
          >
            <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-2 py-1.5 dark:border-zinc-700">
              <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">
                Next action prompt
              </span>
              <button
                type="button"
                data-testid="final-next-prompt-copy"
                class="inline-flex items-center gap-1 rounded border border-zinc-300 px-2 py-0.5 text-xs text-zinc-600 hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800"
                :disabled="!displayPrompt"
                :title="copied ? 'Copied' : 'Copy prompt'"
                @click="copyNextPrompt"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  class="h-3.5 w-3.5"
                  aria-hidden="true"
                >
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
                <span>{{ copied ? 'Copied' : 'Copy' }}</span>
              </button>
            </div>
            <pre class="max-h-40 overflow-auto whitespace-pre-wrap break-words p-3 font-mono text-xs leading-relaxed text-zinc-800 dark:text-zinc-200">{{ displayPrompt }}</pre>
          </div>
          <span v-else class="text-zinc-500">No next step recorded.</span>
        </dd>
      </div>
    </dl>
    <div v-if="result.remainingRisks.length" class="mt-4">
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Remaining risks
      </h3>
      <RiskItemList class="mt-2" :risks="result.remainingRisks" />
    </div>
  </section>
</template>
