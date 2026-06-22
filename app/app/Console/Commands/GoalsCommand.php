<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Services\Company\GoalService;
use Illuminate\Console\Command;

class GoalsCommand extends Command
{
    protected $signature = 'bosskuai:goals
                            {action=list : list | create | recompute}
                            {title? : Goal title (for create)}
                            {--project= : Project ID or name (defaults to active project)}
                            {--metric= : Target metric label, e.g. "$1M MRR"}
                            {--target= : Numeric target value}
                            {--current= : Current numeric value}
                            {--id= : Goal id (for recompute)}';

    protected $description = 'Manage business goals that work rolls up to (manage goals, not PRs).';

    public function handle(GoalService $goals): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'recompute') {
            $goal = Goal::query()->find((string) $this->option('id'));
            if ($goal === null) {
                $this->error('Goal not found: '.$this->option('id'));

                return self::FAILURE;
            }
            $goal = $goals->recomputeProgress($goal);
            $this->line($goal->title.' → '.$goal->progress.'% ('.$goal->status.')');

            return self::SUCCESS;
        }

        $project = $this->resolveProject();
        if ($project === null) {
            $this->error('No active project. Pass --project=<id|name>.');

            return self::FAILURE;
        }

        if ($action === 'create') {
            $title = (string) $this->argument('title');
            if (trim($title) === '') {
                $this->error('Title is required for create.');

                return self::FAILURE;
            }
            $goal = $goals->create($project, [
                'title' => $title,
                'target_metric' => $this->option('metric'),
                'target_value' => $this->option('target') !== null ? (float) $this->option('target') : null,
                'current_value' => $this->option('current') !== null ? (float) $this->option('current') : null,
            ]);
            $this->info('Created goal '.$goal->id.': '.$goal->title);

            return self::SUCCESS;
        }

        // list
        $rows = Goal::query()->where('project_id', $project->id)->orderBy('created_at')->get()
            ->map(fn (Goal $g) => [$g->id, $g->title, $g->status, $g->progress.'%', $g->target_metric ?? '—'])
            ->all();

        if ($rows === []) {
            $this->line('No goals yet for project "'.$project->name.'".');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Title', 'Status', 'Progress', 'Metric'], $rows);

        return self::SUCCESS;
    }

    private function resolveProject(): ?Project
    {
        $arg = $this->option('project');
        if ($arg !== null && $arg !== '') {
            return Project::query()->where('id', $arg)->orWhere('name', $arg)->first();
        }

        return Project::query()->where('is_active', true)->first();
    }
}
