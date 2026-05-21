<?php

namespace App\Services\Project;

use App\Models\BosskuAi\Project;
use Illuminate\Support\Str;

class ProjectPathResolver
{
    private const SKIP_DIRS = ['.git', 'node_modules', 'vendor', '.nuxt', '.output', 'dist', 'build'];

    public function repoRoot(): string
    {
        $active = Project::query()->where('is_active', true)->first();
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

    public function activeProject(): ?Project
    {
        return Project::query()->where('is_active', true)->first();
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
            if ($parentReal === false || ! Str::startsWith($parentReal, $root)) {
                throw new \InvalidArgumentException('Path denied or not found.');
            }

            $real = $parentReal.DIRECTORY_SEPARATOR.basename($combined);
            if (! Str::startsWith($real, $root)) {
                throw new \InvalidArgumentException('Path denied.');
            }
        } elseif (! Str::startsWith($real, $root)) {
            throw new \InvalidArgumentException('Path denied.');
        }

        $relative = $rel === '' ? '' : ltrim(str_replace($root, '', $real), DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        return ['absolute' => $real, 'relative' => $relative];
    }

    public function shouldSkipDir(string $name): bool
    {
        return in_array($name, self::SKIP_DIRS, true);
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
