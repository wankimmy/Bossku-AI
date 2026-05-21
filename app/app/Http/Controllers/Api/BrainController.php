<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\SkillCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            'knowledge_node_count'          => GraphNode::count(),
            'memory_count'                  => Memory::where('is_active', true)->count(),
        ]);
    }

    public function memoryGraph()
    {
        $memories = Memory::query()
            ->where('is_active', true)
            ->orderByDesc('confidence')
            ->limit(60)
            ->get();

        $nodes = [[
            'id' => 'brain-core',
            'label' => 'BosskuAI Memory',
            'type' => 'core',
            'has_conflict' => false,
            'confidence' => 1,
        ]];
        $edges = [];

        $typeHubs = [];

        foreach ($memories as $memory) {
            $nodeId = 'memory-'.$memory->id;
            $label = Str::limit($memory->human_summary ?: $memory->content, 48);
            $nodes[] = [
                'id' => $nodeId,
                'label' => $label,
                'type' => 'memory',
                'has_conflict' => false,
                'confidence' => (float) ($memory->confidence ?? 0.5),
                'source_type' => 'memory',
                'source_id' => $memory->id,
                'metadata' => [
                    'memory_type' => $memory->type,
                    'source' => $memory->source,
                ],
            ];

            $edges[] = [
                'id' => 'edge-core-'.$memory->id,
                'source_id' => 'brain-core',
                'target_id' => $nodeId,
                'relation' => 'recalls',
                'is_conflict' => false,
            ];

            $typeKey = (string) ($memory->type ?: 'general');
            if (! isset($typeHubs[$typeKey])) {
                $hubId = 'type-'.$typeKey;
                $typeHubs[$typeKey] = $hubId;
                $nodes[] = [
                    'id' => $hubId,
                    'label' => $typeKey,
                    'type' => 'memory_type',
                    'has_conflict' => false,
                    'confidence' => 0.85,
                ];
                $edges[] = [
                    'id' => 'edge-core-type-'.$typeKey,
                    'source_id' => 'brain-core',
                    'target_id' => $hubId,
                    'relation' => 'groups',
                    'is_conflict' => false,
                ];
            }

            $edges[] = [
                'id' => 'edge-type-'.$memory->id,
                'source_id' => $typeHubs[$typeKey],
                'target_id' => $nodeId,
                'relation' => 'typed_as',
                'is_conflict' => false,
            ];
        }

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }
}
