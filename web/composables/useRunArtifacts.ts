import type {
  AgentMessage,
  AuditFinding,
  CommandRun,
  FileChange,
  FileRead,
  FinalResult,
  HandoffNode,
  NormalizedRunArtifacts,
  PlanChecklistItem,
  RiskItem,
  RoutingSummary,
  TestRun,
} from '../types/bossku'
import { formatToolCallSummary, formatToolCallTitle, isToolCallEvent } from '../utils/formatToolCall'
import { formatAgentStepOutput, parseRiskItems } from '../utils/humanizeOutput'

type UnknownRecord = Record<string, unknown>
type EventProgress = {
  plannerRunning: boolean
  plannerDone: boolean
  plannerFailed: boolean
  executorRunning: boolean
  executorDone: boolean
  executorFailed: boolean
  auditorRunning: boolean
  auditorDone: boolean
  auditorFailed: boolean
  auditorNeedsRevision: boolean
  memoryRunning: boolean
  memoryDone: boolean
  finalRunning: boolean
  finalDone: boolean
  runCompleted: boolean
  runFailed: boolean
}

const WORKFLOW: HandoffNode[] = [
  { agent: 'orchestrator', label: 'Orchestrator', status: 'pending' },
  { agent: 'executor', label: 'Executor', status: 'pending' },
  { agent: 'auditor', label: 'Auditor', status: 'pending' },
  { agent: 'executor', label: 'Executor revision', status: 'pending' },
  { agent: 'final-reviewer', label: 'Final Reviewer', status: 'pending' },
]

export function useRunArtifacts(items: UnknownRecord[] = []): NormalizedRunArtifacts {
  const messages: AgentMessage[] = []
  const checklist: PlanChecklistItem[] = []
  const filesRead: FileRead[] = []
  const filesChanged: FileChange[] = []
  const commandsRun: CommandRun[] = []
  let gitStatusAfter: string | undefined
  const testsRun: TestRun[] = []
  const auditFindings: AuditFinding[] = []
  const routingSummary: RoutingSummary = { backend: 'Ollama' }
  let memoryUsed = false
  let finalRaw = ''

  for (const [idx, item] of items.entries()) {
    const event = unwrapStep(item)
    const metadata = asRecord(item.metadata) ?? {}
    const artifacts = mergeArtifacts(event, metadata)
    const type = String(event.type ?? item.type ?? '')
    const agent = isToolCallEvent(event)
      ? 'tools'
      : String(event.agent ?? metadata.agent ?? inferAgent(type))
    const status = normalizeStatus(String(event.status ?? item.status ?? metadata.status ?? 'completed'))

    if (type === 'memory_retrieved' || asArray(event.memory_used).length > 0 || asArray(artifacts.memory_used).length > 0) {
      memoryUsed = true
    }

    applyRouting(event, artifacts, routingSummary, type)
    pushAll(checklist, asChecklist(artifacts.checklist))
    pushAll(filesRead, asFileReads(artifacts.files_read))
    pushAll(filesChanged, asFileChanges(artifacts.files_changed))
    pushAll(commandsRun, asCommands(artifacts.commands_run))
    pushAll(commandsRun, asExecutedCommands(artifacts.commands_executed))
    if (typeof artifacts.git_status_after === 'string' && artifacts.git_status_after.trim()) {
      gitStatusAfter = artifacts.git_status_after.trim()
    }
    pushAll(testsRun, asTests(artifacts.tests_run))
    pushAll(auditFindings, asAuditFindings(artifacts.audit_findings))
    pushAll(auditFindings, asAuditFindings(artifacts.findings))

    const parsedOutput = parseJsonMaybe(item.output)
    if (parsedOutput) {
      pushAll(auditFindings, asAuditFindings(parsedOutput.findings))
    }

    if (type === 'run_completed' || type === 'final' || agent === 'final-reviewer') {
      finalRaw = String(event.output ?? item.output ?? finalRaw)
    }

    if (type === 'clarification_requested') {
      continue
    }

    if (agent || type) {
      const toolSummary = isToolCallEvent(event) ? formatToolCallSummary(event) : undefined
      const rawOutput = stringOrUndefined(
        event.error ?? event.message ?? metadata.message ?? metadata.error ?? item.output,
      )
      const formatted = formatAgentStepOutput(agent, rawOutput, artifacts)
      const existingSummary = stringOrUndefined(
        event.summary ?? metadata.summary ?? summaryFromArtifacts(artifacts, type),
      )

      messages.push({
        id: String(item.id ?? event.id ?? `${type || agent}-${idx}`),
        agent,
        title: isToolCallEvent(event) ? formatToolCallTitle(event) : titleForAgent(agent, artifacts),
        status,
        model_role: stringOrUndefined(event.model_role ?? metadata.model_role),
        model: stringOrUndefined(event.model ?? item.model ?? metadata.model),
        summary: toolSummary ?? existingSummary ?? formatted.summary,
        message: isToolCallEvent(event) ? toolSummary : formatted.detail,
        risks: formatted.risks,
        router: formatted.router,
        from_agent: stringOrUndefined(event.from_agent ?? metadata.from_agent),
        to_agent: stringOrUndefined(event.to_agent ?? metadata.to_agent),
        latency_ms: numberOrUndefined(event.latency_ms ?? item.latency_ms),
        token_estimate: numberOrUndefined(event.token_estimate ?? item.token_estimate),
        artifacts,
      })

      if (formatted.risks?.length) {
        pushAll(auditFindings, formatted.risks.map((risk, riskIdx) => ({
          id: `security-${idx}-${riskIdx}`,
          severity: risk.severity,
          category: 'security',
          title: risk.issue,
          description: risk.description ?? risk.location,
          suggested_fix: risk.recommendation,
          status: 'open',
        })))
      }
    }
  }

  inferRoutingFromMessages(messages, routingSummary)
  const resolvedChecklist = resolveChecklistProgress(
    dedupeChecklistLatest(checklist),
    items,
  )

  return {
    agentMessages: messages,
    handoffNodes: buildHandoff(messages),
    checklist: resolvedChecklist,
    filesRead: dedupeBy(filesRead, item => item.path),
    filesChanged: dedupeBy(filesChanged, item => `${item.change_type}:${item.path}:${item.summary ?? ''}`),
    commandsRun,
    testsRun,
    auditFindings: dedupeBy(auditFindings, item => item.id || item.title),
    finalResult: parseFinalResult(finalRaw, filesChanged, commandsRun, auditFindings, gitStatusAfter),
    gitStatusAfter,
    routingSummary,
    memoryUsed,
  }
}

function unwrapStep(item: UnknownRecord): UnknownRecord {
  const metadata = asRecord(item.metadata)
  const event = asRecord(metadata?.event)
  return { ...item, ...(event ?? {}) }
}

function mergeArtifacts(event: UnknownRecord, metadata: UnknownRecord): UnknownRecord {
  const fromMetadata = asRecord(metadata.artifacts) ?? {}
  const fromEvent = asRecord(event.artifacts) ?? {}
  const parsedOutput = parseJsonMaybe(event.output)
  return { ...(parsedOutput ?? {}), ...fromMetadata, ...fromEvent }
}

function applyRouting(
  event: UnknownRecord,
  artifacts: UnknownRecord,
  routing: RoutingSummary,
  type: string,
) {
  const models = asRecord(
    event.models
    ?? event.models_resolved
    ?? artifacts.models_resolved
    ?? artifacts.models,
  )
  const route = asRecord(
    event.routing
    ?? event.routing_decision
    ?? artifacts.routing_decision
    ?? (type === 'routing_decision' ? event : undefined),
  )
  applyModelsRecord(models, routing)
  if (type === 'agents_skipped') {
    const skipped = asStringArray(artifacts.skipped_agents)
    if (skipped.length > 0) {
      routing.skippedAgents = [...new Set([...(routing.skippedAgents ?? []), ...skipped])]
    }
  }

  if (route) {
    routing.workflow = stringOrUndefined(route.workflow) ?? routing.workflow
    routing.skill = stringOrUndefined(route.skill) ?? routing.skill
    routing.riskLevel = stringOrUndefined(route.risk_level) ?? routing.riskLevel
    const pipeline = asStringArray(artifacts.pipeline_agents ?? route.pipeline_agents)
    const skipped = asStringArray(artifacts.skipped_agents ?? route.skipped_agents)
    if (pipeline.length > 0) routing.pipelineAgents = pipeline
    if (skipped.length > 0) {
      routing.skippedAgents = [...new Set([...(routing.skippedAgents ?? []), ...skipped])]
    }
  }

  routing.pipelinePath = formatPipelinePath(
    routing.workflow,
    routing.pipelineAgents,
    routing.skippedAgents,
  )
}

function formatPipelinePath(
  workflow?: string,
  pipelineAgents?: string[],
  skippedAgents?: string[],
): string | undefined {
  const labels = (pipelineAgents ?? []).map(agentLabel)
  if (labels.length === 0 && workflow) {
    labels.push(...workflowToAgentLabels(workflow))
  }
  if (labels.length === 0) return undefined

  let path = `Route: ${labels.join(' → ')}`
  const skipped = (skippedAgents ?? []).map(agentLabel)
  if (skipped.length > 0) {
    path += ` (${skipped.join(', ')} skipped)`
  }

  return path
}

function workflowToAgentLabels(workflow: string): string[] {
  if (workflow === 'direct_answer') return ['direct answer']
  if (workflow === 'writer_only') return ['writer']
  if (workflow === 'orchestrator_only') return ['orchestrator']

  const labels: string[] = ['orchestrator']
  if (workflow.includes('executor')) labels.push('executor')
  if (/_auditor(?:_|$)/.test(workflow)) labels.push('auditor')
  if (workflow.includes('security')) labels.push('security-auditor')
  if (workflow.includes('final_reviewer')) labels.push('final-reviewer')

  return labels
}

function agentLabel(agent: string): string {
  if (agent === 'direct_answer') return 'direct answer'
  if (agent === 'security-auditor') return 'security-auditor'
  return agent
}

function asStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) return []
  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '')
}

function applyModelsRecord(models: UnknownRecord | undefined, routing: RoutingSummary) {
  if (!models) return
  routing.fastModel = stringOrUndefined(
    models.router ?? models.fast ?? models.direct_answer ?? models.writer,
  ) ?? routing.fastModel
  routing.reasoningModel = stringOrUndefined(
    models.orchestrator ?? models.reasoning ?? models.final_reviewer,
  ) ?? routing.reasoningModel
  routing.codingModel = stringOrUndefined(models.executor ?? models.coding) ?? routing.codingModel
  routing.reviewModel = stringOrUndefined(
    models.auditor ?? models.security_auditor ?? models.review,
  ) ?? routing.reviewModel
}

function inferRoutingFromMessages(messages: AgentMessage[], routing: RoutingSummary) {
  for (const msg of messages) {
    if (!msg.model) continue
    const role = msg.model_role ?? modelRoleForAgent(msg.agent)
    if (role === 'reasoning') routing.reasoningModel ??= msg.model
    else if (role === 'coding') routing.codingModel ??= msg.model
    else if (role === 'review') routing.reviewModel ??= msg.model
    else if (role === 'fast') routing.fastModel ??= msg.model
  }
}

function modelRoleForAgent(agent: string): string {
  if (agent === 'orchestrator' || agent === 'final-reviewer') return 'reasoning'
  if (agent.includes('specialist')) return 'reasoning'
  if (agent === 'executor') return 'coding'
  if (agent === 'auditor' || agent === 'security-auditor' || agent === 'evaluator') return 'review'
  if (agent === 'router' || agent === 'memory') return 'fast'
  return 'system'
}

function buildHandoff(messages: AgentMessage[]): HandoffNode[] {
  const base = WORKFLOW.map((node) => {
    const matches = messages.filter(message => message.agent === node.agent)
    const revision = node.label.includes('revision')
      ? messages.find(message => String(message.id).includes('executor_revision') || String(message.summary ?? '').toLowerCase().includes('revision'))
      : undefined
    const message = revision ?? matches.at(-1)
    return { ...node, status: message?.status ?? node.status }
  })

  const specialistNodes = messages
    .filter(isSpecialistMessage)
    .reduce<HandoffNode[]>((nodes, message) => {
      if (nodes.some(node => node.agent === message.agent)) return nodes
      nodes.push({
        agent: message.agent,
        label: titleForAgent(message.agent, message.artifacts ?? {}),
        status: message.status,
      })
      return nodes
    }, [])

  if (specialistNodes.length === 0) return base

  return [
    base[0],
    ...specialistNodes,
    ...base.slice(1),
  ]
}

function isSpecialistMessage(message: AgentMessage): boolean {
  if (asRecord(message.artifacts?.specialist_agent)) return true
  return typeof message.agent === 'string'
    && message.agent.includes('specialist')
    && !['system', 'router', 'memory', 'evaluator', 'tools'].includes(message.agent)
}

function dedupeChecklistLatest(items: PlanChecklistItem[]): PlanChecklistItem[] {
  const order: string[] = []
  const byKey = new Map<string, PlanChecklistItem>()
  for (const item of items) {
    const key = item.id || item.title
    if (!key) continue
    if (!byKey.has(key)) order.push(key)
    byKey.set(key, { ...byKey.get(key), ...item })
  }
  return order.map(key => byKey.get(key)).filter(Boolean) as PlanChecklistItem[]
}

function resolveChecklistProgress(
  items: PlanChecklistItem[],
  events: UnknownRecord[],
): PlanChecklistItem[] {
  if (items.length === 0) return []

  const progress = eventProgress(events)
  return items.map((item) => {
    const status = normalizeStatus(String(item.status ?? 'pending'))
    if (isTerminalChecklistStatus(status)) return { ...item, status }

    const owner = String(item.owner ?? '').toLowerCase()
    if (owner.includes('executor')) {
      if (progress.executorFailed) return { ...item, status: 'failed' }
      if (progress.executorDone || progress.runCompleted) return { ...item, status: 'completed' }
      if (progress.executorRunning) return { ...item, status: 'running' }
    }

    if (owner.includes('auditor') || owner.includes('audit')) {
      if (progress.auditorNeedsRevision) return { ...item, status: 'needs_revision' }
      if (progress.auditorFailed) return { ...item, status: 'failed' }
      if (progress.auditorDone) return { ...item, status: 'completed' }
      if (progress.auditorRunning) return { ...item, status: 'running' }
    }

    if (owner.includes('orchestrator') || owner.includes('planner')) {
      if (progress.plannerFailed) return { ...item, status: 'failed' }
      if (progress.plannerDone) return { ...item, status: 'completed' }
      if (progress.plannerRunning) return { ...item, status: 'running' }
    }

    if (owner.includes('memory')) {
      if (progress.memoryDone) return { ...item, status: 'completed' }
      if (progress.memoryRunning) return { ...item, status: 'running' }
    }

    if (owner.includes('final')) {
      if (progress.runFailed) return { ...item, status: 'failed' }
      if (progress.finalDone || progress.runCompleted) return { ...item, status: 'completed' }
      if (progress.finalRunning) return { ...item, status: 'running' }
    }

    return { ...item, status }
  })
}

function isTerminalChecklistStatus(status: string): boolean {
  return ['completed', 'passed', 'failed', 'needs_revision', 'skipped'].includes(status)
}

function eventProgress(events: UnknownRecord[]): EventProgress {
  const progress = {
    plannerRunning: false,
    plannerDone: false,
    plannerFailed: false,
    executorRunning: false,
    executorDone: false,
    executorFailed: false,
    auditorRunning: false,
    auditorDone: false,
    auditorFailed: false,
    auditorNeedsRevision: false,
    memoryRunning: false,
    memoryDone: false,
    finalRunning: false,
    finalDone: false,
    runCompleted: false,
    runFailed: false,
  }

  for (const item of events) {
    const event = unwrapStep(item)
    const metadata = asRecord(item.metadata) ?? {}
    const type = String(event.type ?? item.type ?? '').toLowerCase()
    const agent = String(event.agent ?? metadata.agent ?? inferAgent(type)).toLowerCase()
    const status = normalizeStatus(
      String(event.status ?? item.status ?? metadata.status ?? ''),
    ).toLowerCase()
    const running = status === 'running' || type.endsWith('_started')
    const done = ['completed', 'passed'].includes(status) || type.endsWith('_done')
    const failed = ['failed', 'error'].includes(status) || type.endsWith('_failed') || type === 'run_failed'

    if (type === 'run_completed') progress.runCompleted = true
    if (type === 'run_failed') progress.runFailed = true

    applyAgentProgress(progress, agent, type, running, done, failed, status)
  }

  return progress
}

function applyAgentProgress(
  progress: EventProgress,
  agent: string,
  type: string,
  running: boolean,
  done: boolean,
  failed: boolean,
  status: string,
) {
  const key = `${agent}:${type}`

  if (key.includes('planner') || key.includes('orchestrator')) {
    progress.plannerRunning ||= running
    progress.plannerDone ||= done
    progress.plannerFailed ||= failed
  }

  if (key.includes('executor')) {
    progress.executorRunning ||= running
    progress.executorDone ||= done
    progress.executorFailed ||= failed
  }

  if (key.includes('auditor') || key.includes('audit')) {
    progress.auditorRunning ||= running
    progress.auditorDone ||= done && status !== 'needs_revision'
    progress.auditorFailed ||= failed
    progress.auditorNeedsRevision ||= status === 'needs_revision'
  }

  if (key.includes('memory')) {
    progress.memoryRunning ||= running
    progress.memoryDone ||= done
  }

  if (key.includes('final') || type === 'run_completed' || type === 'run_failed') {
    progress.finalRunning ||= running
    progress.finalDone ||= done || type === 'run_completed'
  }
}

function parseFinalResult(
  raw: string,
  files: FileChange[],
  commands: CommandRun[],
  findings: AuditFinding[],
  gitStatusAfter?: string,
): FinalResult {
  const executedChecks = sectionLines(raw, 'Commands executed').map(stripBullet)
  const legacyChecks = sectionLines(raw, 'Checks run').map(stripBullet)
  const result: FinalResult = {
    status: readMarkdownSection(raw, 'Status') || (raw ? 'Completed' : undefined),
    summary: readMarkdownSection(raw, 'What changed') || raw,
    filesChanged: sectionLines(raw, 'Files changed').map(stripBullet),
    checksRun: executedChecks.length ? executedChecks : legacyChecks,
    gitStatusAfter,
    auditResult: readMarkdownSection(raw, 'Audit result') || (findings.length ? 'needs review' : undefined),
    remainingRisks: parseRemainingRisks(raw, findings),
    nextStep: readMarkdownSection(raw, 'Next recommended step'),
    raw,
  }
  result.nextPrompt = readMarkdownSection(raw, 'Next prompt') ?? result.nextStep
  if (result.filesChanged.length === 0) {
    result.filesChanged = files.map(file => file.path)
  }
  if (result.checksRun.length === 0) {
    result.checksRun = commands.map(command => command.command)
  }
  return result
}

function readMarkdownSection(raw: string, heading: string): string | undefined {
  return sectionLines(raw, heading).map(stripBullet).join('\n').trim() || undefined
}

function sectionLines(raw: string, heading: string): string[] {
  const lines = raw.split(/\r?\n/)
  const start = lines.findIndex(line => line.trim().toLowerCase() === `## ${heading}`.toLowerCase())
  if (start === -1) return []
  const out: string[] = []
  for (const line of lines.slice(start + 1)) {
    if (line.startsWith('## ')) break
    if (line.trim()) out.push(line.trim())
  }
  return out
}

function stripBullet(value: string) {
  return value.replace(/^[-*]\s+/, '').trim()
}

function parseRemainingRisks(raw: string, findings: AuditFinding[]): RiskItem[] {
  const lines = sectionLines(raw, 'Remaining risks').map(stripBullet)
  const parsed = parseRiskItems(lines)
  if (parsed.length > 0) return parsed

  if (findings.length > 0) {
    return findings.map(finding => ({
      issue: finding.title,
      severity: String(finding.severity),
      description: finding.description,
      recommendation: finding.suggested_fix,
    }))
  }

  return []
}

function inferAgent(type: string) {
  if (type === 'tool_call') return 'tools'
  if (type.includes('specialist_agent')) return 'specialist-agent'
  if (type === 'commands_executed') return 'executor'
  if (type.includes('planner')) return 'orchestrator'
  if (type.includes('executor')) return 'executor'
  if (type.includes('security')) return 'security-auditor'
  if (type.includes('auditor')) return 'auditor'
  if (type.includes('eval')) return 'evaluator'
  if (type.includes('final')) return 'final-reviewer'
  if (type.includes('router')) return 'router'
  if (type.includes('memory')) return 'memory'
  return 'system'
}

function titleForAgent(agent: string, artifacts: UnknownRecord = {}) {
  const specialist = asRecord(artifacts.specialist_agent)
  const displayName = stringOrUndefined(specialist?.display_name)
  if (displayName) return displayName

  return agent
    .split('-')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function normalizeStatus(status: string) {
  if (status === 'success') return 'completed'
  if (status === 'fail') return 'failed'
  return status
}

function summaryFromArtifacts(artifacts: UnknownRecord, type: string) {
  if (type.includes('planner') && asArray(artifacts.checklist).length) {
    return `Planner created ${asArray(artifacts.checklist).length}-step execution checklist.`
  }
  if (type.includes('executor') && asArray(artifacts.files_changed).length) {
    return `Changed ${asArray(artifacts.files_changed).length} file(s).`
  }
  if (type.includes('auditor') && asArray(artifacts.audit_findings).length) {
    return `Auditor found ${asArray(artifacts.audit_findings).length} item(s).`
  }
  if (type.includes('eval') && asRecord(artifacts.evaluation)) {
    const evalData = asRecord(artifacts.evaluation) ?? {}
    const score = typeof evalData.score === 'number' ? evalData.score : Number(evalData.score ?? NaN)
    const verdict = String(evalData.verdict ?? 'completed')
    return Number.isFinite(score)
      ? `Post-memory eval ${verdict} — ${score.toFixed(2)}`
      : `Post-memory eval ${verdict}`
  }
  if (type === 'commands_executed' && asArray(artifacts.commands_executed).length) {
    const rows = asArray(artifacts.commands_executed)
    const ok = rows.filter((row) => asRecord(row)?.ok === true).length
    return `${ok}/${rows.length} git command(s) executed.`
  }
  return undefined
}

function asChecklist(value: unknown): PlanChecklistItem[] {
  return asArray(value).map((item, index) => {
    const record = asRecord(item) ?? {}
    return {
      id: String(record.id ?? `plan-${index + 1}`),
      title: String(record.title ?? record.description ?? `Plan item ${index + 1}`),
      description: stringOrUndefined(record.description),
      owner: String(record.owner ?? 'executor'),
      status: String(record.status ?? 'pending'),
    }
  })
}

function asFileReads(value: unknown): FileRead[] {
  return asArray(value).map(item => asRecord(item)).filter(Boolean).map(record => ({
    path: String(record!.path ?? ''),
    reason: stringOrUndefined(record!.reason),
  })).filter(item => item.path)
}

function asFileChanges(value: unknown): FileChange[] {
  return asArray(value).map((item) => {
    if (typeof item === 'string') return { path: item, change_type: 'modified' }
    const record = asRecord(item) ?? {}
    const afterRaw = record.after ?? record.new_contents ?? record.contents
    return {
      path: String(record.path ?? ''),
      change_type: String(record.change_type ?? 'modified'),
      summary: stringOrUndefined(record.summary ?? record.description),
      why: stringOrUndefined(record.why),
      diff: stringOrUndefined(record.diff),
      after: typeof afterRaw === 'string' ? afterRaw : undefined,
      before: typeof record.before === 'string' ? record.before : undefined,
    }
  }).filter(item => item.path)
}

function asCommands(value: unknown): CommandRun[] {
  return asArray(value).map((item) => {
    if (typeof item === 'string') return { command: item, status: 'completed' }
    const record = asRecord(item) ?? {}
    return {
      command: String(record.command ?? ''),
      status: String(record.status ?? 'completed'),
      exit_code: numberOrUndefined(record.exit_code),
      duration_ms: numberOrUndefined(record.duration_ms),
      output_summary: stringOrUndefined(record.output_summary),
    }
  }).filter(item => item.command)
}

function asExecutedCommands(value: unknown): CommandRun[] {
  return asArray(value).map((item) => {
    const record = asRecord(item) ?? {}
    const ok = record.ok === true
    const skipped = record.skipped === true
    const stderr = stringOrUndefined(record.stderr ?? record.reason)
    return {
      command: String(record.command ?? ''),
      status: skipped ? 'skipped' : (ok ? 'completed' : 'failed'),
      exit_code: numberOrUndefined(record.exit_code),
      output_summary: stderr,
    }
  }).filter(item => item.command)
}

function asTests(value: unknown): TestRun[] {
  return asArray(value).map((item) => {
    const record = asRecord(item) ?? {}
    return {
      name: String(record.name ?? record.command ?? 'Test run'),
      status: String(record.status ?? record.result ?? 'not_run'),
      summary: stringOrUndefined(record.summary ?? record.output_summary),
    }
  })
}

function asAuditFindings(value: unknown): AuditFinding[] {
  return asArray(value).map((item, index) => {
    const record = asRecord(item) ?? {}
    return {
      id: stringOrUndefined(record.id) ?? `audit-${index + 1}`,
      severity: String(record.severity ?? 'low'),
      category: stringOrUndefined(record.category),
      title: String(record.title ?? record.issue ?? record.description ?? 'Audit finding'),
      description: stringOrUndefined(record.description ?? record.issue),
      suggested_fix: stringOrUndefined(record.suggested_fix ?? record.recommendation),
      status: stringOrUndefined(record.status) ?? 'open',
    }
  })
}

function parseJsonMaybe(value: unknown): UnknownRecord | undefined {
  if (typeof value !== 'string') return asRecord(value)
  try {
    return asRecord(JSON.parse(value))
  }
  catch {
    return undefined
  }
}

function asRecord(value: unknown): UnknownRecord | undefined {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    ? value as UnknownRecord
    : undefined
}

function asArray(value: unknown): unknown[] {
  return Array.isArray(value) ? value : []
}

function pushAll<T>(target: T[], values: T[]) {
  target.push(...values)
}

function dedupeBy<T>(items: T[], key: (item: T) => string) {
  const seen = new Set<string>()
  return items.filter((item) => {
    const id = key(item)
    if (!id || seen.has(id)) return false
    seen.add(id)
    return true
  })
}

function stringOrUndefined(value: unknown): string | undefined {
  if (value === undefined || value === null || value === '') return undefined
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)
  if (Array.isArray(value)) {
    const lines = value.map((item) => {
      if (typeof item === 'string') return item
      if (item && typeof item === 'object') return JSON.stringify(item)
      return String(item)
    }).filter(Boolean)
    return lines.length ? lines.join('\n') : undefined
  }
  if (typeof value === 'object') {
    try {
      return JSON.stringify(value)
    }
    catch {
      return undefined
    }
  }
  return String(value)
}

function numberOrUndefined(value: unknown): number | undefined {
  const n = Number(value)
  return Number.isFinite(n) ? n : undefined
}
