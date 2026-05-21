export interface AgentPersonaListItem {
  role: string
  display_name: string
  content_preview: string
  enabled: boolean
  updated_at: string | null
}

export interface AgentPersonaDetail {
  role: string
  display_name: string
  content: string
  enabled: boolean
  updated_at: string | null
  builtin_preview: string
}

export function useAgentPersonas() {
  const api = useApi()

  async function list() {
    const res = await api.get('/agent-personas') as { data?: AgentPersonaListItem[] }
    return res.data ?? []
  }

  async function get(role: string) {
    return api.get(`/agent-personas/${role}`) as Promise<AgentPersonaDetail>
  }

  async function save(role: string, payload: { content?: string; enabled?: boolean; display_name?: string }) {
    return api.put(`/agent-personas/${role}`, payload) as Promise<AgentPersonaDetail>
  }

  async function reset(role: string) {
    return api.post(`/agent-personas/${role}/reset`) as Promise<AgentPersonaDetail>
  }

  return { list, get, save, reset }
}
