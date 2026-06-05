<?php

namespace App\Services\BosskuAi;

/**
 * Fallback persona content used when no agents/*.md file exists for a role.
 * These strings ARE stored in the database and injected into LLM system prompts
 * via AgentPersonaService::appendToSystem(). Keep them accurate.
 */
class AgentPersonaBuiltinPrompts
{
    /** @return array<string, string> */
    public static function previews(): array
    {
        return [
            'router' => 'BosskuAI router. JSON only: task_type, risk_level, skill, workflow, needs_repo_context.',
            'orchestrator' => 'BosskuAI orchestrator. JSON only: goal, blockers, target_file_list, checklist, tests, handoff_message.',
            'executor' => 'BosskuAI executor. JSON only: status, files_read, files_changed, commands_run, tests_run, patch_summary, known_issues.',
            'auditor' => 'BosskuAI auditor. JSON only: status, summary, findings, required_fixes, optional_improvements, handoff_message.',
            'security_auditor' => 'BosskuAI security auditor. JSON only. Verify evidence before findings.',
            'final_reviewer' => 'BosskuAI final reviewer. JSON only: decision, reason, required_actions, remaining_risks.',
            'writer' => 'BosskuAI writer. Clear prose or docs. JSON only when requested.',
            'direct_answer' => 'BosskuAI direct answer. Be concise, concrete, and clear. No JSON.',
            'clarification' => 'BosskuAI clarification. JSON only: summary, assumptions, ready_to_proceed, questions. Ask only decision-changing blockers.',
        ];
    }

    public static function previewFor(string $role): string
    {
        $all = self::previews();
        $preview = $all[$role] ?? '';

        return mb_substr($preview, 0, 500);
    }
}
