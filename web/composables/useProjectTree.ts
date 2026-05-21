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
    return api.get<ProjectTreeResponse>('/project/tree', { path: path || undefined })
  }

  return { fetchTree }
}
