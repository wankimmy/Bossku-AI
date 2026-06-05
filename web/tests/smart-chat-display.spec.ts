import { describe, expect, it } from 'vitest'
import {
  isShortChatWorkflow,
  shouldFocusAgentWorkflow,
  shouldShowRunSummaryCard,
} from '../utils/smartChatDisplay'

describe('smart chat display policy', () => {
  it('keeps direct answers in the chat lane without run summary cards', () => {
    const routing = {
      backend: 'Ollama' as const,
      workflow: 'direct_answer',
      pipelineAgents: ['direct_answer'],
    }

    expect(isShortChatWorkflow(routing)).toBe(true)
    expect(shouldFocusAgentWorkflow(routing)).toBe(false)
    expect(shouldShowRunSummaryCard(routing, true)).toBe(false)
  })

  it('keeps writer-only short paths in the chat lane', () => {
    const routing = {
      backend: 'Ollama' as const,
      workflow: 'writer_only',
      pipelineAgents: ['writer'],
    }

    expect(isShortChatWorkflow(routing)).toBe(true)
    expect(shouldFocusAgentWorkflow(routing)).toBe(false)
    expect(shouldShowRunSummaryCard(routing, true)).toBe(false)
  })

  it('focuses agent panels for executor or review workflows', () => {
    expect(shouldFocusAgentWorkflow({
      backend: 'Ollama',
      workflow: 'orchestrator_executor',
      pipelineAgents: ['orchestrator', 'executor'],
    })).toBe(true)

    expect(shouldFocusAgentWorkflow({
      backend: 'Ollama',
      workflow: 'orchestrator_executor_auditor_security',
      pipelineAgents: ['orchestrator', 'executor', 'auditor', 'security-auditor'],
    })).toBe(true)
  })

  it('still shows run summary cards for non-short workflows', () => {
    expect(shouldShowRunSummaryCard({
      backend: 'Ollama',
      workflow: 'orchestrator_executor',
    }, true)).toBe(true)

    expect(shouldShowRunSummaryCard({
      backend: 'Ollama',
      workflow: 'orchestrator_executor',
    }, false)).toBe(false)
  })
})
