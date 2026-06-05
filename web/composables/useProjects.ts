export type RegisteredProject = {
  id: string
  name: string
  host_path: string
  container_path: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export type WorkspaceMeta = {
  workspace_mount: string
  workspace_host_prefix: string
  default_repo_root: string
}

export function useProjects() {
  const api = useApi()
  const projects = ref<RegisteredProject[]>([])
  const activeProjectId = ref<string | null>(null)
  const workspace = ref<WorkspaceMeta | null>(null)
  const loading = ref(false)
  const error = ref('')

  async function refresh() {
    loading.value = true
    error.value = ''
    try {
      const res = await api.get<{
        projects: RegisteredProject[]
        active_project_id: string | null
        workspace: WorkspaceMeta
      }>('/project/list')
      projects.value = res.projects
      activeProjectId.value = res.active_project_id
      workspace.value = res.workspace
    }
    catch (e: unknown) {
      error.value = e instanceof Error ? e.message : String(e)
    }
    finally {
      loading.value = false
    }
  }

  function hostUnderWorkspace(hostPath: string): boolean {
    const prefix = workspace.value?.workspace_host_prefix?.replace(/\\/g, '/').replace(/\/+$/, '') ?? ''
    if (!prefix) return false
    const host = hostPath.replace(/\\/g, '/').replace(/\/+$/, '')
    return host.toLowerCase() === prefix.toLowerCase()
      || host.toLowerCase().startsWith(`${prefix.toLowerCase()}/`)
  }

  async function register(name: string, hostPath: string) {
    return api.post<{
      project: RegisteredProject
      created: boolean
      mounted: boolean
      under_workspace: boolean
      message?: string
    }>('/project/register', { name, host_path: hostPath })
  }

  async function activate(id: string) {
    return api.post<{
      project: RegisteredProject
      repo_root: string | null
      available: boolean
      error: string | null
      manifest_total: number | null
    }>(`/project/${id}/activate`)
  }

  async function remove(id: string) {
    await api.delete(`/project/${id}`)
    await refresh()
  }

  async function bootstrapSkills() {
    return api.post<{
      message: string
      project_name: string
      copied: string[]
    }>('/project/skills/bootstrap')
  }

  return {
    projects,
    activeProjectId,
    workspace,
    loading,
    error,
    refresh,
    hostUnderWorkspace,
    register,
    activate,
    remove,
    bootstrapSkills,
  }
}
