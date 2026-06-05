<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\SpecialistAgent;
use Illuminate\Http\Request;

class SpecialistAgentController extends Controller
{
    public function index(Request $request)
    {
        $query = SpecialistAgent::query()
            ->with(['project:id,name', 'linkedSkill:id,name'])
            ->orderByRaw("CASE approval_status WHEN 'draft' THEN 0 WHEN 'pending_review' THEN 1 WHEN 'approved' THEN 2 WHEN 'archived' THEN 3 ELSE 4 END")
            ->orderByDesc('updated_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('approval_status', $request->query('status'));
        }

        return response()->json($query->paginate(30));
    }

    public function show(string $id)
    {
        return response()->json(
            SpecialistAgent::query()
                ->with(['project:id,name', 'linkedSkill:id,name'])
                ->findOrFail($id)
        );
    }

    public function update(string $id, Request $request)
    {
        $agent = SpecialistAgent::query()->findOrFail($id);
        $data = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'trigger_keywords' => 'sometimes|array',
            'trigger_keywords.*' => 'string|max:80',
            'persona_content' => 'sometimes|nullable|string',
            'linked_skill_id' => 'sometimes|nullable|uuid|exists:bossku_ai_skills,id',
            'approval_status' => 'sometimes|string|in:draft,pending_review,approved,rejected,archived',
            'pixel_palette' => 'sometimes|integer|min:0|max:31',
            'pixel_hue_shift' => 'sometimes|integer|min:-180|max:180',
            'seat_id' => 'sometimes|nullable|string|max:120',
            'metadata' => 'sometimes|array',
        ]);

        if (array_key_exists('trigger_keywords', $data)) {
            $data['trigger_keywords'] = array_values(array_unique(array_map(
                static fn ($item) => strtolower(trim((string) $item)),
                $data['trigger_keywords'],
            )));
        }

        $agent->update($data);

        return response()->json($agent->refresh());
    }

    public function approve(string $id)
    {
        $agent = SpecialistAgent::query()->findOrFail($id);
        $agent->update(['approval_status' => 'approved']);

        return response()->json($agent->refresh());
    }

    public function reject(string $id, Request $request)
    {
        $agent = SpecialistAgent::query()->findOrFail($id);
        $metadata = array_merge($agent->metadata ?? [], [
            'rejection_reason' => $request->input('reason'),
            'reviewed_at' => now()->toISOString(),
        ]);
        $agent->update([
            'approval_status' => 'rejected',
            'metadata' => $metadata,
        ]);

        return response()->json($agent->refresh());
    }

    public function archive(string $id)
    {
        $agent = SpecialistAgent::query()->findOrFail($id);
        $agent->update(['approval_status' => 'archived']);

        return response()->json($agent->refresh());
    }
}
