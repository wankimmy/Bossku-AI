<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Company\AgentWakeupDispatcher;
use App\Services\Company\TeamsCatalogService;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyTeamsController extends Controller
{
    public function __construct(
        protected ProjectService $projects,
    ) {}

    public function index(TeamsCatalogService $teams): JsonResponse
    {
        return response()->json(['data' => $teams->teams()]);
    }

    public function install(Request $request, TeamsCatalogService $teams): JsonResponse
    {
        $project = $this->projects->activeProject();
        if ($project === null) {
            return response()->json(['message' => 'No active project is registered.'], 422);
        }

        $data = $request->validate([
            'team_slug' => 'required|string|max:80',
        ]);

        $installed = $teams->installTeam($project, (string) $data['team_slug']);
        if ($installed === 0) {
            return response()->json(['message' => 'Unknown team slug.'], 422);
        }

        return response()->json([
            'team_slug' => $data['team_slug'],
            'installed' => $installed,
        ]);
    }

    public function dispatchWakeups(AgentWakeupDispatcher $dispatcher, Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);

        return response()->json($dispatcher->dispatchQueued(max(1, min($limit, 50))));
    }
}
