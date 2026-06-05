<script setup lang="ts">
import type { Command } from './CommandBar.vue'
import { APP_COMMANDS } from '~/utils/appCommands'

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

const commands = computed<Command[]>(() => APP_COMMANDS.map(command => ({
  id: command.id,
  label: command.label,
  action: () => {
    if (command.action === 'toggle-logs') {
      logsOpen.value = !logsOpen.value
      return
    }
    if (command.to) {
      router.push(command.to)
    }
  },
})))

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

    <!-- Run-command popup (shown when a command must be run manually) -->
    <RunCommandPopup />

    <!-- First-time onboarding spotlight -->
    <OnboardingSpotlight />
  </div>
</template>
