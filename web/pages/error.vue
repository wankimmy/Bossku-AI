<script setup lang="ts">
definePageMeta({ layout: false })
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-zinc-50 px-4 text-center dark:bg-zinc-950">
    <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
      Something went wrong.
    </p>
    <p class="max-w-md text-sm text-zinc-600 dark:text-zinc-400">
      Use the nav to return, or check the API and Ollama endpoints.
    </p>
    <NuxtLink class="text-sm font-medium text-emerald-700 dark:text-emerald-400" to="/">
      Back to run
    </NuxtLink>
  </div>
</template>
