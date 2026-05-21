<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;

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
        $run->update(['status' => 'running']);

        return response()->json(['message' => 'Run resumed.', 'run' => $run]);
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

        /** @var \App\Services\Learning\SkillCandidateGenerator $generator */
        $generator = app(\App\Services\Learning\SkillCandidateGenerator::class);
        $generator->maybeGenerate($run);

        return response()->json(['message' => 'skill candidate created']);
    }
}
