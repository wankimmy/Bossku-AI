<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\SkillCandidate;
use Illuminate\Support\Facades\DB;

class BrainController extends Controller
{
    public function index()
    {
        $learningEventsByStatus = LearningEvent::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $skillCandidatesByStatus = SkillCandidate::query()
            ->select('approval_status', DB::raw('count(*) as count'))
            ->groupBy('approval_status')
            ->pluck('count', 'approval_status');

        $feedbackUnprocessed = FeedbackItem::where('processed', false)->count();

        $memoryConfidence = Memory::query()
            ->select(
                DB::raw('avg(confidence) as avg'),
                DB::raw('min(confidence) as min'),
                DB::raw('max(confidence) as max'),
            )
            ->first();

        $conflictCount = GraphNode::where('has_conflict', true)->count();

        return response()->json([
            'learning_events_by_status'     => $learningEventsByStatus,
            'skill_candidates_by_status'    => $skillCandidatesByStatus,
            'feedback_unprocessed_count'    => $feedbackUnprocessed,
            'memory_confidence'             => [
                'avg' => $memoryConfidence?->avg !== null ? round((float) $memoryConfidence->avg, 4) : null,
                'min' => $memoryConfidence?->min !== null ? (float) $memoryConfidence->min : null,
                'max' => $memoryConfidence?->max !== null ? (float) $memoryConfidence->max : null,
            ],
            'conflict_count'                => $conflictCount,
        ]);
    }
}
