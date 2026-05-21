<script setup lang="ts">
import type { Command } from './CommandBar.vue'

const route = useRoute()
const { collapsed: sidebarCollapsed } = useSidebarCollapsed()
const sidebarOpen = ref(false)
const onboarding = useOnboarding()
const isGraphLayout = computed(() => String(route.meta.layout ?? '') === 'graph' || /-graph$/.test(route.path))
const inspectorOpen = ref(false)
const inspectorTitle = ref('Inspector')
const logsOpen = ref(false)
const commandBarOpen = ref(false)

const router = useRouter()

const commands: Command[] = [
  { id: 'dashboard', label: '📊 Go to Dashboard', action: () => router.push('/dashboard') },
  { id: 'runs', label: '▶ Go to Runs', action: () => router.push('/runs') },
  { id: 'project', label: '📁 Go to Project', action: () => router.push('/project') },
  { id: 'agents', label: '🤖 Go to Agents', action: () => router.push('/agents') },
  { id: 'skills', label: '⚡ Go to Skills', action: () => router.push('/skills') },
  { id: 'memory', label: '🧠 Go to Memory', action: () => router.push('/memory') },
  { id: 'brain', label: '🔬 Go to Brain', action: () => router.push('/brain') },
  { id: 'plugins', label: '🔌 Go to Plugins', action: () => router.push('/plugins') },
  { id: 'logs', label: '📋 Go to Logs', action: () => router.push('/logs') },
  { id: 'usage', label: '💰 Go to Usage', action: () => router.push('/usage') },
  { id: 'feedback', label: '💬 Go to Feedback', action: () => router.push('/feedback') },
  { id: 'soul', label: '✨ Go to Soul', action: () => router.push('/soul') },
  { id: 'settings', label: '⚙ Go to Settings', action: () => router.push('/settings/providers') },
  { id: 'toggle-logs', label: '📋 Toggle Log Drawer', action: () => { logsOpen.value = !logsOpen.value } },
]

function onKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    commandBarOpen.value = !commandBarOpen.value
  }
}

onMounted(() => {
  if (import.meta.client) {
    document.addEventListener('keydown', onKeydown)
    onboarding.registerOpenSidebar(() => {
      sidebarOpen.value = true
    })
    onboarding.maybeAutoStart(300)
  }
})

onUnmounted(() => {
  if (import.meta.client) {
    document.removeEventListener('keydown', onKeydown)
  }
})
</script>

<template>
  <div class="min-h-screen bg-zinc-950 text-zinc-100">
    <!-- Sidebar -->
    <AppSidebar :open="sidebarOpen" @toggle="sidebarOpen = !sidebarOpen" />

    <!-- Top bar -->
    <AppTopBar @menu-toggle="sidebarOpen = !sidebarOpen" />

    <!-- Main content area -->
    <div
      class="pt-12 min-h-screen transition-[padding] duration-200 ease-out"
      :class="[
        sidebarCollapsed ? 'lg:pl-0' : 'lg:pl-[220px]',
        isGraphLayout ? 'pb-0' : 'pb-10',
      ]"
    >
      <main
        :class="isGraphLayout
          ? 'flex h-[calc(100vh-3rem)] min-h-0 flex-col overflow-hidden p-0'
          : 'p-6'"
      >
        <slot />
      </main>
    </div>

    <!-- Bottom log drawer -->
    <AppBottomLogDrawer v-model:open="logsOpen" />

    <!-- Inspector panel -->
    <AppInspectorPanel
      :open="inspectorOpen"
      :title="inspectorTitle"
      @close="inspectorOpen = false"
    >
      <slot name="inspector" />
    </AppInspectorPanel>

    <!-- Command bar -->
    <AppCommandBar
      v-if="commandBarOpen"
      :commands="commands"
      @close="commandBarOpen = false"
    />

    <!-- Toast notifications -->
    <AppToastContainer />

    <!-- First-time onboarding spotlight -->
    <OnboardingSpotlight />
  </div>
</template>
