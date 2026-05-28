<?php

namespace App\Services\Specialists;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillCandidate;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Project\ProjectService;
use App\Support\StringCoercion;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SpecialistAgentDraftingService
{
    public function __construct(
        protected ProjectService $projects,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function maybeDraftFromRun(Run $run, array $context = []): ?SpecialistAgent
    {
        if ($run->status !== 'completed') {
            return null;
        }

        $project = $this->resolveProject($run);
        if ($project === null) {
            return null;
        }

        $skillName = $this->skillName($run, $context);
        if ($skillName === '') {
            return null;
        }

        $recentRuns = $this->recentProjectRunsForSkill($project, $skillName);
        $clearPattern = $this->hasClearPattern($context);
        if (count($recentRuns) < 2 && ! $clearPattern) {
            return null;
        }

        $roleSlug = $this->roleSlug($skillName);
        $exists = SpecialistAgent::query()
            ->where('project_id', $project->id)
            ->where('role_slug', $roleSlug)
            ->whereIn('approval_status', ['draft', 'pending_review', 'approved'])
            ->exists();

        if ($exists) {
            return null;
        }

        $context['source_run_ids'] = array_values(array_unique(array_merge(
            Arr::wrap($context['source_run_ids'] ?? []),
            array_map(fn (Run $sourceRun) => $sourceRun->id, $recentRuns),
            [$run->id],
        )));

        return $this->draftFromRun($run, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function draftFromRun(Run $run, array $context = [], bool $force = false): SpecialistAgent
    {
        $project = $this->resolveProject($run) ?? $this->projects->activeProject();
        if ($project === null) {
            throw new \RuntimeException('Cannot draft a specialist agent without an active or run-scoped project.');
        }

        $skillName = $this->skillName($run, $context);
        $roleSlug = $this->roleSlug($skillName !== '' ? $skillName : $run->prompt);
        $displayName = Str::headline(str_replace('-', ' ', $roleSlug));
        $keywords = $this->keywords($run, $context, $skillName);
        $linkedSkill = $skillName !== '' ? Skill::query()->where('name', $skillName)->first() : null;
        $sourceRunIds = array_values(array_unique(array_merge(
            Arr::wrap($context['source_run_ids'] ?? []),
            [$run->id],
        )));

        $existing = SpecialistAgent::query()
            ->where('project_id', $project->id)
            ->where('role_slug', $roleSlug)
            ->first();

        if ($existing !== null) {
            $mergedSourceIds = array_values(array_unique(array_merge(
                Arr::wrap($existing->metadata['source_run_ids'] ?? []),
                $sourceRunIds,
            )));
            $existing->update([
                'description' => $existing->description ?: 'Draft specialist agent for project "'.$project->name.'".',
                'trigger_keywords' => array_values(array_unique(array_merge($existing->trigger_keywords ?? [], $keywords))),
                'metadata' => array_merge($existing->metadata ?? [], [
                    'source' => 'specialist_agent_drafting',
                    'source_run_ids' => $mergedSourceIds,
                    'draft_context' => [
                        'skill_name' => $skillName ?: null,
                        'plan_summary' => StringCoercion::toString(Arr::get($context, 'planner_output.summary')),
                        'patch_summary' => StringCoercion::toString(Arr::get($context, 'executor_result.patch_summary')),
                    ],
                ]),
            ]);

            return $existing->refresh();
        }

        $candidate = SkillCandidate::query()->create([
            'name' => $displayName,
            'description' => 'Draft specialist agent for project "'.$project->name.'" based on completed run patterns.',
            'category' => 'specialist-agent',
            'draft_content' => $this->buildPersona($displayName, $run, $context, $keywords),
            'approval_status' => 'draft',
            'quality_score' => null,
            'source_run_count' => count($sourceRunIds),
            'source_run_ids' => $sourceRunIds,
            'tags' => array_values(array_slice($keywords, 0, 8)),
            'metadata' => [
                'source' => 'specialist_agent_drafting',
                'project_id' => $project->id,
                'role_slug' => $roleSlug,
                'linked_skill_name' => $skillName ?: null,
                'force' => $force,
            ],
        ]);

        $metadata = [
            'source' => 'specialist_agent_drafting',
            'source_run_ids' => $sourceRunIds,
            'skill_candidate_id' => $candidate->id,
            'draft_context' => [
                'skill_name' => $skillName ?: null,
                'plan_summary' => StringCoercion::toString(Arr::get($context, 'planner_output.summary')),
                'patch_summary' => StringCoercion::toString(Arr::get($context, 'executor_result.patch_summary')),
            ],
        ];

        return SpecialistAgent::query()->create([
            'project_id' => $project->id,
            'role_slug' => $roleSlug,
            'display_name' => $displayName,
            'description' => 'Draft specialist agent for project "'.$project->name.'" covering '.implode(', ', array_slice($keywords, 0, 5)).'.',
            'trigger_keywords' => $keywords,
            'persona_content' => $candidate->draft_content,
            'linked_skill_id' => $linkedSkill?->id,
            'approval_status' => 'draft',
            'pixel_palette' => count($keywords) % 6,
            'pixel_hue_shift' => (strlen($roleSlug) * 7) % 60,
            'seat_id' => null,
            'metadata' => $metadata,
        ]);
    }

    protected function resolveProject(Run $run): ?Project
    {
        $metadata = is_array($run->metadata) ? $run->metadata : [];
        $projectId = StringCoercion::toString($metadata['active_project_id'] ?? null);
        if ($projectId !== '') {
            return Project::query()->find($projectId);
        }

        return $this->projects->activeProject();
    }

    /** @param array<string, mixed> $context */
    protected function skillName(Run $run, array $context): string
    {
        return trim(StringCoercion::toString(
            $context['skill_name'] ?? $run->selected_skill_name ?? Arr::get($context, 'router_context.primary_skill.name'),
        ));
    }

    protected function roleSlug(string $source): string
    {
        $slug = Str::slug($source);
        if ($slug === '') {
            $slug = 'project';
        }

        return Str::limit($slug.'-specialist', 80, '');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function keywords(Run $run, array $context, string $skillName): array
    {
        $parts = [
            $skillName,
            $run->prompt,
            StringCoercion::toString(Arr::get($context, 'planner_output.summary')),
            StringCoercion::toString(Arr::get($context, 'executor_result.patch_summary')),
        ];

        foreach (Arr::wrap(Arr::get($context, 'planner_output.target_file_list', [])) as $target) {
            if (is_array($target)) {
                $parts[] = StringCoercion::toString($target['path'] ?? null);
            } else {
                $parts[] = StringCoercion::toString($target);
            }
        }
        foreach (Arr::wrap($context['memory_signals'] ?? []) as $memory) {
            $parts[] = is_array($memory)
                ? StringCoercion::toString($memory['summary'] ?? $memory['content'] ?? null)
                : StringCoercion::toString($memory);
        }

        preg_match_all('/[a-z0-9][a-z0-9_-]{2,}/i', Str::lower(implode(' ', $parts)), $matches);
        $stop = ['the', 'and', 'for', 'with', 'from', 'this', 'that', 'into', 'flow', 'file', 'files', 'app', 'php', 'src', 'run'];
        $keywords = [];
        foreach ($matches[0] ?? [] as $token) {
            $token = trim($token, '-_');
            if ($token === '' || in_array($token, $stop, true)) {
                continue;
            }
            $keywords[] = $token;
        }

        return array_values(array_slice(array_unique($keywords), 0, 16));
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $keywords
     */
    protected function buildPersona(string $displayName, Run $run, array $context, array $keywords): string
    {
        $planSummary = StringCoercion::toString(Arr::get($context, 'planner_output.summary'), 'No planner summary captured.');
        $patchSummary = StringCoercion::toString(Arr::get($context, 'executor_result.patch_summary'), 'No executor patch summary captured.');

        return implode("\n", [
            '# '.$displayName,
            '',
            '## Purpose',
            'Project-scoped temporary specialist drafted from completed BosskuAI runs. Use it only when the prompt clearly matches this project and these triggers: '.implode(', ', $keywords).'.',
            '',
            '## Persona',
            'You are a focused specialist for this project. Before executor starts, convert the planner output into practical implementation guidance, call out risky assumptions, and point to files or areas worth checking first.',
            '',
            '## Source Pattern',
            '- Original prompt: '.$run->prompt,
            '- Planner summary: '.$planSummary,
            '- Executor summary: '.$patchSummary,
            '',
            '## Output Contract',
            'Return summary, task_strategy, pitfalls, files_or_areas_to_focus, and handoff_to_executor.',
        ]);
    }

    /** @param array<string, mixed> $context */
    protected function hasClearPattern(array $context): bool
    {
        return Arr::get($context, 'planner_output.target_file_list', []) !== []
            || Arr::get($context, 'executor_result.files_changed', []) !== []
            || StringCoercion::toString(Arr::get($context, 'executor_result.patch_summary')) !== '';
    }

    /**
     * @return list<Run>
     */
    protected function recentProjectRunsForSkill(Project $project, string $skillName): array
    {
        return Run::query()
            ->where('status', 'completed')
            ->where('selected_skill_name', $skillName)
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->filter(function (Run $run) use ($project): bool {
                $metadata = is_array($run->metadata) ? $run->metadata : [];

                return ($metadata['active_project_id'] ?? null) === $project->id;
            })
            ->values()
            ->all();
    }
}
