<?php

namespace App\Services\BosskuAi;

use App\Support\PromptContextHelper;

/**
 * Detects prompts that must read the active project repository (executor + file tools).
 */
class RepoTaskDetector
{
    public static function requiresRepositoryAccess(string $prompt): bool
    {
        if (PromptContextHelper::isMetaAboutAssistant($prompt)) {
            return false;
        }

        $lower = mb_strtolower(trim(PromptContextHelper::currentRequest($prompt)));

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
     * Read-only "understand / summarize / explain this project" requests.
     *
     * These need repository CONTEXT so the orchestrator can answer, but must NOT be
     * pushed through the executor + auditor pipeline — the user wants a synthesized
     * overview, not a code review. An explicit audit/review/scan or build/mutation
     * verb disqualifies the prompt and lets it fall back to the audit/executor path.
     */
    public static function isReadOnlyUnderstanding(string $prompt): bool
    {
        if (PromptContextHelper::isMetaAboutAssistant($prompt)) {
            return false;
        }

        $lower = mb_strtolower(trim(PromptContextHelper::currentRequest($prompt)));

        // Audit / review / mutation intent wins — those belong in the executor pipeline.
        if (preg_match('/\b(audit|review|scan|analyse|analyze|refactor|implement|deploy|migrate|optimi[sz]e|vulnerabilit|owasp|pentest|penetration)\b/u', $lower)) {
            return false;
        }

        $understands = (bool) preg_match(
            '/\b(understand|understanding|summari[sz]e|summary|explain|describe|overview|orient|onboard|familiari[sz]e|get familiar|walk me through|tell me about|what (is|are|does)|map the (stack|repo|repository|codebase|project)|get up to speed)\b/u',
            $lower,
        );

        if (! $understands) {
            return false;
        }

        return (bool) preg_match('/\b(repo|repository|codebase|code base|project|workspace|this code|the code)\b/u', $lower);
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
        if (PromptContextHelper::isMetaAboutAssistant($prompt)) {
            return $route;
        }

        // Read-only understanding needs repo context, never the executor/auditor pipeline.
        if (self::isReadOnlyUnderstanding($prompt)) {
            $route['needs_repo_context'] = true;

            return $route;
        }

        $requiresAudit = self::requiresRepositoryAccess($prompt);
        $needsRepoContext = (bool) ($route['needs_repo_context'] ?? false);

        if (! $requiresAudit && ! $needsRepoContext) {
            return $route;
        }

        if ($needsRepoContext) {
            $route['needs_repo_context'] = true;
        }

        if (! $requiresAudit) {
            // Repo context for Q&A or planning only — do not force executor or auditor.
            return $route;
        }

        $route['needs_repo_context'] = true;
        $route['needs_executor'] = true;
        $route['needs_auditor'] = true;
        $workflow = (string) ($route['workflow'] ?? 'orchestrator_executor_auditor');
        if (in_array($workflow, ['direct_answer', 'writer_only', 'orchestrator_only'], true)) {
            $route['workflow'] = 'orchestrator_executor_auditor';
        }

        $route['audit_mode'] = self::isFullRepositoryAudit($prompt) ? 'full' : 'repo';
        if ($route['audit_mode'] === 'full') {
            $route['needs_security_auditor'] = true;
            $wf = (string) ($route['workflow'] ?? 'orchestrator_executor_auditor');
            if ($wf === 'orchestrator_executor_auditor' || $wf === 'orchestrator_executor') {
                $route['workflow'] = 'orchestrator_executor_auditor_security';
            }
        }

        return $route;
    }
}
