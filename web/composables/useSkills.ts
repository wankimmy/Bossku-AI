import type { Skill } from '~/types/api'

export function useSkills() {
  const api = useApi()
  return useAsyncData<Skill[]>('skills', () => api.get('/skills'))
}
