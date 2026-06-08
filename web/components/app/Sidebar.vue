<script setup lang="ts">
import { SIDEBAR_GROUPS } from '~/utils/sidebarLinks'

defineProps<{ open?: boolean }>()
const emit = defineEmits<{ toggle: [] }>()

const { collapsed, toggle: toggleCollapsed } = useSidebarCollapsed()

const groups = SIDEBAR_GROUPS
</script>

<template>
  <!-- Desktop sidebar -->
  <aside
    class="hidden lg:flex flex-col w-[220px] min-h-screen bg-gradient-to-b from-slate-950 via-slate-950 to-slate-900 border-r border-slate-800/80 fixed left-0 top-0 z-30 transition-transform duration-200 ease-out"
    :class="collapsed ? '-translate-x-full' : 'translate-x-0'"
  >
    <div class="flex items-center justify-between gap-2 px-3 h-12 border-b border-slate-800/80 shrink-0">
      <NuxtLink to="/dashboard" class="flex items-center gap-2 min-w-0">
        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-gradient-to-br from-emerald-400 to-cyan-500 text-[11px] font-black text-slate-950 shadow-[0_0_12px_-2px_rgba(34,211,238,0.7)]">B</span>
        <span class="hud-brand text-sm truncate">BosskuAI</span>
      </NuxtLink>
      <button
        type="button"
        class="shrink-0 rounded-md border border-slate-700 p-1.5 text-slate-400 hover:bg-slate-800 hover:text-emerald-300"
        title="Hide sidebar"
        aria-label="Hide sidebar"
        @click="toggleCollapsed"
      >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
    </div>
    <nav class="hud-scroll flex-1 overflow-y-auto px-2 pb-4">
      <div v-for="group in groups" :key="group.title">
        <p class="hud-section">{{ group.title }}</p>
        <div class="space-y-0.5">
          <NuxtLink
            v-for="link in group.links"
            :key="link.to"
            :to="link.to"
            :data-tour="link.tourId"
            class="hud-link"
          >
            <span class="hud-slot" aria-hidden="true">{{ link.icon }}</span>
            <span class="truncate">{{ link.label }}</span>
          </NuxtLink>
        </div>
      </div>
    </nav>
  </aside>

  <!-- Mobile overlay -->
  <Teleport to="body">
    <div
      v-if="open"
      class="lg:hidden fixed inset-0 z-40 flex"
    >
      <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="emit('toggle')" />
      <aside class="relative flex flex-col w-[230px] min-h-screen bg-gradient-to-b from-slate-950 via-slate-950 to-slate-900 border-r border-slate-800/80 z-50">
        <div class="flex items-center justify-between px-4 h-12 border-b border-slate-800/80 shrink-0">
          <div class="flex items-center gap-2">
            <span class="grid h-6 w-6 place-items-center rounded-md bg-gradient-to-br from-emerald-400 to-cyan-500 text-[11px] font-black text-slate-950">B</span>
            <span class="hud-brand text-sm">BosskuAI</span>
          </div>
          <button type="button" class="text-slate-400 hover:text-emerald-300 text-sm" aria-label="Close menu" @click="emit('toggle')">✕</button>
        </div>
        <nav class="hud-scroll flex-1 overflow-y-auto px-2 pb-4">
          <div v-for="group in groups" :key="group.title">
            <p class="hud-section">{{ group.title }}</p>
            <div class="space-y-0.5">
              <NuxtLink
                v-for="link in group.links"
                :key="link.to"
                :to="link.to"
                :data-tour="link.tourId"
                class="hud-link"
                @click="emit('toggle')"
              >
                <span class="hud-slot" aria-hidden="true">{{ link.icon }}</span>
                <span class="truncate">{{ link.label }}</span>
              </NuxtLink>
            </div>
          </div>
        </nav>
      </aside>
    </div>
  </Teleport>
</template>
