<?php

namespace App\Services\BosskuAi;

class DeterministicTaskClassifier
{
    /**
     * Lightweight heuristics when LLM router is disabled or as prior.
     *
     * @return array<string, mixed>
     */
    public function classify(string $prompt): array
    {
        $lower = mb_strtolower($prompt);

        $taskType = 'unknown';
        $skill = 'generic';
        $workflow = 'orchestrator_executor_auditor';
        $needsRepo = true;
        $needsFileEdit = true;
        $needsTest = false;
        $needsExecutor = true;
        $needsAuditor = true;
        $needsSecurity = false;
        $needsFinal = false;
        $executorProfile = 'default';
        $memoryMode = 'read_and_write';
        $tokenLevel = 'medium';
        $auditMode = 'standard';

        // Repository audit / review — must run executor + read files (never orchestrator_only)
        if (RepoTaskDetector::requiresRepositoryAccess($prompt)) {
            $auditMode = RepoTaskDetector::isFullRepositoryAudit($prompt) ? 'full' : 'repo';
            $taskType = 'code_edit';
            $skill = 'generic';
            $workflow = $auditMode === 'full'
                ? 'orchestrator_executor_auditor_security'
                : 'orchestrator_executor_auditor';
            $needsRepo = true;
            $needsFileEdit = false;
            $needsExecutor = true;
            $needsAuditor = true;
            $needsSecurity = $auditMode === 'full';
            $needsFinal = false;
            $needsTest = $auditMode === 'full';
            $executorProfile = 'default';
            $memoryMode = 'read_only';
            $tokenLevel = $auditMode === 'full' ? 'high' : 'medium';
        }

        // Smoke / connectivity checks (no repo work)
        if (preg_match('/^(test|ping|hello|hi|hey)\s*[!?.]*$/i', trim($prompt))) {
            $taskType = 'question';
            $skill = 'generic';
            $workflow = 'direct_answer';
            $needsRepo = false;
            $needsFileEdit = false;
            $needsExecutor = false;
            $needsAuditor = false;
            $needsSecurity = false;
            $needsFinal = false;
            $needsTest = false;
            $executorProfile = 'none';
            $memoryMode = 'none';
            $tokenLevel = 'low';
        }

        // Snippet / example only (no repo edit implied), before broad "question" detection
        if (
            (str_contains($lower, 'example') || str_contains($lower, 'snippet'))
            && ! str_contains($lower, 'fix ')
            && ! str_contains($lower, 'update ')
            && ! preg_match('/^(explain|what is|what are|how does|why|define)\b/i', trim($prompt))
        ) {
            $taskType = 'code_generation';
            $skill = 'laravel';
            $workflow = 'orchestrator_only';
            $needsFileEdit = false;
            $needsExecutor = false;
            $needsAuditor = false;
            $executorProfile = 'none';
            $memoryMode = 'read_only';
            $tokenLevel = 'low';
        }

        // Questions
        if (preg_match('/^(explain|what is|what are|how does|why|define)\b/i', trim($prompt))
            || (str_contains($lower, 'policy') && str_contains($lower, 'gate') && str_contains($lower, 'vs'))) {
            $taskType = 'question';
            $skill = 'laravel';
            if (! str_contains($lower, 'laravel') && str_contains($lower, 'react')) {
                $skill = 'react';
            }
            $workflow = 'direct_answer';
            $needsRepo = str_contains($lower, 'repo') || str_contains($lower, 'my project') || str_contains($lower, 'this codebase');
            $needsFileEdit = false;
            $needsExecutor = false;
            $needsAuditor = false;
            $needsTest = false;
            $executorProfile = 'none';
            $memoryMode = $needsRepo ? 'read_only' : 'none';
            $tokenLevel = 'low';
        }

        // Marketing / social
        if (preg_match('/\b(social media|instagram|twitter|linkedin post|vendor signup)\b/i', $prompt)) {
            $taskType = 'marketing';
            $skill = 'marketing';
            $workflow = 'writer_only';
            $needsRepo = false;
            $needsFileEdit = false;
            $needsExecutor = false;
            $needsAuditor = false;
            $needsTest = false;
            $executorProfile = 'none';
            $memoryMode = 'read_only';
            $tokenLevel = 'low';
        }

        // Documentation
        if (str_contains($lower, 'readme') || (str_contains($lower, 'documentation') && str_contains($lower, 'update'))) {
            $taskType = 'documentation';
            $skill = 'documentation';
            $workflow = 'writer_only';
            $needsRepo = str_contains($lower, 'repo');
            $needsFileEdit = str_contains($lower, 'update') || str_contains($lower, 'edit');
            if (! $needsFileEdit) {
                $needsExecutor = false;
            }
            $needsAuditor = $needsFileEdit;
            $needsFinal = false;
            $memoryMode = 'read_only';
            $tokenLevel = 'low';
        }

        // Payment
        if (str_contains($lower, 'payment') || (str_contains($lower, 'webhook') && str_contains($lower, 'signature'))) {
            $taskType = 'payment';
            $skill = 'security';
            $workflow = 'orchestrator_executor_auditor_security_final_reviewer';
            $executorProfile = 'high_risk';
            $needsSecurity = true;
            $needsFinal = true;
            $tokenLevel = 'very_high';
        }

        // Auth
        if (str_contains($lower, 'authentication') || str_contains($lower, 'middleware') && str_contains($lower, 'auth')) {
            $taskType = 'authentication';
            $skill = 'security';
            $workflow = 'orchestrator_executor_auditor_security_final_reviewer';
            $executorProfile = 'high_risk';
            $needsSecurity = true;
            $needsFinal = true;
        }

        // Policy (Laravel) — code/auth work, not explanatory "policy vs gate" questions
        if (
            $workflow !== 'direct_answer'
            && str_contains($lower, 'policy')
            && (str_contains($lower, 'laravel') || str_contains($lower, 'gate'))
        ) {
            $taskType = 'authorization';
            $skill = 'laravel';
            $workflow = 'orchestrator_executor_auditor_security_final_reviewer';
            $executorProfile = 'high_risk';
            $needsSecurity = true;
            $needsFinal = true;
        }

        // UI
        if (str_contains($lower, 'button') || str_contains($lower, 'spacing') || str_contains($lower, 'dashboard') && str_contains($lower, 'mobile')) {
            $taskType = 'ui_ux';
            $skill = 'uiux';
            $executorProfile = 'frontend_ui';
        }

        // Redis
        if (str_contains($lower, 'redis')) {
            $skill = 'redis';
            $executorProfile = 'backend';
        }

        // Laravel typo fix
        if (str_contains($lower, 'validation message') && str_contains($lower, 'typo')) {
            $taskType = 'bug_fix';
            $skill = 'laravel';
            $executorProfile = 'backend';
            $workflow = 'orchestrator_executor_auditor';
            $needsFinal = false;
            $needsSecurity = false;
        }

        // Migration
        if (str_contains($lower, 'migration') && str_contains($lower, 'subscription')) {
            $taskType = 'database';
            $skill = 'laravel';
            $executorProfile = 'high_risk';
            $workflow = 'orchestrator_executor_auditor_security_final_reviewer';
            $needsSecurity = true;
            $needsFinal = true;
        }

        // DevOps deploy
        if (str_contains($lower, 'deploy') && (str_contains($lower, 'docker') || str_contains($lower, 'nginx') || str_contains($lower, 'ssl'))) {
            $taskType = 'deployment';
            $skill = 'devops';
            $executorProfile = 'devops';
            $workflow = 'orchestrator_executor_auditor_security_final_reviewer';
            $needsSecurity = true;
            $needsFinal = true;
        }

        $riskEngine = new RiskRuleEngine;
        $det = $riskEngine->deterministicRisk($prompt);
        $risk = $det['risk'];

        if ($risk === 'high') {
            if ($executorProfile !== 'devops') {
                $executorProfile = 'high_risk';
            }
            $needsSecurity = true;
            $needsFinal = true;
            if (! str_contains($workflow, 'final_reviewer')) {
                $workflow = 'orchestrator_executor_auditor_security_final_reviewer';
            }
        } elseif ($risk === 'medium') {
            $needsFinal = false;
            if ($taskType === 'unknown') {
                $workflow = 'orchestrator_executor_auditor';
            }
        } else {
            $needsFinal = false;
            if ($workflow === 'orchestrator_executor_auditor' && in_array($taskType, ['question', 'marketing', 'documentation'], true)) {
                // already set
            }
        }

        return [
            'task_type' => $taskType,
            'audit_mode' => $auditMode,
            'risk_level' => $risk,
            'skill' => $skill,
            'workflow' => $workflow,
            'needs_repo_context' => $needsRepo,
            'needs_file_edit' => $needsFileEdit,
            'needs_test_run' => $needsTest,
            'needs_executor' => $needsExecutor,
            'needs_auditor' => $needsAuditor,
            'needs_security_auditor' => $needsSecurity,
            'needs_final_reviewer' => $needsFinal,
            'executor_profile' => $executorProfile,
            'memory_mode' => $memoryMode,
            'estimated_token_level' => $tokenLevel,
            'reason' => 'Heuristic classification',
            '_deterministic_risk' => $det,
        ];
    }
}
