export type ProjectTreeEntry = {
  name: string
  path: string
  type: 'dir' | 'file'
}

export type ProjectTreeResponse = {
  path: string
  entries: ProjectTreeEntry[]
  truncated?: boolean
}

export function useProjectTree() {
  const api = useApi()

  async function fetchTree(path = '') {
    const params: Record<string, string> = {}
    if (path !== '') {
      params.path = path
    }
    return api.get<ProjectTreeResponse>('/project/tree', params)
  }

  return { fetchTree }
}
