<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()

const tabs = [
  { to: '/settings/models', label: 'Ollama & Models' },
  { to: '/settings/providers', label: 'Providers' },
  { to: '/settings/model-routing', label: 'Model Routing' },
  { to: '/settings/governance', label: 'Governance' },
  { to: '/settings/approval-gates', label: 'Approval Gates' },
  { to: '/settings/learning', label: 'Learning' },
  { to: '/settings/secrets', label: 'Secrets' },
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-semibold text-zinc-100">
        Settings
      </h1>
      <p class="mt-1 text-sm text-zinc-400">
        Configure Ollama models, providers, and governance.
      </p>
    </div>

    <nav class="flex flex-wrap gap-1 rounded-lg border border-zinc-800 bg-zinc-900 p-1" aria-label="Settings sections">
      <NuxtLink
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
        :class="route.path === tab.to || route.path.startsWith(tab.to + '/')
          ? 'bg-zinc-800 text-zinc-100'
          : 'text-zinc-500 hover:text-zinc-300'"
      >
        {{ tab.label }}
      </NuxtLink>
    </nav>

    <NuxtPage />
  </div>
</template>
