<?php

namespace App\Services\Governance;

use App\Services\BosskuAi\RiskRuleEngine;

class RiskClassifier
{
    private const LEVELS = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];

    private const PATTERNS = [
        'critical' => [
            'payment', 'stripe', 'billing', 'credit.?card', 'secret',
            'env\s*=', '\.env', 'private.?key', 'ssh.?key',
        ],
        'high' => [
            'auth', 'password', 'token', 'migration', 'drop.?table',
            'delete.*database', 'rm\s+-rf', 'deploy', 'production',
        ],
        'medium' => [
            'install', 'npm', 'composer', 'external.?http', 'curl', 'webhook',
        ],
    ];

    public function __construct(private readonly RiskRuleEngine $engine) {}

    public function classify(string $prompt, array $planSteps = []): string
    {
        $corpus = mb_strtolower($prompt . ' ' . implode(' ', $planSteps));

        $patternLevel = 'low';
        foreach (self::PATTERNS as $level => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $corpus)) {
                    $patternLevel = $level;
                    break 2;
                }
            }
        }

        $engineResult = $this->engine->deterministicRisk($prompt);
        $engineRaw    = $engineResult['risk'];
        // deterministicRisk only returns low/medium/high; map to our four-level scale
        $engineLevel  = match ($engineRaw) {
            'high'   => 'high',
            'medium' => 'medium',
            default  => 'low',
        };

        return self::LEVELS[$patternLevel] >= self::LEVELS[$engineLevel]
            ? $patternLevel
            : $engineLevel;
    }
}
