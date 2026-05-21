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
        }

        return $route;
    }
}
