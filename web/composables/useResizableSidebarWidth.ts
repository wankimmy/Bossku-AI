import { onMounted, ref } from 'vue'

const STORAGE_KEY = 'bossku-office-sidebar-width-v2'

export const OFFICE_SIDEBAR_MIN_WIDTH = 280
export const OFFICE_SIDEBAR_MAX_WIDTH_PX = 960
export const OFFICE_SIDEBAR_DEFAULT_VIEWPORT_FRACTION = 0.5
export const OFFICE_SIDEBAR_MAX_VIEWPORT_FRACTION = 0.65

/** Default sidebar width: half the viewport (50/50 split), clamped to min/max. */
export function defaultOfficeSidebarWidth(
  viewportWidth = typeof window !== 'undefined' ? window.innerWidth : 1280,
): number {
  return clampOfficeSidebarWidth(
    Math.floor(viewportWidth * OFFICE_SIDEBAR_DEFAULT_VIEWPORT_FRACTION),
    viewportWidth,
  )
}

export function maxOfficeSidebarWidth(viewportWidth = typeof window !== 'undefined' ? window.innerWidth : 1280): number {
  return Math.min(OFFICE_SIDEBAR_MAX_WIDTH_PX, Math.floor(viewportWidth * OFFICE_SIDEBAR_MAX_VIEWPORT_FRACTION))
}

export function clampOfficeSidebarWidth(
  width: number,
  viewportWidth = typeof window !== 'undefined' ? window.innerWidth : 1280,
): number {
  const max = maxOfficeSidebarWidth(viewportWidth)
  return Math.min(max, Math.max(OFFICE_SIDEBAR_MIN_WIDTH, Math.round(width)))
}

function readStoredWidth(): number | null {
  if (!import.meta.client) return null
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = Number.parseInt(raw, 10)
    return Number.isFinite(parsed) ? parsed : null
  }
  catch {
    return null
  }
}

function persistWidth(width: number) {
  if (!import.meta.client) return
  try {
    localStorage.setItem(STORAGE_KEY, String(width))
  }
  catch {
    //
  }
}

export function useResizableSidebarWidth() {
  const width = ref(defaultOfficeSidebarWidth())
  const isResizing = ref(false)
  const hydrated = ref(false)

  function hydrate() {
    if (!import.meta.client || hydrated.value) return
    const stored = readStoredWidth()
    width.value = stored != null
      ? clampOfficeSidebarWidth(stored)
      : defaultOfficeSidebarWidth()
    hydrated.value = true
  }

  function setWidth(next: number) {
    width.value = clampOfficeSidebarWidth(next)
    persistWidth(width.value)
  }

  function startResize(event: PointerEvent) {
    if (!import.meta.client) return
    event.preventDefault()

    const startX = event.clientX
    const startWidth = width.value
    isResizing.value = true

    const onMove = (e: PointerEvent) => {
      const delta = startX - e.clientX
      setWidth(startWidth + delta)
    }

    const onEnd = () => {
      isResizing.value = false
      document.removeEventListener('pointermove', onMove)
      document.removeEventListener('pointerup', onEnd)
      document.removeEventListener('pointercancel', onEnd)
      document.body.style.cursor = ''
      document.body.style.userSelect = ''
    }

    document.body.style.cursor = 'col-resize'
    document.body.style.userSelect = 'none'
    document.addEventListener('pointermove', onMove)
    document.addEventListener('pointerup', onEnd)
    document.addEventListener('pointercancel', onEnd)
  }

  onMounted(hydrate)

  return {
    width,
    isResizing,
    setWidth,
    startResize,
  }
}
