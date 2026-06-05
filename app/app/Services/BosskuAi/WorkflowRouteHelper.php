<?php

namespace App\Services\BosskuAi;

/**
 * Single source of truth for which pipeline stages a workflow string includes.
 */
class WorkflowRouteHelper
{
    public static function workflowIncludesAuditor(string $workflow): bool
    {
        return (bool) preg_match('/_auditor(?:_|$)/', $workflow);
    }

    public static function workflowIncludesSecurityAuditor(string $workflow): bool
    {
        return str_contains($workflow, 'security');
    }

    public static function workflowIncludesFinalReviewer(string $workflow): bool
    {
        return str_contains($workflow, 'final_reviewer');
    }

    /**
     * @return list<string>
     */
    public static function pipelineAgentsForWorkflow(string $workflow): array
    {
        if ($workflow === 'direct_answer') {
            return ['direct_answer'];
        }
        if ($workflow === 'writer_only') {
            return ['writer'];
        }

        $agents = ['orchestrator'];
        if ($workflow !== 'orchestrator_only') {
            $agents[] = 'executor';
        }
        if (self::workflowIncludesAuditor($workflow)) {
            $agents[] = 'auditor';
        }
        if (self::workflowIncludesSecurityAuditor($workflow)) {
            $agents[] = 'security-auditor';
        }
        if (self::workflowIncludesFinalReviewer($workflow)) {
            $agents[] = 'final-reviewer';
        }

        return $agents;
    }

    /**
     * @param  array<string, mixed>  $route
     * @return list<string>
     */
    public static function skippedAgentsForRoute(array $route): array
    {
        $workflow = (string) ($route['workflow'] ?? 'orchestrator_executor');
        $planned = self::pipelineAgentsForWorkflow($workflow);
        $skipped = [];

        if (! in_array('executor', $planned, true) && ($route['needs_executor'] ?? false) === false) {
            $skipped[] = 'executor';
        }
        if (! ($route['needs_auditor'] ?? false) || ! self::workflowIncludesAuditor($workflow)) {
            $skipped[] = 'auditor';
        }
        if (! ($route['needs_security_auditor'] ?? false) || ! self::workflowIncludesSecurityAuditor($workflow)) {
            $skipped[] = 'security-auditor';
        }
        if (! ($route['needs_final_reviewer'] ?? false) || ! self::workflowIncludesFinalReviewer($workflow)) {
            $skipped[] = 'final-reviewer';
        }

        return array_values(array_unique($skipped));
    }
}
