<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\RunSupervisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunSupervisorController extends Controller
{
    public function __construct(
        private readonly RunSupervisorService $supervisor,
    ) {}

    public function spawn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:50000',
            'tasks' => 'required|array|min:1|max:8',
            'tasks.*.prompt' => 'required|string|max:50000',
            'tasks.*.branch_name' => 'nullable|string|max:191',
        ]);

        $defaultChildren = max(1, (int) config('bossku.supervisor_default_children', 2));
        /** @var list<array{prompt: string, branch_name?: string}> $tasks */
        $tasks = $validated['tasks'];
        if (count($tasks) === 1 && str_contains(strtolower($validated['prompt']), 'parallel')) {
            $tasks = $this->splitPromptIntoTasks($validated['prompt'], $defaultChildren);
        }

        $result = $this->supervisor->spawnParallelChildren($validated['prompt'], $tasks);

        return response()->json($result, 202);
    }

    public function status(string $id): JsonResponse
    {
        $run = Run::query()->find($id);
        if ($run === null) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        if ($run->run_kind !== 'supervisor') {
            return response()->json(['message' => 'Run is not a supervisor run.'], 422);
        }

        return response()->json($this->supervisor->supervisorStatus($run));
    }

    /**
     * @return list<array{prompt: string, branch_name?: string}>
     */
    protected function splitPromptIntoTasks(string $prompt, int $count): array
    {
        $tasks = [];
        for ($i = 0; $i < $count; $i++) {
            $tasks[] = [
                'prompt' => trim($prompt)."\n\n[Parallel slice ".($i + 1).'/'.$count.']',
                'branch_name' => 'bossku/parallel-'.$i.'-'.substr(uniqid(), -6),
            ];
        }

        return $tasks;
    }
}
