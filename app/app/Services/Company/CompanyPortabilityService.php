<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use Illuminate\Support\Str;

/**
 * Export and import an entire company/org as a portable bundle — Paperclip's
 * "one deployment, many companies" portability.
 *
 * Exports the project plus its goal tree and specialist agents (org chart),
 * with secrets scrubbed and internal IDs replaced by stable references
 * (goal index, agent role_slug) so the bundle re-imports cleanly into another
 * deployment. Import recreates everything with fresh IDs, remapping goal parents
 * and agent reporting lines, with role-slug collision handling.
 */
class CompanyPortabilityService
{
    public const VERSION = 1;

    /** Metadata keys whose values are redacted on export. */
    private const SECRET_KEY_PATTERN = '/(key|secret|token|password|passwd|credential|api[-_]?key|bearer)/i';

    public const REDACTED = '[REDACTED]';

    /**
     * @return array<string, mixed>
     */
    public function export(Project $project): array
    {
        $goals = Goal::query()->where('project_id', $project->id)->orderBy('created_at')->get();
        $goalRef = [];
        foreach ($goals as $i => $goal) {
            $goalRef[$goal->id] = $i;
        }

        $agents = SpecialistAgent::query()->where('project_id', $project->id)->orderBy('staff_sort_order')->get();
        $agentSlug = [];
        foreach ($agents as $agent) {
            $agentSlug[$agent->id] = $agent->role_slug;
        }

        return [
            'bundle_version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'project' => ['name' => $project->name],
            'goals' => $goals->map(fn (Goal $g): array => [
                'ref' => $goalRef[$g->id],
                'parent_ref' => $g->parent_goal_id !== null ? ($goalRef[$g->parent_goal_id] ?? null) : null,
                'title' => $g->title,
                'description' => $g->description,
                'status' => $g->status,
                'priority' => $g->priority,
                'target_metric' => $g->target_metric,
                'target_value' => $g->target_value,
                'current_value' => $g->current_value,
                'progress' => $g->progress,
                'metadata' => $this->scrub(is_array($g->metadata) ? $g->metadata : []),
            ])->all(),
            'agents' => $agents->map(fn (SpecialistAgent $a): array => [
                'role_slug' => $a->role_slug,
                'display_name' => $a->display_name,
                'description' => $a->description,
                'department' => $a->department,
                'trigger_keywords' => $a->trigger_keywords ?? [],
                'persona_content' => $a->persona_content,
                'approval_status' => $a->approval_status,
                'runtime_mode' => $a->runtime_mode,
                'reports_to_role_slug' => $a->reports_to_agent_id !== null ? ($agentSlug[$a->reports_to_agent_id] ?? null) : null,
                'metadata' => $this->scrub(is_array($a->metadata) ? $a->metadata : []),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    public function import(array $bundle, ?string $newName = null): Project
    {
        $name = $this->uniqueProjectName($newName ?: (string) (($bundle['project']['name'] ?? null) ?: 'Imported company'));
        // Imported company is not mounted yet; paths are set when the operator
        // attaches a workspace.
        $project = Project::query()->create([
            'name' => $name,
            'host_path' => '',
            'container_path' => '',
            'is_active' => false,
        ]);

        $this->importGoals($project, is_array($bundle['goals'] ?? null) ? $bundle['goals'] : []);
        $this->importAgents($project, is_array($bundle['agents'] ?? null) ? $bundle['agents'] : []);

        return $project->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $goals
     */
    private function importGoals(Project $project, array $goals): void
    {
        $refToId = [];
        // Pass 1: create goals without parents.
        foreach ($goals as $g) {
            if (! is_array($g)) {
                continue;
            }
            $created = Goal::query()->create([
                'project_id' => $project->id,
                'title' => (string) ($g['title'] ?? 'Untitled goal'),
                'description' => $g['description'] ?? null,
                'status' => (string) ($g['status'] ?? 'active'),
                'priority' => (string) ($g['priority'] ?? 'medium'),
                'target_metric' => $g['target_metric'] ?? null,
                'target_value' => $g['target_value'] ?? null,
                'current_value' => $g['current_value'] ?? null,
                'progress' => max(0, min(100, (int) ($g['progress'] ?? 0))),
                'metadata' => is_array($g['metadata'] ?? null) ? $g['metadata'] : null,
            ]);
            if (isset($g['ref'])) {
                $refToId[(string) $g['ref']] = $created->id;
            }
        }
        // Pass 2: wire parents.
        foreach ($goals as $g) {
            if (! is_array($g) || ! isset($g['ref'], $g['parent_ref']) || $g['parent_ref'] === null) {
                continue;
            }
            $childId = $refToId[(string) $g['ref']] ?? null;
            $parentId = $refToId[(string) $g['parent_ref']] ?? null;
            if ($childId !== null && $parentId !== null) {
                Goal::query()->whereKey($childId)->update(['parent_goal_id' => $parentId]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $agents
     */
    private function importAgents(Project $project, array $agents): void
    {
        $slugToId = [];
        $reports = [];
        foreach ($agents as $a) {
            if (! is_array($a)) {
                continue;
            }
            $slug = $this->uniqueRoleSlug($project, (string) ($a['role_slug'] ?? Str::slug((string) ($a['display_name'] ?? 'agent'))));
            $created = SpecialistAgent::query()->create([
                'project_id' => $project->id,
                'role_slug' => $slug,
                'display_name' => (string) ($a['display_name'] ?? $slug),
                'description' => $a['description'] ?? null,
                'department' => $a['department'] ?? null,
                'trigger_keywords' => is_array($a['trigger_keywords'] ?? null) ? $a['trigger_keywords'] : [],
                'persona_content' => $a['persona_content'] ?? null,
                'approval_status' => (string) ($a['approval_status'] ?? 'draft'),
                'runtime_mode' => $a['runtime_mode'] ?? null,
                'metadata' => is_array($a['metadata'] ?? null) ? $a['metadata'] : null,
            ]);
            $originalSlug = (string) ($a['role_slug'] ?? $slug);
            $slugToId[$originalSlug] = $created->id;
            if (! empty($a['reports_to_role_slug'])) {
                $reports[$created->id] = (string) $a['reports_to_role_slug'];
            }
        }
        foreach ($reports as $agentId => $managerSlug) {
            $managerId = $slugToId[$managerSlug] ?? null;
            if ($managerId !== null && $managerId !== $agentId) {
                SpecialistAgent::query()->whereKey($agentId)->update(['reports_to_agent_id' => $managerId]);
            }
        }
    }

    /**
     * Recursively redact secret-looking values from a metadata array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function scrub(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                $out[$key] = self::REDACTED;

                continue;
            }
            $out[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $out;
    }

    private function uniqueProjectName(string $name): string
    {
        $base = $name;
        $candidate = $name;
        $n = 2;
        while (Project::query()->where('name', $candidate)->exists()) {
            $candidate = $base.' ('.$n.')';
            $n++;
        }

        return $candidate;
    }

    private function uniqueRoleSlug(Project $project, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'agent';
        $candidate = $base;
        $n = 2;
        while (SpecialistAgent::query()->where('project_id', $project->id)->where('role_slug', $candidate)->exists()) {
            $candidate = Str::limit($base, 76, '').'-'.$n;
            $n++;
        }

        return $candidate;
    }
}
