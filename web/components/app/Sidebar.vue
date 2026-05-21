<script setup lang="ts">
defineProps<{ open?: boolean }>()
const emit = defineEmits<{ toggle: [] }>()

const links = [
  { to: '/', label: '🏠 Chat' },
  { to: '/dashboard', label: '📊 Dashboard' },
  { to: '/runs', label: '▶ Runs' },
  { to: '/project', label: '📁 Project' },
  { to: '/agents', label: '🤖 Agents' },
  { to: '/skills', label: '⚡ Skills' },
  { to: '/memory', label: '🧠 Memory' },
  { to: '/brain', label: '🔬 Brain' },
  { to: '/knowledge-graph', label: '🕸 Knowledge Graph' },
  { to: '/skills-graph', label: '📈 Skills Graph' },
  { to: '/plugins', label: '🔌 Plugins' },
  { to: '/logs', label: '📋 Logs' },
  { to: '/usage', label: '💰 Usage' },
  { to: '/feedback', label: '💬 Feedback' },
  { to: '/soul', label: '✨ Soul' },
  { to: '/settings/providers', label: '⚙ Settings' },
]
</script>

<template>
  <!-- Desktop sidebar -->
  <aside class="hidden lg:flex flex-col w-[220px] min-h-screen bg-zinc-950 border-r border-zinc-800 fixed left-0 top-0 z-30">
    <div class="flex items-center justify-between px-4 h-12 border-b border-zinc-800 shrink-0">
      <span class="text-sm font-bold text-emerald-400 tracking-tight">BosskuAI</span>
    </div>
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
      <NuxtLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="flex items-center px-3 py-2 rounded-md text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
        active-class="bg-zinc-800 text-zinc-100 font-medium"
      >
        {{ link.label }}
      </NuxtLink>
    </nav>
  </aside>

  <!-- Mobile overlay -->
  <Teleport to="body">
    <div
      v-if="open"
      class="lg:hidden fixed inset-0 z-40 flex"
    >
      <div class="fixed inset-0 bg-black/60" @click="emit('toggle')" />
      <aside class="relative flex flex-col w-[220px] min-h-screen bg-zinc-950 border-r border-zinc-800 z-50">
        <div class="flex items-center justify-between px-4 h-12 border-b border-zinc-800 shrink-0">
          <span class="text-sm font-bold text-emerald-400 tracking-tight">BosskuAI</span>
          <button type="button" class="text-zinc-400 hover:text-zinc-100 text-xs" @click="emit('toggle')">✕</button>
        </div>
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
          <NuxtLink
            v-for="link in links"
            :key="link.to"
            :to="link.to"
            class="flex items-center px-3 py-2 rounded-md text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
            active-class="bg-zinc-800 text-zinc-100 font-medium"
            @click="emit('toggle')"
          >
            {{ link.label }}
          </NuxtLink>
        </nav>
      </aside>
    </div>
  </Teleport>
</template>
