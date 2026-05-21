import { ONBOARDING_STEPS, type OnboardingStep } from '~/utils/onboardingSteps'

export const ONBOARDING_STORAGE_KEY = 'bossku_onboarding_v1'

export type OnboardingHints = {
  hasOllama: boolean
  hasProject: boolean
}

export function useOnboarding() {
  const active = useState<boolean>('bossku-onboarding-active', () => false)
  const stepIndex = useState<number>('bossku-onboarding-step', () => 0)
  const hydrated = useState('bossku-onboarding-hydrated', () => false)
  const completed = useState<boolean>('bossku-onboarding-completed', () => false)
  const hints = useState<OnboardingHints>('bossku-onboarding-hints', () => ({
    hasOllama: false,
    hasProject: false,
  }))

  const openSidebarHandler = useState<(() => void) | null>('bossku-onboarding-open-sidebar', () => null)

  const steps = ONBOARDING_STEPS
  const currentStep = computed<OnboardingStep | null>(() =>
    active.value ? steps[stepIndex.value] ?? null : null,
  )

  function readCompleted(): boolean {
    if (!import.meta.client) return false
    try {
      return localStorage.getItem(ONBOARDING_STORAGE_KEY) === '1'
    }
    catch {
      return false
    }
  }

  function persistCompleted() {
    if (!import.meta.client) return
    try {
      localStorage.setItem(ONBOARDING_STORAGE_KEY, '1')
    }
    catch {
      //
    }
    completed.value = true
  }

  function hydrate() {
    if (!import.meta.client || hydrated.value) return
    completed.value = readCompleted()
    hydrated.value = true
  }

  function registerOpenSidebar(fn: () => void) {
    openSidebarHandler.value = fn
  }

  function ensureSidebarVisible() {
    if (!import.meta.client) return
    const { setCollapsed } = useSidebarCollapsed()
    setCollapsed(false)
    openSidebarHandler.value?.()
  }

  async function loadHints() {
    if (!import.meta.client) return
    const api = useApi()
    try {
      const [projectRes, settingsRes] = await Promise.all([
        api.get<{ active_project_id?: string | null }>('/project/list').catch(() => null),
        api.get<{ ollama_base_url?: string }>('/settings').catch(() => null),
      ])
      hints.value = {
        hasProject: Boolean(projectRes?.active_project_id),
        hasOllama: Boolean(String(settingsRes?.ollama_base_url ?? '').trim()),
      }
    }
    catch {
      //
    }
  }

  function startTour() {
    hydrate()
    stepIndex.value = 0
    active.value = true
    void loadHints()
  }

  function restartTour() {
    stepIndex.value = 0
    active.value = true
    void loadHints()
  }

  function complete() {
    active.value = false
    persistCompleted()
  }

  function skip() {
    complete()
  }

  function next() {
    if (stepIndex.value >= steps.length - 1) {
      complete()
      return
    }
    stepIndex.value += 1
  }

  function prev() {
    if (stepIndex.value > 0) {
      stepIndex.value -= 1
    }
  }

  function maybeAutoStart(delayMs = 300) {
    if (!import.meta.client) return
    hydrate()
    if (completed.value || active.value) return
    window.setTimeout(() => {
      if (!readCompleted() && !active.value) {
        startTour()
      }
    }, delayMs)
  }

  onMounted(hydrate)

  return {
    active,
    stepIndex,
    steps,
    currentStep,
    completed,
    hydrated,
    hints,
    registerOpenSidebar,
    ensureSidebarVisible,
    startTour,
    restartTour,
    complete,
    skip,
    next,
    prev,
    maybeAutoStart,
    loadHints,
  }
}
