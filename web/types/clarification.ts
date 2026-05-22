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

export type ClarificationProof = {
  from_agent?: string
  files_read?: unknown[]
  files_changed?: unknown[]
  proof_files?: string[]
  blockers?: string[]
  known_issues?: unknown[]
  findings?: unknown[]
  required_fixes?: string[]
  commands_run?: unknown[]
}

export type ClarificationRequest = {
  runId: string
  stage: string
  summary: string
  assumptions: string[]
  questions: ClarificationQuestion[]
  from_agent?: string
  origin?: string
  proof?: ClarificationProof
}
