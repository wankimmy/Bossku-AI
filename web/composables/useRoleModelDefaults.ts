export type AgentRoleDef = {
  role: string
  label: string
  formKey: string
}

export const AGENT_ROLE_DEFS: AgentRoleDef[] = [
  { role: 'router', label: 'Router', formKey: 'router_model' },
  { role: 'direct_answer', label: 'Direct answer', formKey: 'direct_answer_model' },
  { role: 'orchestrator', label: 'Orchestrator / Planner', formKey: 'orchestrator_model' },
  { role: 'executor', label: 'Executor / Coder', formKey: 'coding_model' },
  { role: 'auditor', label: 'Auditor', formKey: 'auditor_model' },
  { role: 'security_auditor', label: 'Security auditor', formKey: 'security_auditor_model' },
  { role: 'final_reviewer', label: 'Final reviewer', formKey: 'final_reviewer_model' },
  { role: 'writer', label: 'Writer', formKey: 'writer_model' },
]

export function defaultProviderForRole(
  groups: { provider: string, configured: boolean, recommended_models: { id: string }[] }[],
  role: string,
): string {
  const configured = groups.filter(g => g.configured && g.recommended_models.length > 0)
  return configured[0]?.provider ?? 'ollama-cloud'
}
