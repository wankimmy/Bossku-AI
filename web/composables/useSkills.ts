import type { Skill } from '~/types/api'

type SkillsResponse = Skill[] | {
  data?: Skill[]
  current_page?: number
  last_page?: number
}

export function useSkills() {
  const api = useApi()
  return useAsyncData<Skill[]>(
    'skills-active',
    async () => {
      const res = await api.get<SkillsResponse>('/skills', { active_only: 1 })
      return Array.isArray(res) ? res : res.data ?? []
    },
    { default: () => [] },
  )
}
