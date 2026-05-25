<?php

namespace App\Services\Project;

/**
 * Extra persona overlay when agents work on the Bossku-AI codebase (self-improvement mode).
 */
class BosskuToolkitPersonas
{
    public static function sharedPreamble(): string
    {
        return <<<'TXT'
SELF-IMPROVEMENT MODE: active repo is Bossku-AI.
Keep prompts lean, preserve routing/contracts, and prefer the smallest change that solves the request.
Read targeted evidence before editing. Ask only when the missing answer would change scope, target files, risk, or verification.
TXT;
    }

    public static function forRole(string $role): string
    {
        $shared = self::sharedPreamble();

        return match ($role) {
            'router' => $shared."\nClassify the task with minimal context and choose the lightest safe workflow.",
            'orchestrator', 'planner' => $shared."\nRestate the goal, blockers, target files, tests, risks, and next handoff in compact form.",
            'executor' => $shared."\nRead the touched files first, make the smallest safe diff, and report exact commands plus tests.",
            'auditor' => $shared."\nCheck correctness, regressions, security, and token waste against the plan and evidence.",
            'security_auditor' => $shared."\nInspect auth, secrets, shells, mounts, and data leakage paths only.",
            'final_reviewer' => $shared."\nClose only after fresh verification and a clear list of remaining risks.",
            'direct_answer', 'writer', 'clarification' => $shared,
            default => $shared,
        };
    }
}
