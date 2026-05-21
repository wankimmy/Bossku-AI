import type { LogEntry } from '~/types/api'

export function useLogs(filters?: { level?: Ref<string>; search?: Ref<string>; runId?: Ref<string> }) {
  const api = useApi()
  const level = filters?.level ?? ref('')
  const search = filters?.search ?? ref('')
  const runId = filters?.runId ?? ref('')

  // logs ref is used by BottomLogDrawer for the stub
  const logs = ref<LogEntry[]>([])

  const asyncData = useAsyncData<{ data: LogEntry[]; total?: number }>(
    'logs',
    () => api.get('/logs', {
      level: level.value || undefined,
      search: search.value || undefined,
      run_id: runId.value || undefined,
    }),
    { watch: [level, search, runId] },
  )

  watch(asyncData.data, (val) => {
    if (val) {
      logs.value = Array.isArray(val) ? val : (val.data ?? [])
    }
  })

  return { ...asyncData, logs }
}
