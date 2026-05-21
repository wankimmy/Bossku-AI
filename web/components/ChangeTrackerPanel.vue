<script setup lang="ts">
import type { CommandRun, FileChange, FileRead, TestRun } from '../types/bossku'

defineProps<{
  filesRead: FileRead[]
  filesChanged: FileChange[]
  commandsRun: CommandRun[]
  testsRun: TestRun[]
}>()
</script>

<template>
  <section class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
    <h2 class="text-sm font-semibold">
      Changes
    </h2>
    <div class="mt-3 space-y-4">
      <div>
        <h3 class="text-xs font-semibold uppercase text-zinc-500">
          Files inspected
        </h3>
        <ul v-if="filesRead.length" class="mt-2 space-y-1 text-sm">
          <li v-for="file in filesRead" :key="file.path" class="rounded-md bg-zinc-50 px-2 py-1.5 dark:bg-zinc-950">
            <span class="font-mono text-xs">{{ file.path }}</span>
            <span v-if="file.reason" class="ml-1 text-zinc-500">· {{ file.reason }}</span>
          </li>
        </ul>
        <p v-else class="mt-2 text-sm text-zinc-500">
          No inspected files recorded.
        </p>
      </div>
      <div>
        <h3 class="text-xs font-semibold uppercase text-zinc-500">
          Files changed
        </h3>
        <div v-if="filesChanged.length" class="mt-2 space-y-3">
          <article
            v-for="file in filesChanged"
            :key="`${file.change_type}:${file.path}`"
            class="rounded-md border p-2 dark:border-zinc-800"
            :class="file.change_type === 'created'
              ? 'border-emerald-700/40 bg-emerald-950/10'
              : 'border-zinc-200'"
          >
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded border border-zinc-300 px-1.5 py-0.5 text-[11px] dark:border-zinc-700">{{ file.change_type }}</span>
              <span class="font-mono text-xs">{{ file.path }}</span>
            </div>
            <p v-if="file.summary" class="mt-2 text-sm">
              {{ file.summary }}
            </p>
            <p v-if="file.why" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              {{ file.why }}
            </p>
            <FileDiffViewer
              class="mt-2"
              :path="file.path"
              :change-type="file.change_type"
              :diff="file.diff"
              :after="file.after"
              :before="file.before"
            />
          </article>
        </div>
        <p v-else class="mt-2 text-sm text-zinc-500">
          No file changes recorded.
        </p>
      </div>
      <TestResultPanel :commands-run="commandsRun" :tests-run="testsRun" />
    </div>
  </section>
</template>
