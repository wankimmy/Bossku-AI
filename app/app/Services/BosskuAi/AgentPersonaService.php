<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\AgentPersona;
use App\Services\Project\BosskuToolkitDetector;
use App\Services\Project\BosskuToolkitPersonas;
use App\Support\AgentTools;
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
        'designer',
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
            // A missing persona must degrade to "no persona" (use the builtin
            // prompt), never crash the LLM pipeline — e.g. before migrations run.
            try {
                $this->cache[$role] = AgentPersona::query()->find($role);
            } catch (\Illuminate\Database\QueryException) {
                return null;
            }
        }

        return $this->cache[$role];
    }

    public function appendToSystem(string $role, string $builtinSystem): string
    {
        $role = $this->normalizeRole($role);
        $prefix = '';

        $row = $this->forRole($role);
        if ($row !== null && $row->enabled) {
            $content = trim((string) $row->content);
            if ($content !== '') {
                $name = $row->display_name ?: $role;
                $block = "## Agent persona ({$name})\n{$content}";

                if ($this->toolkitDetector->isBosskuToolkitRepository()) {
                    $overlay = trim(BosskuToolkitPersonas::forRole($role));
                    if ($overlay !== '') {
                        $block .= "\n\n## Bossku toolkit self-improvement\n{$overlay}";
                    }
                }

                $prefix = $block."\n\n";
            }
        }

        if (in_array($role, self::PIPELINE_ROLES, true)) {
            $prefix .= AgentTools::formatToolsBlock($role, $this->readRawAgentsMd($role))."\n\n";
        }

        if ($prefix === '') {
            return $builtinSystem;
        }

        return $prefix."---\n\n{$builtinSystem}";
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
     * Safe propagate: create missing rows, migrate legacy/stale rows, and pull in agents/*.md
     * changes — while leaving rows the user deliberately edited in the UI untouched.
     *
     * Runs automatically on every Docker startup (via AgentPersonaSeeder and the entrypoint),
     * so editing a pipeline agents/*.md file and restarting is enough to update the live persona.
     *
     * @return list<array{role: string, action: string, old_preview: string, new_preview: string}>
     */
    public function ensurePipelinePersonas(): array
    {
        return $this->syncPersonasFromMd(null, force: false, dryRun: false);
    }

    /**
     * Single sync engine for pipeline personas. `ensurePipelinePersonas()` is the safe wrapper.
     *
     * Per role, given the compact runtime-core extracted from agents/*.md:
     *   - no row                          → create, tracking the .md (md_hash = sha256(core)).
     *   - row marked USER_EDITED & !force → skip (the user owns it via the UI; use --force to override).
     *   - content already equals the .md  → backfill md_hash if missing/stale; otherwise unchanged.
     *   - content differs                 → adopt the .md and (re)track it. This covers stubs,
     *                                        legacy null-hash rows, and genuine .md edits.
     *
     * @param  list<string>|null  $roles  Null means all PIPELINE_ROLES.
     * @param  bool  $force   Overwrite even USER_EDITED rows (re-tracks them to the .md).
     * @param  bool  $dryRun  Report what would change without writing.
     * @return list<array{role: string, action: string, old_preview: string, new_preview: string}>
     */
    public function syncPersonasFromMd(?array $roles = null, bool $force = false, bool $dryRun = false): array
    {
        $roles ??= self::PIPELINE_ROLES;
        $names = self::defaultDisplayNames();
        $report = [];
        $wrote = false;

        $row = function (string $role, string $action, string $old = '', string $new = '') use (&$report): void {
            $report[] = ['role' => $role, 'action' => $action, 'old_preview' => $old, 'new_preview' => $new];
        };

        foreach ($roles as $role) {
            $fromMd = $this->defaultContentFromAgentsMd($role);
            if ($fromMd === null) {
                $row($role, 'skip_no_md_file');
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
                    $wrote = true;
                }
                $row($role, 'created', '', mb_substr($fromMd, 0, 80));
                continue;
            }

            // The user edited this persona in the UI — never clobber it on an auto-sync.
            if ($existing->md_hash === self::USER_EDITED_HASH && ! $force) {
                $row($role, 'skipped_user_edited');
                continue;
            }

            $oldContent = trim((string) $existing->content);
            if ($oldContent === trim($fromMd)) {
                if ($existing->md_hash === $newHash) {
                    $row($role, 'unchanged');
                } else {
                    if (! $dryRun) {
                        $existing->update(['md_hash' => $newHash]);
                        $wrote = true;
                    }
                    $row($role, $dryRun ? 'would_backfill_hash' : 'hash_backfilled');
                }
                continue;
            }

            if (! $dryRun) {
                $existing->update(['content' => $fromMd, 'md_hash' => $newHash]);
                $wrote = true;
            }
            $row($role, $dryRun ? 'would_update' : 'updated', mb_substr($oldContent, 0, 80), mb_substr($fromMd, 0, 80));
        }

        if ($wrote) {
            $this->clearCache();
        }

        return $report;
    }

    /** Hash sentinel marking a persona row as user-owned (edited via the UI), exempt from auto-sync. */
    public const USER_EDITED_HASH = 'user-edited';

    /** SHA-256 of the runtime-core currently published for a role, or null when the .md is missing. */
    public function currentMdHash(string $role): ?string
    {
        $fromMd = $this->defaultContentFromAgentsMd($this->normalizeRole($role));

        return $fromMd !== null ? hash('sha256', $fromMd) : null;
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
        $map = self::agentsMdMap();
        $file = $map[$role] ?? null;
        if ($file === null) {
            return null;
        }
        $path = rtrim((string) config('bossku.repo_root'), '/\\').'/agents/'.$file;
        if (! is_file($path)) {
            return null;
        }

        $raw = (string) File::get($path);

        // Token efficiency: the full agent .md is the rich editor/subagent-facing doc.
        // For runtime system-prompt injection we only need the compact operating core,
        // delimited by <!-- runtime-core:start --> ... <!-- runtime-core:end -->.
        // The role's detailed contract + output schema already live in the per-role
        // service system prompt, so injecting the whole doc is largely redundant.
        // Fall back to the full file when no marker block is present.
        if (preg_match('/<!--\s*runtime-core:start\s*-->(.*?)<!--\s*runtime-core:end\s*-->/s', $raw, $m)) {
            $core = trim($m[1]);
            if ($core !== '') {
                return $core;
            }
        }

        return trim($raw) ?: null;
    }

    /** @return array<string, string> */
    public static function defaultDisplayNames(): array
    {
        return [
            'router' => 'Router',
            'orchestrator' => 'Orchestrator',
            'designer' => 'Designer',
            'executor' => 'Executor',
            'auditor' => 'Auditor',
            'security_auditor' => 'Security Auditor',
            'final_reviewer' => 'Final Reviewer',
            'writer' => 'Writer',
            'direct_answer' => 'Direct Answer',
            'clarification' => 'Clarification',
        ];
    }

    /** @return array<string, string> */
    public static function agentsMdMap(): array
    {
        return [
            'router' => 'model-router.md',
            'orchestrator' => 'orchestrator.md',
            'designer' => 'designer.md',
            'executor' => 'executor.md',
            'auditor' => 'auditor.md',
            'security_auditor' => 'security-reviewer.md',
            'final_reviewer' => 'final-reviewer.md',
            'writer' => 'writer.md',
            'direct_answer' => 'direct-answer.md',
            'clarification' => 'clarification.md',
        ];
    }

    public function readRawAgentsMd(string $role): ?string
    {
        $role = $this->normalizeRole($role);
        $file = self::agentsMdMap()[$role] ?? null;
        if ($file === null) {
            return null;
        }
        $path = rtrim((string) config('bossku.repo_root'), '/\\').'/agents/'.$file;

        return is_file($path) ? (string) File::get($path) : null;
    }
}
