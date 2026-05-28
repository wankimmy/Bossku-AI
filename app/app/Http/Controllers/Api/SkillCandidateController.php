<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\SkillCandidate;
use Illuminate\Http\Request;

class SkillCandidateController extends Controller
{
    public function index(Request $request)
    {
        $query = SkillCandidate::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('approval_status', $request->query('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        return response()->json($query->paginate(30));
    }

    public function show(string $id)
    {
        return response()->json(SkillCandidate::findOrFail($id));
    }

    public function approve(string $id, Request $request)
    {
        $candidate = SkillCandidate::findOrFail($id);

        /** @var \App\Services\Skills\SkillCandidateGenerator $generator */
        $generator = app(\App\Services\Skills\SkillCandidateGenerator::class);
        $skill = $generator->approve($candidate);

        return response()->json([
            'message'   => 'Candidate approved.',
            'candidate' => $candidate->refresh(),
            'skill'     => $skill,
        ]);
    }

    public function reject(string $id, Request $request)
    {
        $candidate = SkillCandidate::findOrFail($id);

        $candidate->update([
            'approval_status' => 'rejected',
            'reviewed_at'     => now(),
            'metadata'        => array_merge($candidate->metadata ?? [], [
                'rejection_reason' => $request->input('reason'),
            ]),
        ]);

        return response()->json(['message' => 'Candidate rejected.', 'candidate' => $candidate]);
    }

    public function update(string $id, Request $request)
    {
        $candidate = SkillCandidate::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'sometimes|string',
            'draft_content' => 'sometimes|string',
        ]);

        $candidate->update($data);

        return response()->json($candidate);
    }
}
