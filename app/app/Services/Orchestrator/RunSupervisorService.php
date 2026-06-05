<?php

namespace App\Services\Orchestrator;

use App\Jobs\ProcessChildRunJob;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Workspace\WorktreeManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunSupervisorService
{
    public function __construct(
        private readonly WorktreeManager $worktrees,
        private readonly SupervisorMergeCoordinator $mergeCoordinator,
    ) {}

    /**
     * @param  list<array{prompt: string, branch_name?: string}>  $tasks
     * @return array{parent_run_id: string, child_run_ids: list<string>}
     */
    public function spawnParallelChildren(string $parentPrompt, array $tasks, ?Project $project = null): array
    {
        $max = max(1, (int) config('bossku.supervisor_max_children', 4));
        $tasks = array_slice($tasks, 0, $max);
        if ($tasks === []) {
            throw new \InvalidArgumentException('At least one child task is required.');
        }

        $parent = Run::query()->create([
            'prompt' => $parentPrompt,
            'status' => 'running',
            'run_kind' => 'supervisor',
            'metadata' => [
                'supervisor' => true,
                'child_count' => count($tasks),
            ],
        ]);

        $childIds = [];
        foreach ($tasks as $slot => $task) {
            $prompt = trim((string) ($task['prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }

            $branch = (string) ($task['branch_name'] ?? 'bossku/child-'.$slot.'-'.Str::random(6));
            $child = Run::query()->create([
                'prompt' => $prompt,
                'status' => 'queued',
                'run_kind' => 'child',
                'parent_run_id' => $parent->getKey(),
                'supervisor_slot' => $slot,
                'metadata' => [
                    'parent_run_id' => $parent->getKey(),
                    'workspace_intent' => [
                        'branch_name' => $branch,
                        'base_ref' => 'HEAD',
                    ],
                ],
            ]);

            if ($this->worktrees->enabled()) {
                try {
                    $this->worktrees->provisionForRun($child, $project, [
                        'branch_name' => $branch,
                        'base_ref' => 'HEAD',
                    ]);
                } catch (\Throwable) {
                    $child->update(['status' => 'failed', 'metadata' => array_merge(
                        is_array($child->metadata) ? $child->metadata : [],
                        ['workspace_error' => 'Worktree provisioning failed'],
                    )]);
                    $childIds[] = (string) $child->getKey();

                    continue;
                }
            }

            $child->update(['status' => 'queued']);
            ProcessChildRunJob::dispatch((string) $child->getKey());
            $childIds[] = (string) $child->getKey();
        }

        $parent->update([
            'metadata' => array_merge(is_array($parent->metadata) ? $parent->metadata : [], [
                'child_run_ids' => $childIds,
            ]),
        ]);

        return [
            'parent_run_id' => (string) $parent->getKey(),
            'child_run_ids' => $childIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supervisorStatus(Run $parent): array
    {
        $children = $parent->childRuns()->with('workspace')->get();
        $completed = $children->whereIn('status', ['completed', 'failed', 'partial'])->count();

        return [
            'parent_run_id' => $parent->getKey(),
            'status' => $parent->status,
            'children_total' => $children->count(),
            'children_completed' => $completed,
            'children' => $children->map(fn (Run $c) => [
                'id' => $c->getKey(),
                'status' => $c->status,
                'run_kind' => $c->run_kind,
                'supervisor_slot' => $c->supervisor_slot,
                'prompt' => Str::limit((string) $c->prompt, 120),
                'workspace' => $c->workspace ? [
                    'branch_name' => $c->workspace->branch_name,
                    'worktree_path' => $c->workspace->worktree_path,
                    'status' => $c->workspace->status,
                ] : null,
            ])->values()->all(),
            'ready_to_synthesize' => $children->count() > 0 && $completed === $children->count(),
        ];
    }

    public function maybeFinalizeParent(Run $parent): void
    {
        DB::transaction(function () use ($parent): void {
            /** @var Run|null $locked */
            $locked = Run::query()->whereKey($parent->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $meta = is_array($locked->metadata) ? $locked->metadata : [];
            if (isset($meta['synthesized_at'])) {
                return;
            }

            $status = $this->supervisorStatus($locked);
            if (! ($status['ready_to_synthesize'] ?? false)) {
                return;
            }

            /** @var Collection<int, Run> $children */
            $children = $locked->childRuns()->with('workspace')->get();
            $merged = $this->mergeCoordinator->synthesize($locked, $children);

            $locked->update([
                'status' => $merged['status'],
                'final_output' => $merged['final_output'],
                'metadata' => array_merge($meta, [
                    'synthesized_at' => now()->toIso8601String(),
                    'supervisor_merge' => $merged['merge_report'],
                ]),
            ]);
        });
    }
}
