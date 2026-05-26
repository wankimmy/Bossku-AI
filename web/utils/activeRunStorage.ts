export type ActiveRunBinding = {
  convId: string
  runId: string
  lastSeq: number
}

const STORAGE_KEY = 'bossku_active_run_v1'

function canUseSessionStorage(): boolean {
  return typeof sessionStorage !== 'undefined'
}

export function loadActiveRunBinding(): ActiveRunBinding | null {
  if (!canUseSessionStorage()) return null
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as ActiveRunBinding
    if (!parsed?.convId || !parsed?.runId) return null
    return {
      convId: String(parsed.convId),
      runId: String(parsed.runId),
      lastSeq: Number(parsed.lastSeq) || 0,
    }
  }
  catch {
    return null
  }
}

export function saveActiveRunBinding(binding: ActiveRunBinding): void {
  if (!canUseSessionStorage()) return
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(binding))
  }
  catch {
    //
  }
}

export function clearActiveRunBinding(): void {
  if (!canUseSessionStorage()) return
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  }
  catch {
    //
  }
}

export function updateActiveRunLastSeq(lastSeq: number): void {
  const binding = loadActiveRunBinding()
  if (!binding) return
  saveActiveRunBinding({ ...binding, lastSeq })
}
