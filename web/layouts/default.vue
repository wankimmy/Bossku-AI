<script setup lang="ts">
const route = useRoute()
const dark = useState('dark-mode', () => false)
const mobileNavOpen = ref(false)

watch(
  () => route.fullPath,
  () => {
    mobileNavOpen.value = false
  },
)

onMounted(() => {
  if (import.meta.client) {
    dark.value = document.documentElement.classList.contains('dark')
  }
})

function toggleDark() {
  dark.value = !dark.value
  if (import.meta.client) {
    document.documentElement.classList.toggle('dark', dark.value)
  }
}

const links = [
  { to: '/', label: 'Chat' },
  { to: '/skills', label: 'Skills' },
  { to: '/rules', label: 'Rules' },
  { to: '/playbooks', label: 'Playbooks' },
  { to: '/checklists', label: 'Checklists' },
  { to: '/memory', label: 'Memory' },
  { to: '/runs', label: 'History' },
  { to: '/settings', label: 'Settings' },
]
</script>

<template>
  <div class="min-h-screen flex flex-col bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
      <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-3">
        <NuxtLink to="/" class="text-base font-semibold tracking-tight">
          BosskuAI
        </NuxtLink>
        <nav class="hidden flex-wrap items-center gap-x-5 gap-y-2 text-sm md:flex" aria-label="Main">
          <NuxtLink
            v-for="l in links"
            :key="l.to"
            :to="l.to"
            class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            active-class="text-emerald-700 dark:text-emerald-400 font-medium"
          >
            {{ l.label }}
          </NuxtLink>
        </nav>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium hover:bg-zinc-100 md:hidden dark:border-zinc-700 dark:hover:bg-zinc-900"
            :aria-expanded="mobileNavOpen"
            aria-controls="mobile-nav-panel"
            @click="mobileNavOpen = !mobileNavOpen"
          >
            Menu
          </button>
          <button
            type="button"
            class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-900"
            @click="toggleDark"
          >
            Theme
          </button>
        </div>
      </div>

      <!-- Full primary nav on small screens (header nav is md+ only). -->
      <div
        v-show="mobileNavOpen"
        id="mobile-nav-panel"
        class="border-t border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950 md:hidden"
        role="dialog"
        aria-label="Site navigation"
      >
        <nav class="flex flex-col gap-1 text-sm" aria-label="Main mobile">
          <NuxtLink
            v-for="l in links"
            :key="l.to"
            :to="l.to"
            class="rounded-md px-2 py-2 text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900"
            active-class="bg-emerald-50 font-medium text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300"
            @click="mobileNavOpen = false"
          >
            {{ l.label }}
          </NuxtLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col px-4 py-6">
      <slot />
    </main>
  </div>
</template>
