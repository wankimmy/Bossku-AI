<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Services\Specialists\SpecialistAgentDraftingService;

class RunActionController extends Controller
{
    public function pause(string $runId)
    {
        $run = Run::findOrFail($runId);
        $run->update(['status' => 'paused']);

        return response()->json(['message' => 'Run paused.', 'run' => $run]);
    }

    public function resume(string $runId)
    {
        $run = Run::findOrFail($runId);

        if ($run->status === 'awaiting_input') {
            return response()->json([
                'message' => 'Run is awaiting clarification. POST /api/runs/{id}/continue/stream with answers instead.',
                'run' => $run,
            ], 409);
        }

        $run->update(['status' => 'running']);

        return response()->json(['message' => 'Run status set to running (pipeline not re-executed). Use continue/stream after clarification.', 'run' => $run]);
    }

    public function rerunStep(string $runId, string $stepId)
    {
        Run::findOrFail($runId);
        RunStep::findOrFail($stepId);

        return response()->json(['message' => 'rerun enqueued']);
    }

    public function createSkill(string $runId)
    {
        $run = Run::findOrFail($runId);

        /** @var \App\Services\Skills\SkillCandidateGenerator $generator */
        $generator = app(\App\Services\Skills\SkillCandidateGenerator::class);
        $generator->maybeGenerate($run);

        return response()->json(['message' => 'skill candidate created']);
    }

    public function createSpecialistAgent(string $runId, SpecialistAgentDraftingService $drafting)
    {
        $run = Run::findOrFail($runId);
        $agent = $drafting->draftFromRun($run, [], force: true);

        return response()->json([
            'message' => 'specialist agent draft created',
            'agent' => $agent,
        ]);
    }
}
