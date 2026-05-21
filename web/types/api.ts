export interface Run {
  id: string
  prompt: string
  status: 'running' | 'completed' | 'failed' | 'paused' | string
  risk_level?: 'low' | 'medium' | 'high' | 'critical' | string
  estimated_cost?: number
  created_at: string
  updated_at?: string
  final_output?: string
  metadata?: Record<string, unknown>
  steps?: RunStep[]
}

export interface RunStep {
  id: string
  run_id: string
  agent: string
  type: string
  status: string
  output?: unknown
  latency_ms?: number
  token_count?: number
  cost?: number
  created_at: string
}

export interface AgentMessage {
  id: string
  run_id?: string
  agent: string
  title: string
  status: string
  model_role?: string
  model?: string
  skill?: string
  summary?: string
  message?: string
  content?: string
  safe_reasoning_summary?: string
  memory_used?: boolean
  from_agent?: string
  to_agent?: string
  latency_ms?: number
  token_estimate?: number
  artifacts?: Record<string, unknown>
  created_at?: string
}

export interface Skill {
  id: string
  name: string
  description?: string
  category?: string
  trigger?: string
  steps?: unknown[]
  quality_score?: number
  run_count?: number
  success_rate?: number
  avg_latency_ms?: number
  is_active?: boolean
  created_at?: string
  updated_at?: string
}

export interface SkillCandidate {
  id: string
  name?: string
  description?: string
  category?: string
  trigger_pattern?: string
  confidence?: number
  approval_status: 'pending' | 'approved' | 'rejected' | string
  source_run_id?: string
  created_at?: string
}

export interface LlmProvider {
  id: string
  name: string
  type: string
  base_url?: string
  api_key_masked?: string
  is_active: boolean
  health_status: 'healthy' | 'degraded' | 'offline' | string
  last_checked_at?: string
  created_at?: string
}

export interface ModelRoute {
  id: string
  role: string
  primary_model: string
  fallback_model?: string
  provider_id?: string
  is_active?: boolean
}

export interface FeedbackItem {
  id: string
  target_type: string
  target_id?: string
  signal: 'positive' | 'negative' | 'neutral' | string
  rating?: number
  comment?: string
  processed: boolean
  created_at?: string
}

export interface LearningEvent {
  id: string
  type: string
  content?: string
  source_run_id?: string
  confidence?: number
  status: 'pending' | 'accepted' | 'rejected' | string
  created_at?: string
}

export interface SoulVersion {
  id: string
  version: number
  content: string
  change_summary?: string
  is_active: boolean
  created_at?: string
}

export interface SkillCandidateExtended extends SkillCandidate {
  quality_score?: number
  source_run_count?: number
  approval_status: 'draft' | 'pending_review' | 'approved' | 'rejected' | string
}

export interface GraphNode {
  id: string
  label: string
  type?: string
  has_conflict?: boolean
  confidence?: number
  source_type?: string
  source_id?: string
  properties?: Record<string, unknown>
  metadata?: Record<string, unknown>
}

export interface GraphEdge {
  id: string
  source_id: string
  target_id: string
  relation?: string
  is_conflict?: boolean
  weight?: number
}

export interface KnowledgeGraphResponse {
  nodes: GraphNode[]
  edges: GraphEdge[]
}

export interface WorkspaceGraphNode {
  id: string
  label: string
  category: string
  depth: 'DEEP' | 'OK' | 'THIN' | string
  is_marquee?: boolean
  is_core?: boolean
  skill_lines?: number
  playbook_lines?: number
  total_lines?: number
  triggers?: string[]
  keywords?: string[]
  trigger_count?: number
  description?: string
  playbook_refs?: string[]
  has_conflict?: boolean
  confidence?: number
  source_type?: string
  source_id?: string
  type?: string
  properties?: Record<string, unknown>
}

export interface WorkspaceGraphEdge {
  source: string
  target: string
  kind: string
  is_conflict?: boolean
  weight?: number
}

export interface WorkspaceGraphResponse {
  version?: string
  repo_root?: string
  active_repo_root?: string | null
  toolkit_repo_root?: string
  skills_source?: 'active' | 'toolkit'
  error?: string
  node_count?: number
  edge_count?: number
  categories?: Record<string, number>
  nodes: WorkspaceGraphNode[]
  edges: WorkspaceGraphEdge[]
}

export interface Approval {
  id: string
  run_id?: string
  step_id?: string
  operation_type: string
  description?: string
  risk_level?: string
  evidence?: Record<string, unknown>
  status: 'pending' | 'approved' | 'rejected' | string
  decision_note?: string
  created_at?: string
  decided_at?: string
}

export interface UsageEvent {
  id: string
  run_id?: string
  provider_id?: string
  model?: string
  prompt_tokens?: number
  completion_tokens?: number
  total_tokens?: number
  cost?: number
  created_at?: string
}

export interface UsageSummary {
  total_tokens: number
  total_cost: number
  by_provider?: Record<string, { tokens: number; cost: number }>
}

export interface LogEntry {
  id?: string
  timestamp?: string
  level: 'debug' | 'info' | 'warning' | 'error' | string
  channel?: string
  message: string
  source?: string
  context?: Record<string, unknown>
}

export interface Plugin {
  id: string
  name: string
  version?: string
  author?: string
  description?: string
  is_active: boolean
  last_heartbeat?: string
  config?: Record<string, unknown>
}

export interface Agent {
  id?: string
  role: string
  run_count?: number
  avg_latency_ms?: number
  success_rate?: number
  last_active_at?: string
}

export interface BrainData {
  learning_events?: {
    pending?: number
    accepted?: number
    rejected?: number
    total?: number
  }
  skill_candidates?: {
    pending?: number
    approved?: number
    rejected?: number
    total?: number
  }
  unprocessed_feedback?: number
  knowledge_nodes?: number
  conflicts?: number
  memory_count?: number
  memory_confidence?: {
    avg?: number | null
    min?: number | null
    max?: number | null
  }
}

export interface MemoryRecord {
  id: string
  type?: string
  content?: string
  human_summary?: string | null
  confidence?: number
  source?: string | null
  tags?: string[] | null
  is_active?: boolean
  updated_at?: string
}
