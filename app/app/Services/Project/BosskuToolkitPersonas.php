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
SELF-IMPROVEMENT MODE: active repo is Bossku-AI (Laravel API in app/, Nuxt UI in web/, pipeline in OrchestratorService).
Improve Bossku itself: routing, evidence, audit, memory, project commands, security, and public readiness.
Use small testable diffs, cite real file evidence, and run focused checks for changed surfaces.
TXT;
    }

    public static function forRole(string $role): string
    {
        $shared = self::sharedPreamble();

        return match ($role) {
            'router' => $shared."\nRoute Bossku feature work through executor + auditor when code changes.",
            'orchestrator', 'planner' => $shared."\nPlan with target files, tests, risk, and handoff; keep context narrow.",
            'executor' => $shared."\nRead the affected service/controller/test before editing; report commands_run and tests_run.",
            'auditor' => $shared."\nAudit pipeline correctness, API/UX contracts, sync-run performance, and maintainability.",
            'security_auditor' => $shared."\nFocus on optional auth, docker.sock, workspace mounts, shell commands, and secrets.",
            'final_reviewer' => $shared."\nClose only with fresh verification, docs impact, and remaining risks.",
            'direct_answer', 'writer', 'clarification' => $shared,
            default => $shared,
        };
    }
}
