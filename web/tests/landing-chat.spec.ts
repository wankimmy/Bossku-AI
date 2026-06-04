import { describe, expect, it } from 'vitest'
import { LONG_PROMPT_INLINE_LIMIT, preparePromptForSubmission } from '../utils/longPrompt'

describe('landing chat prompt submission', () => {
  it('keeps normal prompts unchanged for chat display and API payload', () => {
    const prompt = 'hello BosskuAI'

    const prepared = preparePromptForSubmission(prompt)

    expect(prepared.isLongPrompt).toBe(false)
    expect(prepared.runPrompt).toBe(prompt)
    expect(prepared.chatContent).toBe(prompt)
  })

  it('compacts oversized prompts for chat history without changing API payload', () => {
    const prompt = [
      'Please analyze this long pasted log.',
      'START_VISIBLE',
      'A'.repeat(Math.ceil(LONG_PROMPT_INLINE_LIMIT / 2)),
      'MIDDLE_SECRET_SHOULD_NOT_BE_STORED',
      'B'.repeat(Math.ceil(LONG_PROMPT_INLINE_LIMIT / 2)),
      'END_VISIBLE',
    ].join('\n')

    const prepared = preparePromptForSubmission(prompt)

    expect(prepared.isLongPrompt).toBe(true)
    expect(prepared.runPrompt).toBe(prompt)
    expect(prepared.chatContent.length).toBeLessThan(5000)
    expect(prepared.chatContent).toContain(`Long prompt attached (${prompt.length} chars)`)
    expect(prepared.chatContent).toContain('START_VISIBLE')
    expect(prepared.chatContent).toContain('END_VISIBLE')
    expect(prepared.chatContent).not.toContain('MIDDLE_SECRET_SHOULD_NOT_BE_STORED')
  })
})
