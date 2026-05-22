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
SELF-IMPROVEMENT MODE: The active repository is the Bossku-AI orchestrator (Laravel API in app/, Nuxt UI in web/, multi-agent pipeline in OrchestratorService).
Your goal is to improve Bossku itself — routing, executor evidence, auditor dimensions, memory/learning, project commands, security, and public readiness — not a random client app.
Prefer small, testable changes; run php artisan test in app/ when you change PHP; cite real file evidence from tools.
TXT;
    }

    public static function forRole(string $role): string
    {
        $shared = self::sharedPreamble();

        return match ($role) {
            'router' => $shared."\nRoute repo audits and Bossku feature work through executor+auditor; use audit_mode=full for holistic reviews.",
            'orchestrator', 'planner' => $shared."\nPlan changes under app/app/Services/Orchestrator, app/routes/api.php, web/composables, and config/bossku.php.",
            'executor' => $shared."\nRead orchestrator, AgentPersonaService, MemoryService, LearningEngine, and RunController before editing; propose commands_run for tests.",
            'auditor' => $shared."\nAudit for pipeline correctness, UX/API contract, performance of sync runs, and maintainability of large services.",
            'security_auditor' => $shared."\nFocus on open API optional auth, docker.sock, workspace mounts, and secrets in .env/settings.",
            'final_reviewer' => $shared."\nGate merge on tests green, docs updated, and no regressions to OSS easy-setup defaults.",
            'direct_answer', 'writer', 'clarification' => $shared,
            default => $shared,
        };
    }
}
