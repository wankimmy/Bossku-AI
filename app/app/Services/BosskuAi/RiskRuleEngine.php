<?php

namespace App\Services\BosskuAi;

use App\Support\PromptContextHelper;

class RiskRuleEngine
{
    /** @var list<string> */
    protected array $highKeywords = [
        'payment', 'billing', 'subscription', 'checkout', 'invoice', 'refund', 'settlement', 'transaction',
        'webhook', 'callback', 'signature validation', 'authentication', 'authorization', 'permission', 'role',
        'policy', 'gate', 'password', 'token', 'session', 'secret', 'api key', 'private key', 'database migration',
        'schema migration', 'production', 'deployment', 'docker production', 'nginx production', 'ssl', 'tls',
        'security', 'owasp', 'user data', 'personal data', 'file upload', 'large refactor', 'multi-module',
        'breaking change',
    ];

    /** @var list<string> */
    protected array $mediumKeywords = [
        'bug fix', 'backend logic', 'api', 'queue', 'job', 'event', 'listener', 'notification', 'integration',
        'tests', 'validation', 'database query', 'performance', 'cache', 'redis', 'worker', 'cron', 'scheduler',
        'third-party service', 'service class', 'repository class', 'business logic',
    ];

    /** @var list<string> */
    protected array $lowKeywords = [
        'explain', 'what is', 'difference between', 'readme', 'documentation', 'grammar', 'social post',
        'marketing copy', 'rewrite intro',
    ];

    /**
     * @return array{risk: string, reasons: list<string>, upgraded: bool}
     */
    public function deterministicRisk(string $prompt): array
    {
        $current = PromptContextHelper::currentRequest($prompt);
        $lower = mb_strtolower($current);
        $trim = trim($current);

        // Prefer explanation-style prompts over generic high-risk vocab (policy/gate/auth wording).
        if (preg_match('/^(explain|what is|what are|how does|why|define)\b/i', $trim)) {
            return ['risk' => 'low', 'reasons' => ['heuristic:explanation_question'], 'upgraded' => false];
        }

        if (PromptContextHelper::isMetaAboutAssistant($prompt)) {
            return ['risk' => 'low', 'reasons' => ['heuristic:meta_assistant'], 'upgraded' => false];
        }

        $reasons = [];

        foreach ($this->highKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $reasons[] = 'high:'.$kw;
            }
        }
        if ($reasons !== []) {
            return ['risk' => 'high', 'reasons' => array_values(array_unique($reasons)), 'upgraded' => true];
        }

        foreach ($this->mediumKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $reasons[] = 'medium:'.$kw;
            }
        }
        if ($reasons !== []) {
            return ['risk' => 'medium', 'reasons' => array_values(array_unique($reasons)), 'upgraded' => true];
        }

        $looksLow = false;
        foreach ($this->lowKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $looksLow = true;
                break;
            }
        }

        if ($looksLow || preg_match('/^explain\s+/i', trim($prompt))) {
            return ['risk' => 'low', 'reasons' => ['heuristic:explanation_or_docs'], 'upgraded' => true];
        }

        return ['risk' => 'low', 'reasons' => [], 'upgraded' => false];
    }

    /**
     * @param  'low'|'medium'|'high'  $llm
     * @param  'low'|'medium'|'high'  $det
     * @return array{risk: string, upgraded_note: string|null}
     */
    public function mergeRisk(string $llm, string $det): array
    {
        $order = ['low' => 0, 'medium' => 1, 'high' => 2];
        $lr = $order[$llm] ?? 0;
        $dr = $order[$det] ?? 0;
        if ($dr > $lr) {
            return ['risk' => $det, 'upgraded_note' => 'Risk upgraded by deterministic rule'];
        }

        return ['risk' => $llm, 'upgraded_note' => null];
    }

    /** @param list<string> $tags */
    public function needsSecurityAudit(string $risk, array $tags): bool
    {
        if ($risk === 'high') {
            return true;
        }

        foreach ($tags as $t) {
            if (in_array($t, [
                'authentication', 'authorization', 'payment', 'billing', 'secrets', 'database_migration',
                'database', 'deployment', 'webhook', 'production_deployment', 'security_sensitive', 'security',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $tags */
    public function needsFinalReviewer(string $risk, array $tags): bool
    {
        if ($risk !== 'high') {
            return false;
        }

        return true;
    }
}
