/** True when a run API call targeted a run row that no longer exists. */
export function isRunNotFoundError(err: unknown): boolean {
  if (!err || typeof err !== 'object') {
    return false
  }

  const status = 'status' in err ? Number((err as { status?: number }).status) : 0

  return status === 404
}

export type StreamResumeErrorAction = 'abandon' | 'retry_poll'

/** How the UI should react after stream-events resume fetch fails. */
export function streamResumeErrorAction(err: unknown): StreamResumeErrorAction {
  return isRunNotFoundError(err) ? 'abandon' : 'retry_poll'
}
