<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Project;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectService;
use Illuminate\Http\Request;

class ProjectRegistryController extends Controller
{
    public function __construct(
        protected ProjectService $projects,
        protected ProjectPathResolver $paths,
    ) {}

    public function list()
    {
        $active = $this->projects->activeProject();

        return response()->json([
            'projects' => Project::query()->orderBy('name')->get(),
            'active_project_id' => $active?->id,
            'workspace' => $this->projects->workspaceMeta(),
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'host_path' => 'required|string|max:2000',
        ]);

        try {
            $result = $this->projects->register($validated['name'], $validated['host_path']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'under_workspace' => false,
                'workspace' => $this->projects->workspaceMeta(),
            ], 422);
        }

        $project = $result['project'];
        $mounted = is_dir($project->container_path) || @realpath($project->container_path) !== false;

        return response()->json([
            'project' => $project,
            'created' => $result['created'],
            'mounted' => $mounted,
            'under_workspace' => true,
        ], $result['created'] ? 201 : 200);
    }

    public function activate(string $id)
    {
        $project = $this->projects->setActive($id);

        try {
            $root = $this->paths->repoRoot();
            $available = true;
            $error = null;
        } catch (\Throwable $e) {
            $available = false;
            $root = null;
            $error = $e->getMessage();
        }

        return response()->json([
            'project' => $project,
            'repo_root' => $root,
            'available' => $available,
            'error' => $error,
        ]);
    }

    public function destroy(string $id)
    {
        $project = Project::query()->findOrFail($id);

        if ($project->is_active) {
            return response()->json([
                'message' => 'Cannot delete the active project. Activate another project first.',
            ], 422);
        }

        $project->delete();

        return response()->json(['message' => 'Project removed.']);
    }
}
