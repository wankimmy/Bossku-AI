const STORAGE_KEY = 'bossku-sidebar-collapsed'

export function useSidebarCollapsed() {
  const collapsed = useState<boolean>('bossku-sidebar-collapsed', () => false)
  const hydrated = useState('bossku-sidebar-collapsed-hydrated', () => false)

  function hydrate() {
    if (!import.meta.client || hydrated.value) return
    try {
      const stored = localStorage.getItem(STORAGE_KEY)
      if (stored === '1') collapsed.value = true
      if (stored === '0') collapsed.value = false
    }
    catch {
      //
    }
    hydrated.value = true
  }

  function persist(value: boolean) {
    if (!import.meta.client) return
    try {
      localStorage.setItem(STORAGE_KEY, value ? '1' : '0')
    }
    catch {
      //
    }
  }

  function toggle() {
    collapsed.value = !collapsed.value
    persist(collapsed.value)
  }

  function setCollapsed(value: boolean) {
    collapsed.value = value
    persist(value)
  }

  onMounted(hydrate)

  return {
    collapsed,
    toggle,
    setCollapsed,
  }
}
