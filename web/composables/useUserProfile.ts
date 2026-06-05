export interface UserProfile {
  id: string
  headline: string | null
  content: string
  origin: string | null
  generated_by_model: string | null
  confidence: number | null
  updated_at: string | null
}

interface ProfileResponse {
  profile: UserProfile | null
}

export function useUserProfile() {
  const api = useApi()

  const profile = ref<UserProfile | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const generating = ref(false)
  const error = ref<string | null>(null)

  async function load() {
    loading.value = true
    error.value = null
    try {
      const res = await api.get('/user-profile') as ProfileResponse
      profile.value = res.profile
    }
    catch {
      error.value = 'Failed to load user profile.'
    }
    finally {
      loading.value = false
    }
  }

  async function save(content: string, headline: string | null) {
    saving.value = true
    error.value = null
    try {
      const res = await api.put('/user-profile', { content, headline }) as ProfileResponse
      profile.value = res.profile
      return true
    }
    catch {
      error.value = 'Failed to save user profile.'
      return false
    }
    finally {
      saving.value = false
    }
  }

  async function generate() {
    generating.value = true
    error.value = null
    try {
      const res = await api.post('/user-profile/generate') as ProfileResponse
      profile.value = res.profile
      return true
    }
    catch (e: unknown) {
      const message = (e as { data?: { message?: string } })?.data?.message
      error.value = message ?? 'Failed to generate user profile.'
      return false
    }
    finally {
      generating.value = false
    }
  }

  return { profile, loading, saving, generating, error, load, save, generate }
}
