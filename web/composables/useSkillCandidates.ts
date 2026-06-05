import type { SkillCandidate } from '~/types/api'

export function useSkillCandidates(status?: Ref<string>) {
  const api = useApi()
  const s = status ?? ref('')
  return useAsyncData<SkillCandidate[]>(
    'skill-candidates',
    () => api.get('/skill-candidates', { status: s.value || undefined }),
    { watch: [s] },
  )
}
