export type ClarificationOption = {
  id: string
  label: string
  recommendation?: boolean
}

export type ClarificationQuestion = {
  id: string
  prompt: string
  why_it_matters?: string
  options: ClarificationOption[]
  allow_free_text?: boolean
}

export type ClarificationAnswer = {
  question_id: string
  option_id?: string
  free_text?: string
}

export type ClarificationRequest = {
  runId: string
  stage: string
  summary: string
  assumptions: string[]
  questions: ClarificationQuestion[]
}
