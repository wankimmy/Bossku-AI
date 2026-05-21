export type ProjectSearchMatch = {
  path: string
  line: number
  preview: string
}

export type ProjectSearchResponse = {
  query: string
  matches: ProjectSearchMatch[]
}

export function useProjectSearch() {
  const api = useApi()

  async function search(q: string, glob = '*') {
    if (!q.trim()) return { query: q, matches: [] as ProjectSearchMatch[] }
    return api.get<ProjectSearchResponse>('/project/search', { q: q.trim(), glob })
  }

  return { search }
}
