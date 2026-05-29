<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\AgentPersona;
use App\Services\Project\BosskuToolkitDetector;
use App\Services\Project\BosskuToolkitPersonas;
use Illuminate\Support\Facades\File;

class AgentPersonaService
{
    public function __construct(
        protected BosskuToolkitDetector $toolkitDetector,
    ) {}
    /** @var array<string, AgentPersona|null> */
    protected array $cache = [];

    /** Roles that receive persona injection in ModelFallbackService. */
    public const PIPELINE_ROLES = [
        'router',
        'orchestrator',
        'executor',
        'auditor',
        'security_auditor',
        'final_reviewer',
        'writer',
        'direct_answer',
        'clarification',
    ];

    public function clearCache(): void
    {
        $this->cache = [];
    }

    public function normalizeRole(string $llmRoleSlug): string
    {
        $slug = strtolower(trim($llmRoleSlug));

        return match ($slug) {
            'planner', 'orchestrator' => 'orchestrator',
            'model_router', 'model-router' => 'router',
            'security', 'security-auditor', 'security_reviewer' => 'security_auditor',
            'final-reviewer', 'final_reviewer', 'reviewer' => 'final_reviewer',
            'direct', 'direct_answer' => 'direct_answer',
            default => $slug,
        };
    }

    public function shouldApplyPersona(string $llmRoleSlug): bool
    {
        return in_array($this->normalizeRole($llmRoleSlug), self::PIPELINE_ROLES, true);
    }

    public function forRole(string $role): ?AgentPersona
    {
        $role = $this->normalizeRole($role);
        if (! array_key_exists($role, $this->cache)) {
            $this->cache[$role] = AgentPersona::query()->find($role);
        }

        return $this->cache[$role];
    }

    public function appendToSystem(string $role, string $builtinSystem): string
    {
        $row = $this->forRole($role);
        if ($row === null || ! $row->enabled) {
            return $builtinSystem;
        }
        $content = trim((string) $row->content);
        if ($content === '') {
            return $builtinSystem;
        }
        $name = $row->display_name ?: $role;

        $block = "## Agent persona ({$name})\n{$content}";

        if ($this->toolkitDetector->isBosskuToolkitRepository()) {
            $overlay = trim(BosskuToolkitPersonas::forRole($role));
            if ($overlay !== '') {
                $block .= "\n\n## Bossku toolkit self-improvement\n{$overlay}";
            }
        }

        return $block."\n\n---\n\n{$builtinSystem}";
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    public function applyToMessages(string $role, array $messages): array
    {
        if (! $this->shouldApplyPersona($role)) {
            return $messages;
        }

        $normalized = $this->normalizeRole($role);
        $found = false;
        foreach ($messages as $idx => $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $messages[$idx]['content'] = $this->appendToSystem($normalized, (string) ($msg['content'] ?? ''));
                $found = true;
                break;
            }
        }
        if (! $found) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->appendToSystem($normalized, ''),
            ]);
        }

        return $messages;
    }

    public function wrapHandoffUserContent(
        string $toRole,
        ?string $fromRole,
        ?string $handoffMessage,
        string $payload
    ): string {
        if ($fromRole === null && ($handoffMessage === null || trim($handoffMessage) === '')) {
            return $payload;
        }
        $to = $this->normalizeRole($toRole);
        $from = $fromRole !== null ? $this->normalizeRole($fromRole) : 'previous';
        $lines = ["## Handoff: {$from} → {$to}"];
        if ($handoffMessage !== null && trim($handoffMessage) !== '') {
            $lines[] = 'Message: '.trim($handoffMessage);
        }
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = $payload;

        return implode("\n", $lines);
    }

    /**
     * @return array<string, array{enabled: bool, preview: string}>
     */
    public function snapshotForRun(): array
    {
        $out = [];
        foreach (self::PIPELINE_ROLES as $role) {
            $row = $this->forRole($role);
            if ($row === null) {
                continue;
            }
            $content = trim((string) $row->content);
            $enabled = $row->enabled && $content !== '';
            $out[$role] = [
                'enabled' => $enabled,
                'preview' => $enabled ? mb_substr($content, 0, 80) : '',
            ];
        }

        return $out;
    }

    /**
     * Create missing pipeline persona rows, and upgrade rows when the .md file changed.
     *
     * Upgrade logic (in priority order):
     *   1. Row does not exist → create from .md (or stub fallback).
     *   2. Row content is a known builtin stub → upgrade to .md.
     *   3. Row has an md_hash and the current .md hash differs → the .md changed since last
     *      sync; upgrade automatically (this is the path that picks up agents/*.md edits).
     *   4. Row has real content but no md_hash → was seeded before hash tracking; treat as
     *      "potentially user-edited"; do NOT overwrite automatically. Use syncPersonasFromMd()
     *      with $force=true to overwrite these.
     *   5. Everything else → leave untouched (user-edited content).
     */
    public function ensurePipelinePersonas(): void
    {
        $names = self::defaultDisplayNames();
        $stubs = AgentPersonaBuiltinPrompts::previews();
        $changed = false;

        foreach (self::PIPELINE_ROLES as $role) {
            $existing = AgentPersona::query()->where('role', $role)->first();

            $fromMd = $this->defaultContentFromAgentsMd($role);
            $currentMdHash = $fromMd !== null ? hash('sha256', $fromMd) : null;

            if ($existing === null) {
                $content = $fromMd ?? ($stubs[$role] ?? 'BosskuAI '.$role.' agent.');
                AgentPersona::query()->create([
                    'role'         => $role,
                    'display_name' => $names[$role] ?? ucfirst(str_replace('_', ' ', $role)),
                    'content'      => $content,
                    'md_hash'      => $currentMdHash,
                    'enabled'      => true,
                ]);
                $changed = true;
                continue;
            }

            $currentContent = trim((string) $existing->content);
            $knownStubs = array_map('trim', array_merge(
                array_values($stubs),
                ['BosskuAI '.$role.' agent.'],
            ));

            $isStub = in_array($currentContent, $knownStubs, true);
            $isOldFinalReviewerFormat = $role === 'final_reviewer'
                && str_contains($currentContent, 'Status: Completed / Partially Completed / Blocked');

            // md_hash is set → this row was last written by a sync; auto-upgrade when .md changed.
            $mdHashChanged = $existing->md_hash !== null
                && $currentMdHash !== null
                && $existing->md_hash !== $currentMdHash;

            if ($fromMd !== null && ($isStub || $isOldFinalReviewerFormat || $mdHashChanged)) {
                $existing->update(['content' => $fromMd, 'md_hash' => $currentMdHash]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->clearCache();
        }
    }

    /**
     * Force-sync all (or specific) pipeline persona rows from their agents/*.md files.
     *
     * This is the escape hatch when rows have real content but no md_hash (seeded before
     * hash tracking was added) and you want to push new .md content through without dropping
     * the DB and re-seeding.
     *
     * @param  list<string>|null  $roles  Null means all PIPELINE_ROLES.
     * @param  bool  $dryRun  When true, report what would change without writing.
     * @return list<array{role: string, action: string, old_preview: string, new_preview: string}>
     */
    public function syncPersonasFromMd(?array $roles = null, bool $dryRun = false): array
    {
        $roles ??= self::PIPELINE_ROLES;
        $names = self::defaultDisplayNames();
        $report = [];

        foreach ($roles as $role) {
            $fromMd = $this->defaultContentFromAgentsMd($role);
            if ($fromMd === null) {
                $report[] = [
                    'role'        => $role,
                    'action'      => 'skip_no_md_file',
                    'old_preview' => '',
                    'new_preview' => '',
                ];
                continue;
            }

            $newHash = hash('sha256', $fromMd);
            $existing = AgentPersona::query()->where('role', $role)->first();

            if ($existing === null) {
                if (! $dryRun) {
                    AgentPersona::query()->create([
                        'role'         => $role,
                        'display_name' => $names[$role] ?? ucfirst(str_replace('_', ' ', $role)),
                        'content'      => $fromMd,
                        'md_hash'      => $newHash,
                        'enabled'      => true,
                    ]);
                }
                $report[] = [
                    'role'        => $role,
                    'action'      => 'created',
                    'old_preview' => '',
                    'new_preview' => mb_substr($fromMd, 0, 80),
                ];
                continue;
            }

            $oldContent = trim((string) $existing->content);
            if ($oldContent === trim($fromMd)) {
                $existing->md_hash === $newHash
                    ? $report[] = ['role' => $role, 'action' => 'unchanged', 'old_preview' => '', 'new_preview' => '']
                    : ($dryRun ?: $existing->update(['md_hash' => $newHash]));
                if ($oldContent !== trim($fromMd) || $existing->md_hash !== $newHash) {
                    $report[] = ['role' => $role, 'action' => 'hash_updated', 'old_preview' => mb_substr($oldContent, 0, 80), 'new_preview' => mb_substr($fromMd, 0, 80)];
                } else {
                    $report[] = ['role' => $role, 'action' => 'unchanged', 'old_preview' => '', 'new_preview' => ''];
                }
                continue;
            }

            if (! $dryRun) {
                $existing->update(['content' => $fromMd, 'md_hash' => $newHash]);
            }
            $report[] = [
                'role'        => $role,
                'action'      => $dryRun ? 'would_update' : 'updated',
                'old_preview' => mb_substr($oldContent, 0, 80),
                'new_preview' => mb_substr($fromMd, 0, 80),
            ];
        }

        if (! $dryRun) {
            $this->clearCache();
        }

        return $report;
    }

    /**
     * @return list<array{role: string, display_name: string, content_preview: string, enabled: bool, updated_at: string|null}>
     */
    public function listForApi(): array
    {
        $this->ensurePipelinePersonas();

        return AgentPersona::query()
            ->orderBy('role')
            ->get()
            ->map(fn (AgentPersona $row) => [
                'role' => $row->role,
                'display_name' => $row->display_name,
                'content_preview' => mb_substr(trim((string) $row->content), 0, 120),
                'enabled' => (bool) $row->enabled,
                'updated_at' => $row->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function defaultContentFromAgentsMd(string $role): ?string
    {
        $map = [
            'router' => 'model-router.md',
            'orchestrator' => 'orchestrator.md',
            'executor' => 'executor.md',
            'auditor' => 'auditor.md',
            'security_auditor' => 'security-reviewer.md',
            'final_reviewer' => 'final-reviewer.md',
            'writer' => 'writer.md',
            'direct_answer' => 'direct-answer.md',
            'clarification' => 'clarification.md',
        ];
        $file = $map[$role] ?? null;
        if ($file === null) {
            return null;
        }
        $path = rtrim((string) config('bossku.repo_root'), '/\\').'/agents/'.$file;
        if (! is_file($path)) {
            return null;
        }

        return trim((string) File::get($path)) ?: null;
    }

    /** @return array<string, string> */
    public static function defaultDisplayNames(): array
    {
        return [
            'router' => 'Router',
            'orchestrator' => 'Orchestrator',
            'executor' => 'Executor',
            'auditor' => 'Auditor',
            'security_auditor' => 'Security Auditor',
            'final_reviewer' => 'Final Reviewer',
            'writer' => 'Writer',
            'direct_answer' => 'Direct Answer',
            'clarification' => 'Clarification',
        ];
    }
}
