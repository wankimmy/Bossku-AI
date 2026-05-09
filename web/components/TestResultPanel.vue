<script setup lang="ts">
import type { CommandRun, TestRun } from '../types/bossku'

defineProps<{ commandsRun: CommandRun[]; testsRun: TestRun[] }>()
</script>

<template>
  <section class="space-y-3">
    <div>
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Commands run
      </h3>
      <ul v-if="commandsRun.length" class="mt-2 space-y-2">
        <li v-for="(command, idx) in commandsRun" :key="idx" class="rounded-md border border-zinc-200 p-2 text-sm dark:border-zinc-800">
          <div class="font-mono text-xs">
            {{ command.command }}
          </div>
          <div class="mt-1 text-xs text-zinc-500">
            {{ command.status }}<span v-if="command.exit_code !== undefined"> · exit {{ command.exit_code }}</span><span v-if="command.duration_ms"> · {{ command.duration_ms }}ms</span>
          </div>
          <p v-if="command.output_summary" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ command.output_summary }}
          </p>
        </li>
      </ul>
      <p v-else class="mt-2 text-sm text-zinc-500">
        No commands recorded.
      </p>
    </div>
    <div>
      <h3 class="text-xs font-semibold uppercase text-zinc-500">
        Tests run
      </h3>
      <ul v-if="testsRun.length" class="mt-2 space-y-2">
        <li v-for="(test, idx) in testsRun" :key="idx" class="rounded-md border border-zinc-200 p-2 text-sm dark:border-zinc-800">
          <div class="flex items-center justify-between gap-2">
            <span class="font-medium">{{ test.name }}</span>
            <span class="font-mono text-xs text-zinc-500">{{ test.status }}</span>
          </div>
          <p v-if="test.summary" class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ test.summary }}
          </p>
        </li>
      </ul>
      <p v-else class="mt-2 text-sm text-zinc-500">
        No test results recorded.
      </p>
    </div>
  </section>
</template>
