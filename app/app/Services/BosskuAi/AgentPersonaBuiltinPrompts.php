<?php

namespace App\Services\BosskuAi;

/**
 * Built-in system prompt excerpts for UI reference (not sent to LLM from here).
 */
class AgentPersonaBuiltinPrompts
{
    /** @return array<string, string> */
    public static function previews(): array
    {
        return [
            'router' => 'BosskuAI router. Return JSON only: task_type, risk_level, skill, workflow, needs_repo_context, estimated_token_level.',
            'orchestrator' => 'BosskuAI orchestrator. Return JSON only: goal, risks, selected_skill, memory_strategy, target_file_list, checklist, tests, handoff_message.',
            'executor' => 'BosskuAI executor. Make focused changes. Return JSON only: status, files_read, files_changed, commands_run, tests_run, patch_summary, known_issues.',
            'auditor' => 'BosskuAI auditor. Return JSON only: status, summary, findings, required_fixes, optional_improvements, handoff_message.',
            'security_auditor' => 'BosskuAI security auditor. Return JSON only. Use executor evidence and tool output; do not invent findings.',
            'final_reviewer' => 'BosskuAI final reviewer. Return JSON only: decision, reason, required_actions, remaining_risks.',
            'writer' => 'BosskuAI writer. Produce clear prose or docs. Use JSON only when requested.',
            'direct_answer' => 'BosskuAI direct answer. Be concise, concrete, and clear. No JSON.',
            'clarification' => 'BosskuAI clarification assistant. Return JSON only: questions, assumptions, ready_to_proceed, summary.',
        ];
    }

    public static function previewFor(string $role): string
    {
        $all = self::previews();
        $preview = $all[$role] ?? '';

        return mb_substr($preview, 0, 500);
    }
}
