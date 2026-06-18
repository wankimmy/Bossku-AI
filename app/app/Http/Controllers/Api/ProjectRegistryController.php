<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Project;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectRuntimeHints;
use App\Services\Project\ProjectService;
use App\Services\Project\ProjectSkillsBootstrapService;
use Illuminate\Http\Request;

class ProjectRegistryController extends Controller
{
    public function __construct(
        protected ProjectService $projects,
        protected ProjectPathResolver $paths,
        protected ProjectSkillsBootstrapService $skillsBootstrap,
        protected ProjectFileDiscovery $discovery,
        protected ProjectRuntimeHints $runtimeHints,
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

    public function workspaceFolders(Request $request)
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:2000',
        ]);

        try {
            $resolved = $this->projects->resolveWorkspacePath((string) ($validated['path'] ?? ''));
            $folders = $this->projects->listWorkspaceFolders($resolved['relative']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'available' => false,
                'message' => $e->getMessage(),
                'workspace' => $this->projects->workspaceMeta(),
            ], 503);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'available' => false,
                'message' => $e->getMessage(),
                'workspace' => $this->projects->workspaceMeta(),
            ], 422);
        }

        return response()->json([
            'available' => true,
            'path' => $resolved['relative'],
            'absolute' => $resolved['absolute'],
            'folders' => $folders,
            'workspace' => $this->projects->workspaceMeta(),
        ]);
    }

    public function registerContainerPath(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'container_path' => 'required|string|max:2000',
            'activate' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->projects->registerContainerPath(
                $validated['name'],
                $validated['container_path'],
                (bool) ($validated['activate'] ?? true),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'workspace' => $this->projects->workspaceMeta(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'workspace' => $this->projects->workspaceMeta(),
            ], 503);
        }

        $project = $result['project'];
        $mounted = is_dir($project->container_path) || @realpath($project->container_path) !== false;

        $available = false;
        $error = null;
        $manifestTotal = null;

        if ($mounted) {
            try {
                $root = $this->paths->repoRoot();
                $available = true;
                try {
                    $manifestTotal = $this->discovery->manifest('', 1, 1)['total'];
                } catch (\Throwable) {
                    //
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = 'Selected folder is not mounted in the container: '.$project->container_path;
        }

        return response()->json([
            'project' => $project,
            'created' => $result['created'],
            'mounted' => $mounted,
            'available' => $available,
            'error' => $error,
            'manifest_total' => $manifestTotal,
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

        $manifestTotal = null;
        if ($available) {
            try {
                $manifestTotal = $this->discovery->manifest('', 1, 1)['total'];
            } catch (\Throwable) {
                //
            }
        }

        $runtimeHints = $available && $root !== null
            ? $this->runtimeHints->summarize($root)
            : null;

        return response()->json([
            'project' => $project,
            'repo_root' => $root,
            'available' => $available,
            'error' => $error,
            'manifest_total' => $manifestTotal,
            'runtime_hints' => $runtimeHints,
        ]);
    }

    public function bootstrapSkills()
    {
        try {
            $result = $this->skillsBootstrap->bootstrapIntoActiveProject();
        }
        catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json($result);
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
