export const LONG_PROMPT_INLINE_LIMIT = 50000

const CHAT_PREVIEW_CHARS = 1200

export type PreparedPromptSubmission = {
  runPrompt: string
  chatContent: string
  isLongPrompt: boolean
}

export function preparePromptForSubmission(prompt: string): PreparedPromptSubmission {
  if (prompt.length <= LONG_PROMPT_INLINE_LIMIT) {
    return {
      runPrompt: prompt,
      chatContent: prompt,
      isLongPrompt: false,
    }
  }

  return {
    runPrompt: prompt,
    chatContent: compactLongPromptForChat(prompt),
    isLongPrompt: true,
  }
}

function compactLongPromptForChat(prompt: string): string {
  const start = prompt.slice(0, CHAT_PREVIEW_CHARS).trim()
  const end = prompt.slice(-CHAT_PREVIEW_CHARS).trim()

  return [
    `Long prompt attached (${prompt.length} chars)`,
    '',
    'Preview start:',
    start,
    '',
    'Preview end:',
    end,
  ].join('\n')
}
