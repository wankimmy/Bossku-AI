<?php

namespace App\Services\BosskuAi;

use App\Support\PromptContextHelper;

/**
 * Deterministic, dependency-free task router.
 *
 * Used as a prior for the LLM router (see {@see PromptRouteClassifier}) and as the
 * sole classifier when the LLM router is disabled. Pure string heuristics — no DB,
 * no network — so it is fast and safe to run on every prompt.
 *
 * Flow:
 *   1. Short-circuit the routes that must NEVER reach the edit pipeline
 *      (read-only repo understanding, conversational/advisory prompts).
 *   2. Apply ordered domain rules that progressively shape the route. Order is
 *      significant: later rules can depend on state set by earlier ones.
 *   3. Apply the deterministic risk policy (high/medium/low) as a final pass.
 *
 * Behaviour is locked by tests/Unit/BosskuRoutingClassifierTest.php — keep it green.
 */
class DeterministicTaskClassifier
{
    /** Verbs that signal the user wants code/files changed (not just discussed). */
    private const RE_CODE_VERB = '/\b(create|add|write|implement|fix|update|refactor|modify|patch|build|generate|delete|remove|rename|install|configure|deploy|migrate|debug|change|edit|setup|set up|integrate|wire up|scaffold|optimize|optimise|upgrade|convert|replace)\b/i';

    /** "Simple implementation" verbs that map to a plain executor run (no audit). */
    private const RE_SIMPLE_IMPL_VERB = '/\b(create|add|write|implement|fix|update|refactor|modify|patch)\b/i';

    /** Audit/review verbs that must trigger an auditor rather than a blind edit. */
    private const RE_AUDIT_VERB = '/\b(audit|review|scan|inspect|analyse|analyze)\b/i';

    /** Deliberative lead-ins: the user is asking for an opinion, not commanding. */
    private const RE_DELIBERATIVE = '/\b(should i|should we|do you think|what do you think|what.?s your (take|opinion|view)|would you recommend|is it (a )?good idea|good idea to|worth (it|adding|doing|building|trying)|pros and cons|wondering (if|whether)|not sure (if|whether)|which (one|option|approach)|better to|your (opinion|advice|thoughts|take|view) on|any (idea|ideas|thoughts|advice|suggestions)|help me (think|brainstorm|decide)|brainstorm|let.?s (discuss|talk|chat|think)|talk through|thoughts on|feedback on|advise|recommendation)\b/i';

    /** Conversational openers that introduce a discussion, not a task. */
    private const RE_CONVERSATIONAL_OPENER = '/^(hmm+|so[,\s]|well[,\s]|btw|by the way|i (think|feel|wonder|guess|reckon)|what if|how about|what about|maybe we|curious)/i';

    /** Smoke/connectivity checks and short social acknowledgements (no repo work). */
    private const RE_SMOKE = '/^(test|ping|hello|hi|hey|yo|hiya|sup|good (morning|afternoon|evening)|thanks|thank you|thx|ty|cheers|ok|okay|cool|nice|great|awesome|got it)\s*[!?.]*$/i';

    /** Explanatory question lead-ins ("explain ...", "what is ..."). */
    private const RE_QUESTION_LEAD = '/^(explain|what is|what are|how does|why|define)\b/i';

    /**
     * Lightweight heuristics when the LLM router is disabled or as a prior.
     *
     * @return array<string, mixed>
     */
    public function classify(string $prompt): array
    {
        $lower = mb_strtolower($prompt);

        // Is the user actually asking to build/change code or files? Conversational
        // prompts (advice, opinions, brainstorming) must NOT default to file edits.
        $hasCodeVerb = (bool) preg_match(self::RE_CODE_VERB, $lower);
        if ($hasCodeVerb && preg_match('/\b(write|draft|create|compose)\b.+\b(seo|blog|article|post|copy|newsletter|landing page|meta description|social media|sales pitch|outreach email|marketing)\b/i', $lower)) {
            $hasCodeVerb = false;
        }

        // (1) Short-circuit routes — these must never fall through to an edit pipeline.
        if (($route = $this->metaAssistantRoute($prompt)) !== null) {
            return $route;
        }
        if (($route = $this->readOnlyUnderstandingRoute($prompt)) !== null) {
            return $route;
        }
        if (($route = $this->conversationalRoute($prompt, $hasCodeVerb)) !== null) {
            return $route;
        }

        // (2) Ordered domain shaping. State starts at the executor default and is
        // progressively narrowed; later branches can read state set by earlier ones,
        // so the order below is significant and mirrors the routing contract.
        $taskType = 'unknown';
        $skill = 'generic';
        $workflow = 'orchestrator_executor';
        $needsRepo = false;
        $needsFileEdit = true;
        $needsTest = false;
        $needsExecutor = true;
        $needsAuditor = false;
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

        // Smoke / connectivity checks + social acknowledgements (no repo work)
        if (preg_match(self::RE_SMOKE, trim($prompt))) {
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
            && ! preg_match(self::RE_QUESTION_LEAD, trim($prompt))
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

        // Questions — orchestrator surveys via index; no executor unless user asks to change code
        if (preg_match(self::RE_QUESTION_LEAD, trim($prompt))
            || (str_contains($lower, 'policy') && str_contains($lower, 'gate') && str_contains($lower, 'vs'))) {
            $taskType = 'question';
            $skill = 'laravel';
            if (! str_contains($lower, 'laravel') && str_contains($lower, 'react')) {
                $skill = 'react';
            }
            $needsRepo = str_contains($lower, 'repo') || str_contains($lower, 'my project') || str_contains($lower, 'this codebase');
            if ($needsRepo && ! preg_match('/\b(change|fix|update|implement|add|create|modify)\b/i', $lower)) {
                $workflow = 'orchestrator_only';
                $memoryMode = 'read_only';
            }
            else {
                $workflow = 'direct_answer';
                $memoryMode = $needsRepo ? 'read_only' : 'none';
            }
            $needsFileEdit = false;
            $needsExecutor = false;
            $needsAuditor = false;
            $needsTest = false;
            $executorProfile = 'none';
            $tokenLevel = 'low';
        }

        // Simple implementation (create/fix/update) — executor only, no mandatory audit
        if (
            ! RepoTaskDetector::requiresRepositoryAccess($prompt)
            && preg_match(self::RE_SIMPLE_IMPL_VERB, $lower)
            && ! preg_match(self::RE_AUDIT_VERB, $lower)
            && ! in_array($workflow, ['direct_answer', 'writer_only', 'orchestrator_only'], true)
        ) {
            $taskType = str_contains($lower, 'fix') || str_contains($lower, 'bug') ? 'bug_fix' : 'code_edit';
            $workflow = 'orchestrator_executor';
            $needsRepo = true;
            $needsFileEdit = true;
            $needsExecutor = true;
            $needsAuditor = false;
            $needsSecurity = false;
            $needsFinal = false;
            $needsTest = str_contains($lower, 'test') || str_contains($lower, 'phpunit') || str_contains($lower, 'pest');
            $memoryMode = 'read_and_write';
        }

        // Marketing / social / growth content
        if (preg_match('/\b(social media|instagram|twitter|linkedin post|vendor signup|marketing campaign|brand voice|positioning|launch plan|growth strategy)\b/', $prompt)) {
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

        // SEO / discoverability content (non-coding)
        if (
            ! $hasCodeVerb
            && preg_match('/\b(seo|search engine optimization|keyword research|meta description|organic traffic|search ranking|serp)\b/i', $lower)
            && ! in_array($workflow, ['orchestrator_executor_auditor_security_final_reviewer'], true)
        ) {
            $taskType = 'seo';
            $skill = 'seo';
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

        // Sales / commercial messaging (non-coding)
        if (
            ! $hasCodeVerb
            && preg_match('/\b(sales pitch|cold outreach|cold email|sales proposal|objection handling|conversion copy|pipeline messaging|outbound email)\b/i', $lower)
        ) {
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

        // UI/UX advisory (design critique without implementation)
        if (
            ! $hasCodeVerb
            && preg_match('/\b(ui\/ux|user experience|usability review|wireframe critique|design critique|visual hierarchy|information architecture)\b/i', $lower)
            && ! preg_match('/\b(implement|fix|update|create|build|code|css|tailwind)\b/i', $lower)
        ) {
            $taskType = 'ui_ux';
            $skill = 'uiux';
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

        // Auth — note: precedence is "authentication" OR ("middleware" AND "auth")
        if (str_contains($lower, 'authentication') || (str_contains($lower, 'middleware') && str_contains($lower, 'auth'))) {
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

        // UI — note: precedence is "button" OR "spacing" OR ("dashboard" AND "mobile")
        if (str_contains($lower, 'button') || str_contains($lower, 'spacing') || (str_contains($lower, 'dashboard') && str_contains($lower, 'mobile'))) {
            $taskType = 'ui_ux';
            $skill = 'uiux';
            $executorProfile = 'frontend_ui';
            if (! RepoTaskDetector::requiresRepositoryAccess($prompt)) {
                $workflow = 'orchestrator_executor';
                $needsAuditor = false;
                $needsExecutor = true;
                $needsFileEdit = true;
            }
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
            $workflow = 'orchestrator_executor';
            $needsAuditor = false;
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

        // Catch-all: an unclassified prompt with no code-action verb and no repo
        // task must not fall through to the orchestrator_executor default and edit
        // code. Treat it as a conversation instead.
        if (
            $taskType === 'unknown'
            && $workflow === 'orchestrator_executor'
            && $needsFileEdit
            && ! $hasCodeVerb
            && ! RepoTaskDetector::requiresRepositoryAccess($prompt)
        ) {
            $taskType = 'question';
            $workflow = 'direct_answer';
            $needsFileEdit = false;
            $needsExecutor = false;
            $needsAuditor = false;
            $needsSecurity = false;
            $needsFinal = false;
            $executorProfile = 'none';
            $memoryMode = 'read_only';
            $tokenLevel = 'low';
        }

        // (3) Final deterministic risk policy.
        return $this->applyRiskPolicy($prompt, [
            'task_type' => $taskType,
            'audit_mode' => $auditMode,
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
        ]);
    }

    /**
     * Meta questions about BosskuAI itself — answer directly, never repo preflight.
     *
     * @return array<string, mixed>|null
     */
    private function metaAssistantRoute(string $prompt): ?array
    {
        if (! PromptContextHelper::isMetaAboutAssistant($prompt)) {
            return null;
        }

        $det = (new RiskRuleEngine)->deterministicRisk($prompt);

        return [
            'task_type' => 'question',
            'audit_mode' => 'standard',
            'risk_level' => 'low',
            'skill' => 'generic',
            'workflow' => 'direct_answer',
            'needs_repo_context' => false,
            'needs_file_edit' => false,
            'needs_test_run' => false,
            'needs_executor' => false,
            'needs_auditor' => false,
            'needs_security_auditor' => false,
            'needs_final_reviewer' => false,
            'executor_profile' => 'none',
            'memory_mode' => 'read_only',
            'estimated_token_level' => 'low',
            'reason' => 'Meta question about BosskuAI capabilities — direct conversational answer.',
            '_deterministic_risk' => $det,
        ];
    }

    /**
     * Read-only repository understanding ("summarize / explain this project") — the
     * orchestrator answers from repo context; no executor edits, no audit pipeline.
     * Must precede the repository-audit branch, which would otherwise force
     * executor + auditor for any prompt that merely mentions the repo.
     *
     * @return array<string, mixed>|null
     */
    private function readOnlyUnderstandingRoute(string $prompt): ?array
    {
        if (! RepoTaskDetector::isReadOnlyUnderstanding($prompt)) {
            return null;
        }

        $det = (new RiskRuleEngine)->deterministicRisk($prompt);

        return [
            'task_type' => 'question',
            'audit_mode' => 'standard',
            'risk_level' => $det['risk'],
            'skill' => 'bosskuai-project-understanding',
            'workflow' => 'orchestrator_only',
            'needs_repo_context' => true,
            'needs_file_edit' => false,
            'needs_test_run' => false,
            'needs_executor' => false,
            'needs_auditor' => false,
            'needs_security_auditor' => false,
            'needs_final_reviewer' => false,
            'executor_profile' => 'none',
            'memory_mode' => 'read_only',
            'estimated_token_level' => 'low',
            'reason' => 'Read-only repository understanding — orchestrator answers from repo context without code changes or an audit pipeline.',
            '_deterministic_risk' => $det,
        ];
    }

    /**
     * Conversational / advisory prompts — talk it through, never touch code. A
     * deliberative lead ("should I…", "what do you think") wins even when a code
     * verb is present, because the user is asking, not commanding.
     *
     * @return array<string, mixed>|null
     */
    private function conversationalRoute(string $prompt, bool $hasCodeVerb): ?array
    {
        $isRepoTask = RepoTaskDetector::requiresRepositoryAccess($prompt);
        $deliberative = (bool) preg_match(self::RE_DELIBERATIVE, mb_strtolower($prompt));
        $conversationalOpener = (bool) preg_match(self::RE_CONVERSATIONAL_OPENER, trim($prompt));

        if ($isRepoTask || (! $deliberative && ! ($conversationalOpener && ! $hasCodeVerb))) {
            return null;
        }

        $det = (new RiskRuleEngine)->deterministicRisk($prompt);

        return [
            'task_type' => 'question',
            'audit_mode' => 'standard',
            'risk_level' => $det['risk'],
            'skill' => 'generic',
            'workflow' => 'direct_answer',
            'needs_repo_context' => false,
            'needs_file_edit' => false,
            'needs_test_run' => false,
            'needs_executor' => false,
            'needs_auditor' => false,
            'needs_security_auditor' => false,
            'needs_final_reviewer' => false,
            'executor_profile' => 'none',
            'memory_mode' => 'read_only',
            'estimated_token_level' => 'low',
            'reason' => 'Conversational/advisory prompt — answered directly without code changes.',
            '_deterministic_risk' => $det,
        ];
    }

    /**
     * Apply the deterministic risk gate as the final pass: high risk forces the
     * full security + final-reviewer chain, medium trims an unneeded auditor, and
     * low collapses an auditor-only workflow back to a plain executor run.
     *
     * @param  array<string, mixed>  $route  the route assembled by the domain rules (no risk fields yet)
     * @return array<string, mixed>
     */
    private function applyRiskPolicy(string $prompt, array $route): array
    {
        $det = (new RiskRuleEngine)->deterministicRisk($prompt);
        $risk = $det['risk'];

        $workflow = (string) $route['workflow'];
        $executorProfile = (string) $route['executor_profile'];
        $needsAuditor = (bool) $route['needs_auditor'];
        $needsSecurity = (bool) $route['needs_security_auditor'];
        $needsFinal = (bool) $route['needs_final_reviewer'];

        if (
            in_array($workflow, ['direct_answer', 'writer_only', 'orchestrator_only'], true)
            && ! ($route['needs_executor'] ?? false)
        ) {
            $route['risk_level'] = $risk === 'high' && PromptContextHelper::isMetaAboutAssistant($prompt) ? 'low' : $risk;
            $route['needs_auditor'] = false;
            $route['needs_security_auditor'] = false;
            $route['needs_final_reviewer'] = false;

            return $route;
        }

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
            if (! WorkflowRouteHelper::workflowIncludesAuditor($workflow)
                && ! in_array($workflow, ['direct_answer', 'writer_only', 'orchestrator_only', 'orchestrator_executor_auditor_security', 'orchestrator_executor_auditor_security_final_reviewer'], true)) {
                $workflow = 'orchestrator_executor';
                $needsAuditor = false;
            }
        } else {
            $needsFinal = false;
            if ($workflow === 'orchestrator_executor_auditor' && ! $needsAuditor) {
                $workflow = 'orchestrator_executor';
            }
        }

        $route['risk_level'] = $risk;
        $route['workflow'] = $workflow;
        $route['executor_profile'] = $executorProfile;
        $route['needs_auditor'] = $needsAuditor;
        $route['needs_security_auditor'] = $needsSecurity;
        $route['needs_final_reviewer'] = $needsFinal;
        $route['reason'] = 'Heuristic classification';
        $route['_deterministic_risk'] = $det;

        return $route;
    }
}
