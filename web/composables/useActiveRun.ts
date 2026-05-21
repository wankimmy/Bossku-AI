export function useActiveRun() {
  const status = ref<string | null>(null)
  const runId = ref<string | null>(null)

  // Stub — in production this would poll /api/runs?status=running
  return { status, runId }
}
