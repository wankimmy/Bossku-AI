import { formatToolCallSummary, isToolCallEvent } from './formatToolCall'

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

export interface FormattedAgentOutput {
  summary: string
  detail?: string
  risks?: RiskItem[]
  router?: RouterDisplay
}

type UnknownRecord = Record<string, unknown>

export function tryParseJson(value: unknown): UnknownRecord | null {
  if (value === null || value === undefined || value === '') return null
  if (typeof value === 'object' && !Array.isArray(value)) return value as UnknownRecord
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) return null
  try {
    const parsed = JSON.parse(trimmed)
    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)
      ? parsed as UnknownRecord
      : null
  }
  catch {
    return null
  }
}

export function parseRiskItems(lines: string[]): RiskItem[] {
  const out: RiskItem[] = []
  for (const line of lines) {
    const item = parseRiskLine(line)
    if (item) out.push(item)
  }
  return out
}

export function parseRiskLine(line: string): RiskItem | null {
  const trimmed = line.replace(/^[-*]\s+/, '').trim()
  if (!trimmed) return null

  const parsed = tryParseJson(trimmed)
  if (parsed) return riskFromRecord(parsed)

  const issueMatch = trimmed.match(/^\[?(critical|high|medium|low)\]?\s*(.+)$/i)
  if (issueMatch) {
    return {
      severity: issueMatch[1].toLowerCase(),
      issue: issueMatch[2].trim(),
    }
  }

  return { issue: trimmed, severity: 'medium' }
}

export function riskFromRecord(record: UnknownRecord): RiskItem | null {
  const issue = String(
    record.issue ?? record.title ?? record.description ?? '',
  ).trim()
  if (!issue) return null

  return {
    issue,
    severity: String(record.severity ?? 'medium').toLowerCase(),
    location: stringOrUndefined(record.location),
    description: stringOrUndefined(record.description ?? record.issue),
    recommendation: stringOrUndefined(record.recommendation ?? record.suggested_fix),
  }
}

export function risksFromSecurityPayload(data: UnknownRecord): RiskItem[] {
  const issues = Array.isArray(data.security_issues) ? data.security_issues : []
  return issues
    .map(item => (typeof item === 'object' && item !== null ? riskFromRecord(item as UnknownRecord) : null))
    .filter((item): item is RiskItem => item !== null)
}

export function formatRouterDisplay(data: UnknownRecord): RouterDisplay | null {
  const primary = asRecord(data.primary_skill)
  if (!primary?.name) return null

  const secondary = asArray(data.secondary_skills)
    .map(item => asRecord(item))
    .filter(Boolean)
    .map(record => ({
      name: String(record!.name ?? ''),
      reason: stringOrUndefined(record!.reason),
    }))
    .filter(item => item.name)
    .slice(0, 5)

  return {
    primarySkill: String(primary.name),
    primaryReason: stringOrUndefined(primary.reason),
    secondarySkills: secondary,
    rulesCount: asArray(data.rules).length,
    playbooksCount: asArray(data.playbooks).length,
    checklistsCount: asArray(data.checklists).length,
  }
}

export function summarizeRouter(router: RouterDisplay): string {
  const secondary = router.secondarySkills.length
    ? ` (+${router.secondarySkills.length} secondary skill${router.secondarySkills.length === 1 ? '' : 's'})`
    : ''
  return `Routed to ${router.primarySkill}${secondary}`
}

export function formatAgentStepOutput(
  agent: string,
  rawText: string | undefined,
  artifacts?: UnknownRecord,
): FormattedAgentOutput {
  const parsed = tryParseJson(rawText)
  if (!parsed) {
    const text = (rawText ?? '').trim()
    if (!text) {
      return { summary: summaryFromArtifacts(agent, artifacts) }
    }
    if (looksLikeJsonBlob(text)) {
      return { summary: 'Step completed.', detail: truncate(text, 400) }
    }
    return { summary: truncate(text, 280), detail: text.length > 280 ? text : undefined }
  }

  if (agent === 'router' || parsed.primary_skill) {
    const router = formatRouterDisplay(parsed)
    if (router) {
      return {
        summary: summarizeRouter(router),
        router,
        detail: router.primaryReason,
      }
    }
  }

  if (agent === 'security-auditor' || Array.isArray(parsed.security_issues)) {
    const risks = risksFromSecurityPayload(parsed)
    const summary = String(parsed.summary ?? '').trim()
      || (risks.length ? `Security review: ${risks.length} issue(s)` : 'Security review completed.')
    return {
      summary,
      risks,
      detail: risks.length === 0 ? stringOrUndefined(parsed.summary) : undefined,
    }
  }

  if (agent === 'evaluator' || parsed.verdict || parsed.score !== undefined || parsed.dimensions) {
    const score = typeof parsed.score === 'number' ? parsed.score : Number(parsed.score ?? NaN)
    const verdict = String(parsed.verdict ?? parsed.status ?? 'completed').trim() || 'completed'
    const percent = Number.isFinite(score) ? `${score.toFixed(2)}` : ''
    const summary = percent
      ? `Post-memory eval ${verdict} — ${percent}`
      : `Post-memory eval ${verdict}`
    return {
      summary,
      detail: stringOrUndefined(parsed.summary ?? parsed.recommendation ?? parsed.message),
    }
  }

  if (agent === 'auditor' && Array.isArray(parsed.findings)) {
    const count = parsed.findings.length
    const status = String(parsed.status ?? 'completed')
    return {
      summary: `Audit ${status}${count ? ` — ${count} finding(s)` : ''}`,
      detail: stringOrUndefined(parsed.summary ?? parsed.final_output),
    }
  }

  if (parsed.summary) {
    const risks = risksFromSecurityPayload(parsed)
    if (risks.length) {
      return { summary: String(parsed.summary), risks }
    }
    return {
      summary: String(parsed.summary),
      detail: stringOrUndefined(parsed.message ?? parsed.final_output),
    }
  }

  if (parsed.status) {
    return {
      summary: `Status: ${String(parsed.status)}`,
      detail: stringOrUndefined(parsed.patch_summary ?? parsed.message),
    }
  }

  return { summary: 'Step completed.', detail: truncate(JSON.stringify(parsed), 500) }
}

export function humanizeActivitySummary(
  agent: string,
  evt: UnknownRecord,
): string {
  if (isToolCallEvent(evt)) {
    return formatToolCallSummary(evt)
  }

  const meta = asRecord(evt.metadata) ?? {}
  const raw = String(
    evt.error ?? evt.summary ?? meta.summary ?? evt.output ?? meta.message ?? meta.error ?? '',
  ).trim()
  if (!raw) return ''

  const formatted = formatAgentStepOutput(
    agent || inferAgentFromType(String(evt.type ?? '')),
    raw,
    mergeArtifacts(evt, meta),
  )
  return formatted.summary
}

function summaryFromArtifacts(agent: string, artifacts?: UnknownRecord): string {
  if (!artifacts) return ''
  if (agent === 'orchestrator' && asArray(artifacts.checklist).length) {
    return `Planner created ${asArray(artifacts.checklist).length}-step checklist.`
  }
  if (agent === 'executor' && asArray(artifacts.files_changed).length) {
    return `Changed ${asArray(artifacts.files_changed).length} file(s).`
  }
  if (agent === 'auditor' && asArray(artifacts.audit_findings).length) {
    return `Auditor found ${asArray(artifacts.audit_findings).length} item(s).`
  }
  if (agent === 'evaluator' && asRecord(artifacts.evaluation)) {
    const evalData = asRecord(artifacts.evaluation) ?? {}
    const score = typeof evalData.score === 'number' ? evalData.score : Number(evalData.score ?? NaN)
    const verdict = String(evalData.verdict ?? 'completed')
    return Number.isFinite(score)
      ? `Post-memory eval ${verdict} — ${score.toFixed(2)}`
      : `Post-memory eval ${verdict}`
  }
  if (asArray(artifacts.commands_executed).length) {
    const rows = asArray(artifacts.commands_executed)
    const ok = rows.filter(row => asRecord(row)?.ok === true).length
    return `${ok}/${rows.length} git command(s) executed.`
  }
  return ''
}

function inferAgentFromType(type: string): string {
  if (type.includes('planner')) return 'orchestrator'
  if (type.includes('executor')) return 'executor'
  if (type.includes('security')) return 'security-auditor'
  if (type.includes('auditor')) return 'auditor'
  if (type.includes('eval')) return 'evaluator'
  if (type.includes('final')) return 'final-reviewer'
  if (type.includes('router')) return 'router'
  return 'system'
}

function mergeArtifacts(evt: UnknownRecord, meta: UnknownRecord): UnknownRecord {
  return { ...(asRecord(meta.artifacts) ?? {}), ...(asRecord(evt.artifacts) ?? {}) }
}

function looksLikeJsonBlob(text: string): boolean {
  return text.startsWith('{') && text.includes('"') && text.length > 120
}

function truncate(text: string, max: number): string {
  if (text.length <= max) return text
  return `${text.slice(0, max)}…`
}

function asRecord(value: unknown): UnknownRecord | undefined {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    ? value as UnknownRecord
    : undefined
}

function asArray(value: unknown): unknown[] {
  return Array.isArray(value) ? value : []
}

function stringOrUndefined(value: unknown): string | undefined {
  return value === undefined || value === null || value === '' ? undefined : String(value)
}

export function severityBadgeClass(severity: string): string {
  const s = severity.toLowerCase()
  if (s === 'critical' || s === 'high') return 'border-rose-500/60 bg-rose-950/40 text-rose-300'
  if (s === 'medium') return 'border-amber-500/60 bg-amber-950/40 text-amber-300'
  return 'border-zinc-600 bg-zinc-800/60 text-zinc-300'
}
