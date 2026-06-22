<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Company\CompanyStaffService;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyStaffController extends Controller
{
    public function __construct(
        protected ProjectService $projects,
    ) {}

    public function index(CompanyStaffService $staff): JsonResponse
    {
        $project = $this->projects->activeProject();
        if ($project === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $staff->staffForProject($project)
                ->map(fn (SpecialistAgent $agent) => $agent->toOfficePayload())
                ->values(),
        ]);
    }

    public function seed(CompanyStaffService $staff): JsonResponse
    {
        $project = $this->projects->activeProject();
        if ($project === null) {
            return response()->json(['message' => 'No active project is registered.'], 422);
        }

        return response()->json([
            'data' => $staff->seedDefaults($project)
                ->map(fn (SpecialistAgent $agent) => $agent->toOfficePayload())
                ->values(),
        ]);
    }

    public function update(string $id, Request $request): JsonResponse
    {
        $agent = SpecialistAgent::query()
            ->where('is_company_staff', true)
            ->findOrFail($id);

        $data = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'trigger_keywords' => 'sometimes|array',
            'trigger_keywords.*' => 'string|max:80',
            'persona_content' => 'sometimes|nullable|string',
            'linked_skill_id' => 'sometimes|nullable|uuid|exists:bossku_ai_skills,id',
            'approval_status' => 'sometimes|string|in:draft,pending_review,approved,rejected,archived',
            'staff_active' => 'sometimes|boolean',
            'council_enabled' => 'sometimes|boolean',
            'runtime_mode' => 'sometimes|string|in:advisory,mixed',
            'staff_sort_order' => 'sometimes|integer|min:0|max:1000',
            'metadata' => 'sometimes|array',
        ]);

        if (array_key_exists('trigger_keywords', $data)) {
            $data['trigger_keywords'] = array_values(array_unique(array_map(
                static fn ($item) => strtolower(trim((string) $item)),
                $data['trigger_keywords'],
            )));
        }

        $agent->update($data);

        return response()->json($agent->refresh()->toOfficePayload());
    }
}
