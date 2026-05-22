export type AgentName =
  | 'orchestrator'
  | 'executor'
  | 'auditor'
  | 'security-auditor'
  | 'final-reviewer'
  | 'router'
  | 'memory'
  | 'system'

export type StepStatus =
  | 'pending'
  | 'running'
  | 'completed'
  | 'success'
  | 'passed'
  | 'failed'
  | 'fail'
  | 'needs_revision'
  | 'skipped'
  | string

export interface PlanChecklistItem {
  id: string
  title: string
  description?: string
  owner: AgentName | string
  status: StepStatus
}

export interface FileRead {
  path: string
  reason?: string
}

export interface FileChange {
  path: string
  change_type: 'created' | 'modified' | 'deleted' | 'renamed' | string
  summary?: string
  why?: string
  diff?: string
  after?: string
  before?: string
}

export interface CommandRun {
  command: string
  status: StepStatus | string
  exit_code?: number
  duration_ms?: number
  output_summary?: string
}

export interface TestRun {
  name: string
  status: StepStatus | string
  summary?: string
}

export interface AuditFinding {
  id?: string
  severity: 'low' | 'medium' | 'high' | 'critical' | string
  category?: string
  title: string
  description?: string
  suggested_fix?: string
  status?: 'open' | 'fixed' | 'accepted_risk' | string
}

export interface RiskItem {
  issue: string
  severity: string
  location?: string
  description?: string
  recommendation?: string
}

export interface RouterDisplay {
  primarySkill: string
  primaryReason?: string
  secondarySkills: { name: string; reason?: string }[]
  rulesCount: number
  playbooksCount: number
  checklistsCount: number
}

export interface AgentMessage {
  id: string
  agent: AgentName | string
  title: string
  status: StepStatus
  model_role?: string
  model?: string
  summary?: string
  message?: string
  from_agent?: string
  to_agent?: string
  latency_ms?: number
  token_estimate?: number
  artifacts?: Record<string, unknown>
  risks?: RiskItem[]
  router?: RouterDisplay
}

export interface HandoffNode {
  agent: AgentName | string
  label: string
  status: StepStatus
}

export interface FinalResult {
  status?: string
  summary?: string
  filesChanged: string[]
  checksRun: string[]
  gitStatusAfter?: string
  auditResult?: string
  remainingRisks: RiskItem[]
  nextStep?: string
  nextPrompt?: string
  raw?: string
}

export interface RoutingSummary {
  backend: 'Ollama'
  reasoningModel?: string
  codingModel?: string
  reviewModel?: string
  fastModel?: string
  workflow?: string
  skill?: string
  riskLevel?: string
  /** Human-readable pipeline, e.g. "orchestrator → executor (auditor skipped)". */
  pipelinePath?: string
  pipelineAgents?: string[]
  skippedAgents?: string[]
}

export interface NormalizedRunArtifacts {
  agentMessages: AgentMessage[]
  handoffNodes: HandoffNode[]
  checklist: PlanChecklistItem[]
  filesRead: FileRead[]
  filesChanged: FileChange[]
  commandsRun: CommandRun[]
  gitStatusAfter?: string
  testsRun: TestRun[]
  auditFindings: AuditFinding[]
  finalResult: FinalResult
  routingSummary: RoutingSummary
  memoryUsed: boolean
}
