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
import { formatAgentStepOutput, parseRiskItems } from '../utils/humanizeOutput'

type UnknownRecord = Record<string, unknown>

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
    const agent = String(event.agent ?? metadata.agent ?? inferAgent(type))
    const status = normalizeStatus(String(event.status ?? item.status ?? metadata.status ?? 'completed'))

    if (type === 'memory_retrieved' || asArray(event.memory_used).length > 0 || asArray(artifacts.memory_used).length > 0) {
      memoryUsed = true
    }

    applyRouting(event, artifacts, routingSummary, type)
    pushAll(checklist, asChecklist(artifacts.checklist))
    pushAll(filesRead, asFileReads(artifacts.files_read))
    pushAll(filesChanged, asFileChanges(artifacts.files_changed))
    pushAll(commandsRun, asCommands(artifacts.commands_run))
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
        title: titleForAgent(agent),
        status,
        model_role: stringOrUndefined(event.model_role ?? metadata.model_role),
        model: stringOrUndefined(event.model ?? item.model ?? metadata.model),
        summary: existingSummary ?? formatted.summary,
        message: formatted.detail,
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

  return {
    agentMessages: messages,
    handoffNodes: buildHandoff(messages),
    checklist: dedupeBy(checklist, item => item.id || item.title),
    filesRead: dedupeBy(filesRead, item => item.path),
    filesChanged: dedupeBy(filesChanged, item => `${item.change_type}:${item.path}:${item.summary ?? ''}`),
    commandsRun,
    testsRun,
    auditFindings: dedupeBy(auditFindings, item => item.id || item.title),
    finalResult: parseFinalResult(finalRaw, filesChanged, commandsRun, auditFindings),
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
  if (route) {
    routing.workflow = stringOrUndefined(route.workflow) ?? routing.workflow
    routing.skill = stringOrUndefined(route.skill) ?? routing.skill
    routing.riskLevel = stringOrUndefined(route.risk_level) ?? routing.riskLevel
  }
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
  if (agent === 'executor') return 'coding'
  if (agent === 'auditor' || agent === 'security-auditor') return 'review'
  if (agent === 'router' || agent === 'memory') return 'fast'
  return 'system'
}

function buildHandoff(messages: AgentMessage[]): HandoffNode[] {
  return WORKFLOW.map((node) => {
    const matches = messages.filter(message => message.agent === node.agent)
    const revision = node.label.includes('revision')
      ? messages.find(message => String(message.id).includes('executor_revision') || String(message.summary ?? '').toLowerCase().includes('revision'))
      : undefined
    const message = revision ?? matches.at(-1)
    return { ...node, status: message?.status ?? node.status }
  })
}

function parseFinalResult(raw: string, files: FileChange[], commands: CommandRun[], findings: AuditFinding[]): FinalResult {
  const result: FinalResult = {
    status: readMarkdownSection(raw, 'Status') || (raw ? 'Completed' : undefined),
    summary: readMarkdownSection(raw, 'What changed') || raw,
    filesChanged: sectionLines(raw, 'Files changed').map(stripBullet),
    checksRun: sectionLines(raw, 'Checks run').map(stripBullet),
    auditResult: readMarkdownSection(raw, 'Audit result') || (findings.length ? 'needs review' : undefined),
    remainingRisks: parseRemainingRisks(raw, findings),
    nextStep: readMarkdownSection(raw, 'Next recommended step'),
    raw,
  }
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
  if (type.includes('planner')) return 'orchestrator'
  if (type.includes('executor')) return 'executor'
  if (type.includes('security')) return 'security-auditor'
  if (type.includes('auditor')) return 'auditor'
  if (type.includes('final')) return 'final-reviewer'
  if (type.includes('router')) return 'router'
  if (type.includes('memory')) return 'memory'
  return 'system'
}

function titleForAgent(agent: string) {
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
  return value === undefined || value === null || value === '' ? undefined : String(value)
}

function numberOrUndefined(value: unknown): number | undefined {
  const n = Number(value)
  return Number.isFinite(n) ? n : undefined
}
