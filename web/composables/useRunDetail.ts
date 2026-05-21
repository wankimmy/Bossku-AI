import type { Run } from '~/types/api'

export function useRunDetail(id: string | Ref<string>) {
  const api = useApi()
  const runId = isRef(id) ? id : ref(id)

  const run = useAsyncData<Run>(`run-${runId.value}`, () => api.get(`/runs/${runId.value}`))

  async function fetchApprovals() {
    return api.get(`/runs/${runId.value}/approvals`)
  }

  async function fetchUsage() {
    return api.get(`/runs/${runId.value}/usage`)
  }

  async function fetchFeedback() {
    return api.get(`/runs/${runId.value}/feedback`)
  }

  return { run, fetchApprovals, fetchUsage, fetchFeedback }
}
