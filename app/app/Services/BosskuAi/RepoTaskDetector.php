<?php

namespace App\Services\BosskuAi;

/**
 * Detects prompts that must read the active project repository (executor + file tools).
 */
class RepoTaskDetector
{
    public static function requiresRepositoryAccess(string $prompt): bool
    {
        $lower = mb_strtolower(trim($prompt));

        if (preg_match('/\b(audit|review|scan|analyse|analyze|inspect)\b.*\b(repo|repository|codebase|project|code)\b/u', $lower)) {
            return true;
        }

        if (preg_match('/\b(repo|repository|codebase|project)\b.*\b(audit|review|scan|analyse|analyze|inspect)\b/u', $lower)) {
            return true;
        }

        if (preg_match('/\baudit\b/u', $lower) && preg_match('/\b(full|entire|whole|features?|feature\s+gap)\b/u', $lower)) {
            return true;
        }

        foreach ([
            'audit the',
            'audit full',
            'review the repo',
            'review this repo',
            'read the repo',
            'read files in',
            'scan the',
            'in the codebase',
            'in this codebase',
            'my project files',
            'what features',
            'feature gap',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        if (preg_match('/[a-z]:\\\\|\/users\/|\/home\/|\\\\users\\\\/i', $prompt)) {
            return true;
        }

        return false;
    }

    /**
     * Full repo audit: functionality, design, performance, tests, then dedicated security pass.
     * Default for repository audit prompts unless the user asks for security-only review.
     */
    public static function isFullRepositoryAudit(string $prompt): bool
    {
        if (! self::requiresRepositoryAccess($prompt)) {
            return false;
        }

        return ! self::isSecurityOnlyAudit($prompt);
    }

    /**
     * Narrow security review without full product/code-quality dimensions.
     */
    public static function isSecurityOnlyAudit(string $prompt): bool
    {
        $lower = mb_strtolower(trim($prompt));
        $securityFocused = preg_match(
            '/\b(security|owasp|penetration|pentest|vulnerability|vulnerabilities|xss|csrf|injection)\b/u',
            $lower,
        ) === 1;

        if (! $securityFocused) {
            return false;
        }

        $fullMarkers = [
            'audit full', 'full audit', 'entire repo', 'whole codebase', 'all features',
            'functionality', 'performance', 'best practice', 'design', 'production ready',
            'public use', 'comprehensive', 'complete audit', 'audit all', 'full review',
            'code quality', 'maintainability',
        ];
        foreach ($fullMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    public static function enforceExecutorForRepo(array $route, string $prompt): array
    {
        if (! self::requiresRepositoryAccess($prompt) && ! ($route['needs_repo_context'] ?? false)) {
            return $route;
        }

        $route['needs_repo_context'] = true;
        $route['needs_executor'] = true;

        if (self::requiresRepositoryAccess($prompt)) {
            $route['needs_auditor'] = true;
            $workflow = (string) ($route['workflow'] ?? 'orchestrator_executor_auditor');
            if (in_array($workflow, ['direct_answer', 'writer_only', 'orchestrator_only'], true)) {
                $route['workflow'] = 'orchestrator_executor_auditor';
            }
            if ($workflow === 'orchestrator_executor') {
                $route['workflow'] = 'orchestrator_executor_auditor';
            }

            $route['audit_mode'] = self::isFullRepositoryAudit($prompt) ? 'full' : 'repo';
            if ($route['audit_mode'] === 'full') {
                $route['needs_security_auditor'] = true;
                $wf = (string) ($route['workflow'] ?? 'orchestrator_executor_auditor');
                if ($wf === 'orchestrator_executor_auditor') {
                    $route['workflow'] = 'orchestrator_executor_auditor_security';
                }
            }
        }

        return $route;
    }
}
