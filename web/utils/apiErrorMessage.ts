import type { FetchError } from 'ofetch'

export function apiErrorMessage(err: unknown, fallback = 'Request failed.'): string {
  if (err && typeof err === 'object' && 'data' in err) {
    const data = (err as FetchError).data
    if (data && typeof data === 'object') {
      if ('error' in data && typeof (data as { error?: string }).error === 'string') {
        const message = (data as { error: string }).error.trim()
        if (message !== '') {
          return message
        }
      }
      if ('message' in data && typeof (data as { message?: string }).message === 'string') {
        const message = (data as { message: string }).message.trim()
        if (message !== '') {
          return message
        }
      }
    }
  }

  if (err instanceof Error && err.message.trim() !== '') {
    return err.message
  }

  return fallback
}
