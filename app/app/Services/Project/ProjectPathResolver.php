<?php

namespace App\Services\Project;

use Illuminate\Support\Str;

class ProjectPathResolver
{
    private const SKIP_DIRS = ['.git', 'node_modules', 'vendor', '.nuxt', '.output', 'dist', 'build'];

    public function repoRoot(): string
    {
        $root = (string) config('bossku.repo_root');
        $real = realpath($root);

        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Project repo root is not available: '.$root);
        }

        return $real;
    }

    /**
     * @return array{absolute: string, relative: string}
     */
    public function resolve(string $relativePath = ''): array
    {
        $root = $this->repoRoot();
        $rel = trim(str_replace('\\', '/', $relativePath), '/');
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
