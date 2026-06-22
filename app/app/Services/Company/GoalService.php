<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\WorkIssue;

/**
 * Goal management and progress roll-up — the "manage goals, not PRs" layer.
 *
 * A goal aggregates progress from, in priority order: its sub-goals (average),
 * a numeric target metric (current/target), or its linked work issues
 * (completed/total). Progress bubbles up the goal tree, so finishing issues on a
 * leaf goal advances its parents automatically.
 */
class GoalService
{
    /** Work-issue statuses that count as complete for roll-up. */
    private const DONE_STATUSES = ['done', 'completed', 'closed', 'achieved', 'shipped'];

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function create(Project $project, array $attrs, ?Goal $parent = null): Goal
    {
        return Goal::query()->create([
            'project_id' => $project->id,
            'parent_goal_id' => $parent?->id,
            'title' => (string) ($attrs['title'] ?? 'Untitled goal'),
            'description' => $attrs['description'] ?? null,
            'status' => (string) ($attrs['status'] ?? 'active'),
            'priority' => (string) ($attrs['priority'] ?? 'medium'),
            'target_metric' => $attrs['target_metric'] ?? null,
            'target_value' => $attrs['target_value'] ?? null,
            'current_value' => $attrs['current_value'] ?? null,
            'progress' => max(0, min(100, (int) ($attrs['progress'] ?? 0))),
            'due_at' => $attrs['due_at'] ?? null,
            'metadata' => $attrs['metadata'] ?? null,
        ]);
    }

    /** Link a work issue to a goal and recompute the goal's progress. */
    public function attachIssue(Goal $goal, WorkIssue $issue): Goal
    {
        $issue->update(['goal_id' => $goal->id]);

        return $this->recomputeProgress($goal);
    }

    /** Update the numeric progress metric (e.g. current MRR) and roll up. */
    public function updateMetric(Goal $goal, float $currentValue): Goal
    {
        $goal->update(['current_value' => $currentValue]);

        return $this->recomputeProgress($goal);
    }

    /**
     * Recompute a goal's progress from the strongest available signal, then
     * bubble the change up to its parent.
     */
    public function recomputeProgress(Goal $goal): Goal
    {
        $children = $goal->childGoals()->get();
        if ($children->isNotEmpty()) {
            return $this->applyProgress($goal, (int) round((float) $children->avg('progress')));
        }

        $target = (float) ($goal->target_value ?? 0);
        if ($target > 0.0 && $goal->current_value !== null) {
            return $this->applyProgress($goal, (int) min(100, round(((float) $goal->current_value / $target) * 100)));
        }

        $issues = $goal->issues()->get();
        if ($issues->isNotEmpty()) {
            $done = $issues->filter(
                fn (WorkIssue $i): bool => in_array(strtolower((string) $i->status), self::DONE_STATUSES, true),
            )->count();

            return $this->applyProgress($goal, (int) round($done / $issues->count() * 100));
        }

        return $goal;
    }

    public function markStatus(Goal $goal, string $status): Goal
    {
        $goal->update(['status' => $status]);

        return $goal->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Goal $goal): array
    {
        $goal->loadCount('childGoals', 'issues');
        $issues = $goal->issues()->get();
        $doneIssues = $issues->filter(
            fn (WorkIssue $i): bool => in_array(strtolower((string) $i->status), self::DONE_STATUSES, true),
        )->count();

        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'status' => $goal->status,
            'progress' => $goal->progress,
            'priority' => $goal->priority,
            'target_metric' => $goal->target_metric,
            'target_value' => $goal->target_value,
            'current_value' => $goal->current_value,
            'sub_goals' => $goal->child_goals_count,
            'issues_total' => $issues->count(),
            'issues_done' => $doneIssues,
            'due_at' => $goal->due_at?->toIso8601String(),
        ];
    }

    private function applyProgress(Goal $goal, int $progress): Goal
    {
        $progress = max(0, min(100, $progress));
        $attrs = ['progress' => $progress];
        if ($progress >= 100 && $goal->status === 'active') {
            $attrs['status'] = 'achieved';
        }
        $goal->update($attrs);

        if ($goal->parent_goal_id !== null) {
            $parent = $goal->parentGoal()->first();
            if ($parent !== null) {
                $this->recomputeProgress($parent);
            }
        }

        return $goal->refresh();
    }
}
