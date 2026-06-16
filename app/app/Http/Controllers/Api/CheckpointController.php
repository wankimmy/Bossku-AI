<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Services\Kernel\Checkpoint\CheckpointService;
use App\Services\Kernel\Checkpoint\DatabaseCheckpointSaver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Time-travel API over the graph kernel's checkpoints: inspect a run's
 * checkpoint history and fork a new run from any point.
 */
class CheckpointController extends Controller
{
    private CheckpointService $checkpoints;

    public function __construct()
    {
        $this->checkpoints = new CheckpointService(new DatabaseCheckpointSaver);
    }

    /** GET /api/runs/{id}/checkpoints — checkpoint history, newest first. */
    public function index(string $id): JsonResponse
    {
        $run = Run::findOrFail($id);

        return response()->json([
            'run_id' => (string) $run->getKey(),
            'checkpoints' => $this->checkpoints->history((string) $run->getKey()),
        ]);
    }

    /**
     * POST /api/runs/{id}/fork — fork a new run from a checkpoint.
     * Body: { checkpoint_id: string, state_patch?: object }
     */
    public function fork(string $id, Request $request): JsonResponse
    {
        $run = Run::findOrFail($id);

        $validated = $request->validate([
            'checkpoint_id' => ['required', 'string'],
            'state_patch' => ['sometimes', 'array'],
        ]);

        $fork = $this->checkpoints->fork(
            $run,
            $validated['checkpoint_id'],
            is_array($validated['state_patch'] ?? null) ? $validated['state_patch'] : [],
        );

        return response()->json([
            'message' => 'Run forked from checkpoint.',
            'forked_run_id' => (string) $fork->getKey(),
            'parent_run_id' => (string) $run->getKey(),
            'checkpoint_id' => $validated['checkpoint_id'],
        ], 201);
    }
}
