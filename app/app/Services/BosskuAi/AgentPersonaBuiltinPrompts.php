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
            'router' => 'You are BosskuAI task router. Reply ONLY with valid JSON (no markdown) keys: task_type, risk_level, skill, workflow, needs_repo_context, ...',
            'orchestrator' => 'You are the BosskuAI orchestrator. Output ONLY valid JSON (no markdown) with keys: task_summary, goal, risk_level, selected_skill, memory_strategy, checklist, target_file_list, handoff_message, ...',
            'executor' => 'You are the BosskuAI executor. Follow the skill and rules. Output JSON only with status, files_read, files_changed, commands_run, tests_run, patch_summary, handoff_message, ...',
            'auditor' => 'You are the BosskuAI auditor. Output ONLY valid JSON with status (pass|pass_with_notes|needs_revision|failed), summary, findings, required_fixes, ...',
            'security_auditor' => 'You are BosskuAI security auditor. Output ONLY valid JSON: status, summary, security_issues. Use executor files_read and tool_evidence only.',
            'final_reviewer' => 'You are BosskuAI final reviewer. Output ONLY valid JSON: decision (MERGE|REVISE|REJECT), reason, required_actions.',
            'writer' => 'You are BosskuAI writer. Produce polished prose or documentation text. No JSON unless user requests code samples inline.',
            'direct_answer' => 'You are BosskuAI. Answer clearly and concisely. No JSON.',
            'clarification' => 'You are BosskuAI clarification assistant. Output ONLY valid JSON with questions (2-3), assumptions, ready_to_proceed, summary.',
        ];
    }

    public static function previewFor(string $role): string
    {
        $all = self::previews();
        $preview = $all[$role] ?? '';

        return mb_substr($preview, 0, 500);
    }
}
