<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Support\StringCoercion;

/**
 * Resolves the business goal a run should align to, and renders a compact
 * context block for the planner.
 *
 * Resolution order: an explicit `goal_id` on the run's metadata always wins;
 * otherwise, when `align_runs_to_active_goal` is enabled, the project's
 * top active goal (highest priority, then most recent) is used so ad-hoc work
 * still rolls up under the live objective.
 */
class GoalContextResolver
{
    /** Priority label → weight for picking the "top" active goal. */
    private const PRIORITY_WEIGHT = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    public function resolveForRun(Run $run, ?Project $project): ?Goal
    {
        $metadata = is_array($run->metadata) ? $run->metadata : [];
        $goalId = StringCoercion::toString($metadata['goal_id'] ?? null);
        if ($goalId !== '') {
            $goal = Goal::query()->find($goalId);
            if ($goal !== null && ($project === null || $goal->project_id === $project->id)) {
                return $goal;
            }
        }

        if ($project === null || ! (bool) config('bossku.align_runs_to_active_goal', false)) {
            return null;
        }

        return Goal::query()
            ->where('project_id', $project->id)
            ->where('status', 'active')
            ->get()
            ->sortByDesc(fn (Goal $g): array => [
                self::PRIORITY_WEIGHT[strtolower((string) $g->priority)] ?? 0,
                $g->created_at?->getTimestamp() ?? 0,
            ])
            ->first();
    }

    /**
     * @return array{id: string, title: string, description: string, target_metric: string, status: string, progress: int, due_at: string|null}
     */
    public function contextBlock(Goal $goal): array
    {
        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => StringCoercion::toString($goal->description),
            'target_metric' => StringCoercion::toString($goal->target_metric),
            'status' => $goal->status,
            'progress' => (int) $goal->progress,
            'due_at' => $goal->due_at?->toIso8601String(),
        ];
    }
}
