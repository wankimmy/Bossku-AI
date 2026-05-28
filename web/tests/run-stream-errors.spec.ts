import { describe, expect, it } from 'vitest'
import { isRunNotFoundError, streamResumeErrorAction } from '../utils/runStreamErrors'

describe('isRunNotFoundError', () => {
  it('detects 404 fetch errors', () => {
    expect(isRunNotFoundError({ status: 404, data: { message: 'Run not found.' } })).toBe(true)
  })

  it('ignores other status codes', () => {
    expect(isRunNotFoundError({ status: 500 })).toBe(false)
  })
})

describe('streamResumeErrorAction', () => {
  it('abandons stale run on 404 instead of retrying poll', () => {
    expect(streamResumeErrorAction({ status: 404 })).toBe('abandon')
  })

  it('retries poll for transient errors', () => {
    expect(streamResumeErrorAction({ status: 503 })).toBe('retry_poll')
  })
})
