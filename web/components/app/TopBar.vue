<script setup lang="ts">
const emit = defineEmits<{ menuToggle: [] }>()
const { status: activeRunStatus, isActive, resumeUrl } = useActiveRun()
const { collapsed: sidebarCollapsed, toggle: toggleSidebar } = useSidebarCollapsed()
const { restartTour } = useOnboarding()
const helpOpen = ref(false)

function takeTour() {
  helpOpen.value = false
  restartTour()
}
</script>

<template>
  <header
    class="fixed top-0 left-0 right-0 z-20 h-12 bg-slate-900/85 backdrop-blur border-b border-slate-800 flex items-center px-4 gap-3 transition-[padding] duration-200 ease-out"
    :class="sidebarCollapsed ? 'lg:pl-4' : 'lg:pl-[236px]'"
  >
    <!-- Left: sidebar toggles + brand -->
    <div class="flex items-center gap-2">
      <button
        type="button"
        class="lg:hidden rounded-md border border-slate-700 p-1.5 text-slate-400 hover:bg-slate-800 hover:text-emerald-300"
        aria-label="Open menu"
        @click="emit('menuToggle')"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
        </svg>
      </button>
      <button
        type="button"
        class="hidden lg:flex rounded-md border border-slate-700 p-1.5 text-slate-400 hover:bg-slate-800 hover:text-emerald-300"
        :title="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'"
        :aria-label="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'"
        @click="toggleSidebar"
      >
        <svg
          v-if="sidebarCollapsed"
          class="h-4 w-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <svg
          v-else
          class="h-4 w-4"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <NuxtLink to="/dashboard" class="hud-brand text-sm lg:hidden">
        BosskuAI
      </NuxtLink>
    </div>

    <!-- Center: active run status -->
    <div class="flex-1 flex items-center justify-center">
      <NuxtLink
        v-if="isActive && activeRunStatus"
        :to="resumeUrl"
        class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-400/30 hover:bg-emerald-500/20"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
        Run active: {{ activeRunStatus }} — open chat
      </NuxtLink>
      <span
        v-else
        class="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-slate-500 ring-1 ring-slate-700/60"
      >
        No active run
      </span>
    </div>

    <!-- Help -->
    <div class="relative">
      <button
        type="button"
        class="rounded-md border border-slate-700 px-2.5 py-1 text-xs text-slate-400 hover:bg-slate-800 hover:text-emerald-300"
        aria-haspopup="true"
        :aria-expanded="helpOpen"
        @click="helpOpen = !helpOpen"
      >
        Help
      </button>
      <div
        v-if="helpOpen"
        class="absolute right-0 top-full mt-1 w-44 rounded-md border border-slate-700 bg-slate-900 py-1 shadow-lg z-50"
      >
        <button
          type="button"
          class="block w-full text-left px-3 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-emerald-300"
          @click="takeTour"
        >
          Take a tour
        </button>
      </div>
    </div>
  </header>
</template>
