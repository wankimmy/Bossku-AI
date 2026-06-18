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
     * Resolve a path relative to the workspace mount (empty = workspace root).
     *
     * @return array{absolute: string, relative: string}
     */
    public function resolveWorkspacePath(string $relativePath = ''): array
    {
        $mount = $this->workspaceMount();
        $mountReal = realpath($mount);

        if ($mountReal === false || ! is_dir($mountReal)) {
            throw new \RuntimeException(
                'Docker workspace mount is not available at '.$mount.'. '
                .'Check ../:/workspace in docker-compose.yml and restart containers.'
            );
        }

        $rel = $this->normalizeHostPath($relativePath);
        if (str_contains($rel, '..')) {
            throw new \InvalidArgumentException('Path traversal is not allowed.');
        }

        $combined = $rel === '' ? $mountReal : $mountReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $real = realpath($combined);

        if ($real === false || ! is_dir($real)) {
            throw new \InvalidArgumentException('Workspace folder not found.');
        }

        if (! str_starts_with($real, $mountReal)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $relative = $rel === '' ? '' : ltrim(str_replace($mountReal, '', $real), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        return ['absolute' => $real, 'relative' => $relative];
    }

    /**
     * @return list<array{name: string, path: string, relative: string, has_children: bool}>
     */
    public function listWorkspaceFolders(string $relativePath = ''): array
    {
        $resolved = $this->resolveWorkspacePath($relativePath);
        $absolute = $resolved['absolute'];
        $baseRelative = $resolved['relative'];
        $skipDirs = config('bossku.skip_dirs', []);
        $entries = [];

        foreach (scandir($absolute) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (is_array($skipDirs) && in_array($name, $skipDirs, true)) {
                continue;
            }

            $full = $absolute.DIRECTORY_SEPARATOR.$name;
            if (! is_dir($full)) {
                continue;
            }

            $rel = $baseRelative === '' ? $name : $baseRelative.'/'.$name;
            $containerPath = $this->workspaceMount().($rel === '' ? '' : '/'.$rel);
            $hasChildren = false;

            foreach (scandir($full) ?: [] as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }
                if (is_array($skipDirs) && in_array($child, $skipDirs, true)) {
                    continue;
                }
                if (is_dir($full.DIRECTORY_SEPARATOR.$child)) {
                    $hasChildren = true;
                    break;
                }
            }

            $entries[] = [
                'name' => $name,
                'path' => $containerPath,
                'relative' => $rel,
                'has_children' => $hasChildren,
            ];
        }

        usort($entries, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $entries;
    }

    public function containerToHost(string $containerPath): string
    {
        $container = $this->normalizeHostPath($containerPath);
        $mount = $this->workspaceMount();
        $prefix = $this->workspaceHostPrefix();

        if ($prefix === '') {
            return $container;
        }

        if (! str_starts_with(strtolower($container), strtolower($mount))) {
            return $container;
        }

        $relative = ltrim(substr($container, strlen($mount)), '/');

        return $relative === '' ? $prefix : $prefix.'/'.$relative;
    }

    /**
     * @return array{project: Project, created: bool}
     */
    public function registerContainerPath(string $name, string $containerPath, bool $autoActivate = true): array
    {
        $container = $this->normalizeHostPath($containerPath);
        $mount = $this->workspaceMount();

        if (! str_starts_with(strtolower($container), strtolower($mount))) {
            throw new \InvalidArgumentException(
                'Container path must be under '.$mount.'. Use the workspace folder browser to pick a folder.'
            );
        }

        $relative = ltrim(substr($container, strlen($mount)), '/');
        $this->resolveWorkspacePath($relative);

        $hostPath = $this->containerToHost($container);
        $realContainer = realpath(str_replace('/', DIRECTORY_SEPARATOR, $container)) ?: $container;

        $existing = Project::query()
            ->where('container_path', $realContainer)
            ->orWhere('host_path', $hostPath)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'name' => $name,
                'host_path' => $hostPath,
                'container_path' => $realContainer,
            ]);
            $project = $existing->fresh();

            if ($autoActivate) {
                $project = $this->setActive($project->id);
            }

            return ['project' => $project, 'created' => false];
        }

        $project = Project::query()->create([
            'name' => $name,
            'host_path' => $hostPath,
            'container_path' => $realContainer,
            'is_active' => false,
        ]);

        if ($autoActivate || ! Project::query()->where('is_active', true)->exists()) {
            $project = $this->setActive($project->id);
        }

        return ['project' => $project, 'created' => true];
    }

    /**
     * @return array{project: Project, created: bool}
     */
    public function register(string $name, string $hostPath): array
    {
        $host = $this->normalizeHostPath($hostPath);
        $prefix = $this->workspaceHostPrefix();

        if ($prefix === '' && preg_match('#^[a-z]:/#i', $host)) {
            throw new \InvalidArgumentException(
                'Windows host paths cannot be registered in Docker without BOSSKU_WORKSPACE_HOST_PREFIX. '
                .'Click Open Folder to browse '.$this->workspaceMount().', or set BOSSKU_WORKSPACE_HOST_PREFIX in app/.env.'
            );
        }

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
