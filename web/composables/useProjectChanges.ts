export type ProjectChangeApproval = {
  id: string
  run_id: string
  operation_type: string
  operation_description: string
  risk_level: string
  status: string
  evidence?: {
    path?: string
    diff?: string
    before?: string
    after?: string
  }
  created_at?: string
}

export function useProjectChanges() {
  const api = useApi()
  const pending = ref<ProjectChangeApproval[]>([])
  const loading = ref(false)
  const error = ref('')

  async function refresh(status = 'pending') {
    loading.value = true
    error.value = ''
    try {
      pending.value = await api.get<ProjectChangeApproval[]>('/project/changes', { status })
    }
    catch (e: unknown) {
      error.value = e instanceof Error ? e.message : String(e)
    }
    finally {
      loading.value = false
    }
  }

  async function propose(path: string, newContents: string, runId?: string) {
    const body: Record<string, string> = { path, new_contents: newContents }
    if (runId) body.run_id = runId
    const approval = await api.post<ProjectChangeApproval>('/project/changes', body)
    await refresh()
    return approval
  }

  async function approve(id: string, note?: string) {
    await api.post(`/project/changes/${id}/approve`, note ? { note } : {})
    await refresh()
  }

  async function apply(id: string) {
    await api.post(`/project/changes/${id}/apply`)
    await refresh()
  }

  async function reject(id: string, note?: string) {
    await api.post(`/project/changes/${id}/reject`, note ? { note } : {})
    await refresh()
  }

  return {
    pending,
    loading,
    error,
    refresh,
    propose,
    approve,
    apply,
    reject,
  }
}
