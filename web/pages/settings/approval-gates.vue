<script setup lang="ts">
definePageMeta({ layout: 'default' })

const alwaysRequireApproval = [
  'file_delete',
  'database_migration',
  'deploy_to_production',
  'api_key_rotation',
  'infrastructure_change',
  'security_config_change',
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-zinc-100">Approval Gates</h1>
      <p class="text-sm text-zinc-500 mt-1">Operation types that always require human approval.</p>
    </div>

    <div class="rounded-lg bg-zinc-900 border border-zinc-800 overflow-hidden">
      <div class="px-4 py-3 border-b border-zinc-800">
        <h3 class="text-sm font-semibold text-zinc-100">Always-approve operation types</h3>
      </div>
      <ul class="divide-y divide-zinc-800">
        <li
          v-for="op in alwaysRequireApproval"
          :key="op"
          class="flex items-center justify-between px-4 py-3"
        >
          <span class="font-mono text-sm text-zinc-300">{{ op }}</span>
          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs border bg-yellow-900/50 text-yellow-300 border-yellow-800">
            Required
          </span>
        </li>
      </ul>
    </div>

    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-4">
      <p class="text-sm text-zinc-500">Future: editable approval gate policy editor with risk level thresholds.</p>
    </div>
  </div>
</template>
