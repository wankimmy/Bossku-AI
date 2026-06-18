<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Models\BosskuAi\WorkIssue;
use App\Services\Company\CompanyStaffService;
use App\Services\Company\WorkIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkIssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WorkIssue::query()
            ->with([
                'project:id,name',
                'run:id,status,created_at',
                'assigneeAgent:id,role_slug,display_name',
                'parentIssue:id,title,status',
            ])
            ->orderByRaw("CASE status WHEN 'backlog' THEN 0 WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'in_review' THEN 3 WHEN 'blocked' THEN 4 WHEN 'done' THEN 5 WHEN 'cancelled' THEN 6 ELSE 7 END")
            ->orderByDesc('updated_at');

        $project = $this->activeProject();
        if ($project !== null) {
            $query->where('project_id', $project->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('assignee_role_slug')) {
            $query->where('assignee_role_slug', $request->query('assignee_role_slug'));
        }

        return response()->json($query->paginate(100));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(
            WorkIssue::query()
                ->with(['project:id,name', 'run:id,status,created_at'])
                ->findOrFail($id)
        );
    }

    public function update(string $id, Request $request, WorkIssueService $workIssues, CompanyStaffService $staff): JsonResponse
    {
        $issue = WorkIssue::query()->findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'approval_state' => 'sometimes|string|in:draft,approved,rejected',
            'assignee_role_slug' => 'sometimes|nullable|string|max:120',
            'assignee_agent_id' => 'sometimes|nullable|uuid|exists:bossku_ai_specialist_agents,id',
            'parent_issue_id' => 'sometimes|nullable|uuid|exists:bossku_ai_work_issues,id',
            'metadata' => 'sometimes|array',
        ]);

        if (array_key_exists('assignee_role_slug', $data) && ! array_key_exists('assignee_agent_id', $data) && $issue->project_id !== null) {
            $project = Project::query()->find($issue->project_id);
            if ($project !== null && is_string($data['assignee_role_slug']) && $data['assignee_role_slug'] !== '') {
                $staff->seedDefaults($project);
                $data['assignee_agent_id'] = $staff->agentForRole($project, $data['assignee_role_slug'])?->id;
            }
        }

        $issue->update($data);

        if (array_key_exists('assignee_agent_id', $data) || array_key_exists('assignee_role_slug', $data)) {
            $workIssues->enqueueForAssigneeChange($issue->refresh());
        }

        return response()->json($issue->refresh()->load([
            'project:id,name',
            'run:id,status,created_at',
            'assigneeAgent:id,role_slug,display_name',
            'parentIssue:id,title,status',
        ]));
    }

    private function activeProject(): ?Project
    {
        return Project::query()
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();
    }
}
