<script setup lang="ts">
const emit = defineEmits<{ menuToggle: [] }>()

const dark = useState('dark-mode', () => false)
const { status: activeRunStatus } = useActiveRun()

function toggleDark() {
  dark.value = !dark.value
  if (import.meta.client) {
    document.documentElement.classList.toggle('dark', dark.value)
  }
}

onMounted(() => {
  if (import.meta.client) {
    dark.value = document.documentElement.classList.contains('dark')
  }
})
</script>

<template>
  <header class="fixed top-0 left-0 right-0 z-20 h-12 bg-zinc-900 border-b border-zinc-800 flex items-center px-4 gap-4 lg:pl-[236px]">
    <!-- Left: brand + mobile menu -->
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="lg:hidden text-zinc-400 hover:text-zinc-100 text-sm"
        @click="emit('menuToggle')"
      >
        ☰
      </button>
      <NuxtLink to="/dashboard" class="text-sm font-bold text-emerald-400 tracking-tight lg:hidden">
        BosskuAI
      </NuxtLink>
    </div>

    <!-- Center: active run status -->
    <div class="flex-1 flex items-center justify-center">
      <span
        class="text-xs px-2 py-1 rounded-full"
        :class="activeRunStatus ? 'bg-blue-900/50 text-blue-300' : 'bg-zinc-800 text-zinc-500'"
      >
        {{ activeRunStatus ? `Run active: ${activeRunStatus}` : 'No active run' }}
      </span>
    </div>

    <!-- Right: theme toggle -->
    <div class="flex items-center gap-2">
      <button
        type="button"
        class="flex items-center gap-1.5 text-xs px-2 py-1 rounded-md border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
        @click="toggleDark"
      >
        {{ dark ? '☀ light' : '🌙 dark' }}
      </button>
    </div>
  </header>
</template>
