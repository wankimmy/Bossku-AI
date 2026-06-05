<?php

namespace App\Services\Project;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Setting;
class ProjectService
{
    public const SETTING_ACTIVE_PROJECT_ID = 'active_project_id';

    public function workspaceMount(): string
    {
        return rtrim((string) config('bossku.workspace_mount', '/workspace'), '/');
    }

    public function workspaceHostPrefix(): string
    {
        $prefix = (string) config('bossku.workspace_host_prefix', '');

        return $this->normalizeHostPath($prefix);
    }

    public function normalizeHostPath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        while (str_contains($normalized, '//')) {
            $normalized = str_replace('//', '/', $normalized);
        }

        return rtrim($normalized, '/');
    }

    /**
     * Map a host path under BOSSKU_WORKSPACE_HOST_PREFIX to /workspace/...
     */
    public function hostToContainer(string $hostPath): string
    {
        $host = $this->normalizeHostPath($hostPath);
        $prefix = $this->workspaceHostPrefix();
        $mount = $this->workspaceMount();

        if ($prefix === '') {
            // Native desktop mode: no Docker workspace mapping. Use the path directly.
            return $host;
        }

        if (! $this->hostPathUnderWorkspace($host, $prefix)) {
            throw new \InvalidArgumentException(
                "Path is outside the mounted workspace ({$prefix}). "
                .'Pick a folder under your workspace host prefix, or add another bind mount in docker-compose.yml and run docker compose up -d.'
            );
        }

        $relative = ltrim(substr($host, strlen($prefix)), '/');

        return $relative === '' ? $mount : $mount.'/'.$relative;
    }

    public function hostPathUnderWorkspace(string $hostPath, ?string $prefix = null): bool
    {
        $host = $this->normalizeHostPath($hostPath);
        $prefix = $prefix ?? $this->workspaceHostPrefix();

        if ($prefix === '') {
            return false;
        }

        return strcasecmp($host, $prefix) === 0
            || str_starts_with(strtolower($host), strtolower($prefix.'/'));
    }

    /**
     * @return array{project: Project, created: bool}
     */
    public function register(string $name, string $hostPath): array
    {
        $host = $this->normalizeHostPath($hostPath);
        $containerPath = $this->hostToContainer($host);

        $existing = Project::query()
            ->where('host_path', $host)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'name' => $name,
                'container_path' => $containerPath,
            ]);

            return ['project' => $existing->fresh(), 'created' => false];
        }

        $project = Project::query()->create([
            'name' => $name,
            'host_path' => $host,
            'container_path' => $containerPath,
            'is_active' => false,
        ]);

        if (! Project::query()->where('is_active', true)->exists()) {
            $project = $this->setActive($project->id);
        }

        return ['project' => $project, 'created' => true];
    }

    public function setActive(string $projectId): Project
    {
        $project = Project::query()->findOrFail($projectId);

        Project::query()->where('is_active', true)->update(['is_active' => false]);
        $project->update(['is_active' => true]);
        Setting::setValue(self::SETTING_ACTIVE_PROJECT_ID, $project->id);

        return $project->fresh();
    }

    public function activeProject(): ?Project
    {
        $active = Project::query()->where('is_active', true)->first();
        if ($active !== null) {
            return $active;
        }

        $id = Setting::getValue(self::SETTING_ACTIVE_PROJECT_ID);
        if ($id !== null && $id !== '') {
            return Project::query()->find($id);
        }

        return null;
    }

    public function activeContainerPath(): ?string
    {
        return $this->activeProject()?->container_path;
    }

    public function activeContainerPathOrDefault(): string
    {
        return $this->activeContainerPath() ?? (string) config('bossku.repo_root');
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceMeta(): array
    {
        return [
            'workspace_mount' => $this->workspaceMount(),
            'workspace_host_prefix' => $this->workspaceHostPrefix(),
            'default_repo_root' => (string) config('bossku.repo_root'),
        ];
    }

    public function evidenceRuleForPrompt(): string
    {
        return $this->agentWorkspaceContext().' '
            .'You MUST NOT report specific file paths, endpoint names, or vulnerabilities unless the executor tool log in this run includes '
            .'file_read_safe or file_search results for those files. '
            .'If no files have been read, return status needs_revision with reason no_files_read and empty findings arrays.';
    }

    public function agentWorkspaceContext(): string
    {
        $active = $this->activeProject();

        try {
            $root = app(ProjectPathResolver::class)->repoRoot();
        } catch (\Throwable $e) {
            $root = $active?->container_path ?? (string) config('bossku.repo_root');

            return 'WARNING: Active project is not mounted ('.$e->getMessage().'). '
                .'Register and activate the project under Project → Paths, then run docker compose up -d if you added a new mount.';
        }

        if ($active === null) {
            $base = 'Active repository root: '.$root.' (default Bossku-AI /repo mount). '
                .'To audit another codebase, add it under Project → Paths and click Activate before starting a run. '
                .'All file_read_safe and file_search paths must be relative to this root (e.g. app/Models/User.php). '
                .'Never pass Windows paths like C:\\Users\\... in tool payloads.';

            return $base.$this->bosskuToolkitContextSuffix($root);
        }

        $hints = app(ProjectRuntimeHints::class)->forPrompt($root);
        $suffix = $hints !== '' ? ' '.$hints : '';

        return 'Active project: "'.$active->name.'". '
            .'Repository root (container): '.$root.'. '
            .'Host path maps here: '.$active->host_path.'. '
            .'All file_read_safe and file_search paths must be relative to the repository root only (e.g. src/index.php). '
            .'Do not use Windows host paths or /repo unless that is the active root. '
            .'Project commands (git, docker compose, php artisan, tests) run in this root only — never hardcode another repo\'s folder or compose service name.'
            .$suffix
            .$this->bosskuToolkitContextSuffix($root);
    }

    protected function bosskuToolkitContextSuffix(string $repoRoot): string
    {
        if (! app(BosskuToolkitDetector::class)->isBosskuToolkitRepository($repoRoot)) {
            return '';
        }

        return ' '.BosskuToolkitPersonas::sharedPreamble()
            .' Agent personas for this run include Bossku self-improvement overlays (see Agent Personas in Settings).';
    }
}
