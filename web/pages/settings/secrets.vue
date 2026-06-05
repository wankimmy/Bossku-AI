<script setup lang="ts">
definePageMeta({ layout: 'default' })
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-zinc-100">Secrets</h1>
      <p class="text-sm text-zinc-500 mt-1">API key management.</p>
    </div>
    <div class="rounded-lg bg-zinc-900 border border-zinc-800 p-6">
      <p class="text-sm text-zinc-400">
        API keys are stored encrypted in the database or loaded from environment variables.
        Edit providers to update keys.
      </p>
      <NuxtLink to="/settings/providers" class="mt-4 inline-block text-sm text-emerald-400 hover:underline">
        → Go to Providers
      </NuxtLink>
    </div>
  </div>
</template>
