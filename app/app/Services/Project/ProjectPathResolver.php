<?php

namespace App\Services\Project;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Setting;

class ProjectPathResolver
{
    public function __construct(
        private readonly ?RunExecutionContext $runContext = null,
    ) {}

    public function repoRoot(): string
    {
        if ($this->runContext !== null) {
            return $this->runContext->executionContext($this)->repoRoot;
        }

        return $this->repoRootWithoutRun();
    }

    public function repoRootWithoutRun(): string
    {
        $active = $this->activeProject();
        $root = $active?->container_path ?: (string) config('bossku.repo_root');
        $real = realpath($root);

        if ($real === false || ! is_dir($real)) {
            $label = $active
                ? "Active project \"{$active->name}\" is not mounted in the container: {$root}"
                : 'Project repo root is not available: '.$root;

            throw new \RuntimeException($label);
        }

        return $real;
    }

    public function executionContext(): ExecutionContext
    {
        if ($this->runContext !== null) {
            return $this->runContext->executionContext($this);
        }

        return ExecutionContext::fromRepoRoot($this->repoRootWithoutRun());
    }

    public function activeProject(): ?Project
    {
        $active = Project::query()->where('is_active', true)->first();
        if ($active !== null) {
            return $active;
        }

        $id = Setting::getValue(ProjectService::SETTING_ACTIVE_PROJECT_ID);
        if ($id !== null && $id !== '') {
            return Project::query()->find($id);
        }

        return null;
    }

    /**
     * Turn host/absolute paths from prompts into paths relative to the active repo root.
     */
    public function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }

        $active = $this->activeProject();
        if ($active !== null) {
            $host = rtrim(str_replace('\\', '/', $active->host_path), '/');
            $lowerPath = strtolower($path);
            $lowerHost = strtolower($host);
            if ($lowerHost !== '' && ($lowerPath === $lowerHost || str_starts_with($lowerPath, $lowerHost.'/'))) {
                $path = ltrim(substr($path, strlen($host)), '/');
            }

            $container = rtrim(str_replace('\\', '/', $active->container_path), '/');
            $lowerContainer = strtolower($container);
            if ($lowerContainer !== '' && ($lowerPath === $lowerContainer || str_starts_with($lowerPath, $lowerContainer.'/'))) {
                $path = ltrim(substr($path, strlen($container)), '/');
            }
        }

        if (preg_match('#^[a-z]:/#i', $path)) {
            $path = ltrim((string) preg_replace('#^[a-z]:/#i', '', $path), '/');
        }

        return trim($path, '/');
    }

    /**
     * @return array{absolute: string, relative: string}
     */
    public function resolve(string $relativePath = ''): array
    {
        $root = $this->repoRoot();
        $rel = $this->normalizeRelativePath($relativePath);
        $combined = $rel === '' ? $root : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $real = realpath($combined);

        if ($real === false) {
            $parent = dirname($combined);
            $parentReal = realpath($parent);
            if ($parentReal === false || ! $this->pathWithinRoot($parentReal, $root)) {
                throw new \InvalidArgumentException('Path denied or not found.');
            }

            $real = $parentReal.DIRECTORY_SEPARATOR.basename($combined);
            if (! $this->pathWithinRoot($real, $root)) {
                throw new \InvalidArgumentException('Path denied.');
            }
        } elseif (! $this->pathWithinRoot($real, $root)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $relative = $rel === '' ? '' : ltrim(str_replace($root, '', $real), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        return ['absolute' => $real, 'relative' => $relative];
    }

    /**
     * Resolve a write target, allowing missing parent directories as long as the
     * deepest existing parent is still inside the active project root.
     *
     * @return array{absolute: string, relative: string}
     */
    public function resolveForWrite(string $relativePath): array
    {
        $root = $this->repoRoot();
        $original = trim(str_replace('\\', '/', $relativePath));

        if ($this->isUnmappedAbsolutePath($original)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $rel = $this->normalizeRelativePath($relativePath);
        if ($rel === '' || $this->containsTraversal($rel)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $combined = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $existing = file_exists($combined) ? $combined : dirname($combined);

        while (! file_exists($existing)) {
            $parent = dirname($existing);
            if ($parent === $existing) {
                throw new \InvalidArgumentException('Path denied or not found.');
            }
            $existing = $parent;
        }

        $existingReal = realpath($existing);
        if ($existingReal === false || ! $this->pathWithinRoot($existingReal, $root)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $existingNormalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existing), DIRECTORY_SEPARATOR);
        $combinedNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $combined);
        $missingTail = ltrim(substr($combinedNormalized, strlen($existingNormalized)), DIRECTORY_SEPARATOR);
        $absolute = $missingTail === ''
            ? $existingReal
            : $existingReal.DIRECTORY_SEPARATOR.$missingTail;

        if (! $this->pathWithinRoot($absolute, $root)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $relative = ltrim(str_replace($root, '', $absolute), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        return ['absolute' => $absolute, 'relative' => $relative];
    }

    public function shouldSkipDir(string $name): bool
    {
        $dirs = config('bossku.skip_dirs', []);

        return is_array($dirs) && in_array($name, $dirs, true);
    }

    private function pathWithinRoot(string $path, string $root): bool
    {
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }

    private function containsTraversal(string $relativePath): bool
    {
        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function isUnmappedAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $isAbsolute = str_starts_with($path, '/') || preg_match('#^[a-z]:/#i', $path) === 1;
        if (! $isAbsolute) {
            return false;
        }

        $active = $this->activeProject();
        if ($active === null) {
            return true;
        }

        $lowerPath = strtolower($path);
        foreach ([$active->host_path, $active->container_path] as $root) {
            $root = rtrim(strtolower(str_replace('\\', '/', (string) $root)), '/');
            if ($root !== '' && ($lowerPath === $root || str_starts_with($lowerPath, $root.'/'))) {
                return false;
            }
        }

        return true;
    }

    public function unifiedDiff(string $path, string $before, string $after): string
    {
        $oldLines = preg_split("/\r\n|\n|\r/", $before) ?: [];
        $newLines = preg_split("/\r\n|\n|\r/", $after) ?: [];
        $lines = ['--- '.$path, '+++ '.$path];

        $max = max(count($oldLines), count($newLines));
        for ($i = 0; $i < $max; $i++) {
            $old = $oldLines[$i] ?? null;
            $new = $newLines[$i] ?? null;
            if ($old === $new) {
                if ($old !== null) {
                    $lines[] = ' '.$old;
                }

                continue;
            }
            if ($old !== null) {
                $lines[] = '-'.$old;
            }
            if ($new !== null) {
                $lines[] = '+'.$new;
            }
        }

        return implode("\n", $lines);
    }
}
