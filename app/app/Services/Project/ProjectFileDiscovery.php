<?php

namespace App\Services\Project;

use Symfony\Component\Finder\Finder;

class ProjectFileDiscovery
{
    public function __construct(
        protected ProjectPathResolver $paths,
    ) {}

    /**
     * @return list<string>
     */
    public function skipDirs(): array
    {
        $dirs = config('bossku.skip_dirs');

        return is_array($dirs) ? array_values($dirs) : [];
    }

    public function maxSearchMatches(): int
    {
        return max(1, (int) config('bossku.max_search_matches', 100));
    }

    public function maxGlobMatches(): int
    {
        return max(1, (int) config('bossku.max_glob_matches', 100));
    }

    public function maxManifestPaths(): int
    {
        return max(1, (int) config('bossku.max_manifest_paths', 5000));
    }

    /**
     * @return list<string> relative paths
     */
    public function findByBasename(string $name, ?int $limit = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $limit ??= $this->maxGlobMatches();
        $root = $this->paths->repoRoot();
        $finder = $this->baseFinder($root)
            ->files()
            ->name('*'.$name.'*');

        return $this->collectRelativePaths($finder, $root, $limit);
    }

    /**
     * @return list<string> relative paths matching glob (supports ** segments)
     */
    public function globPaths(string $pattern, ?int $limit = null): array
    {
        $pattern = trim(str_replace('\\', '/', $pattern));
        if ($pattern === '') {
            return [];
        }

        $limit ??= $this->maxGlobMatches();

        if (preg_match('#^\*\*/?\*(.+)\*$#', $pattern, $m)) {
            return $this->findByBasename($m[1], $limit);
        }

        $root = $this->paths->repoRoot();

        $finder = $this->baseFinder($root)->files();

        if (str_contains($pattern, '/')) {
            $parts = explode('/', $pattern);
            $name = (string) array_pop($parts);
            $pathFilter = implode('/', array_filter($parts, static fn (string $p) => $p !== '' && $p !== '**'));
            $finder->in($root);
            if ($pathFilter !== '') {
                $finder->path($pathFilter);
            }
            $finder->name($name === '' || $name === '*' || $name === '**' ? '*' : $name);
        } else {
            $finder->in($root)->name($pattern);
        }

        return $this->collectRelativePaths($finder, $root, $limit);
    }

    public function resolvePathHint(string $hint): ?string
    {
        $hint = trim($hint);
        if ($hint === '') {
            return null;
        }

        if (preg_match('/^[A-Z][A-Za-z0-9_]*Controller$/', $hint)) {
            $laravelPath = 'app/Http/Controllers/'.$hint.'.php';
            try {
                $resolved = $this->paths->resolve($laravelPath);
                if (is_file($resolved['absolute'])) {
                    return $resolved['relative'];
                }
            } catch (\Throwable) {
                //
            }
        }

        $normalized = $this->paths->normalizeRelativePath($hint);
        if ($normalized !== '') {
            try {
                $resolved = $this->paths->resolve($normalized);

                return is_file($resolved['absolute']) ? $resolved['relative'] : null;
            } catch (\Throwable) {
                // fall through to basename search
            }
        }

        $base = pathinfo($hint, PATHINFO_FILENAME);
        if ($base === '' || $base === '.') {
            $base = $hint;
        }

        $matches = $this->findByBasename($base, 5);
        if (count($matches) === 1) {
            return $matches[0];
        }

        foreach ($matches as $path) {
            if (strcasecmp(basename($path), basename($hint)) === 0) {
                return $path;
            }
        }

        return $matches[0] ?? null;
    }

    /**
     * @return list<string> relative controller paths from routes/web.php
     */
    public function controllersFromRoutesFile(): array
    {
        try {
            $resolved = $this->paths->resolve('routes/web.php');
        } catch (\Throwable) {
            return [];
        }

        if (! is_file($resolved['absolute'])) {
            return [];
        }

        $content = (string) file_get_contents($resolved['absolute']);
        $paths = [];

        if (preg_match_all('/use\s+App\\\\Http\\\\Controllers\\\\([A-Za-z0-9_]+)\s*;/', $content, $m)) {
            foreach (array_unique($m[1]) as $class) {
                $candidate = 'app/Http/Controllers/'.$class.'.php';
                try {
                    $r = $this->paths->resolve($candidate);
                    if (is_file($r['absolute'])) {
                        $paths[] = $r['relative'];
                    }
                } catch (\Throwable) {
                    //
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Paginated recursive path listing for manifest API.
     *
     * @return array{root: string, path: string, paths: list<string>, total: int, truncated: bool, skipped_dirs: list<string>}
     */
    public function manifest(
        string $subdir = '',
        int $page = 1,
        int $perPage = 200,
        ?string $ext = null,
    ): array {
        $root = $this->paths->repoRoot();
        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        $scanRoot = $subdir === '' ? $root : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdir);

        if (! is_dir($scanRoot)) {
            throw new \InvalidArgumentException('Not a directory.');
        }

        $finder = $this->baseFinder($scanRoot)->files();
        if ($ext !== null && $ext !== '') {
            $ext = ltrim($ext, '.');
            $finder->name('*.'.$ext);
        }

        $all = $this->collectRelativePaths($finder, $root, $this->maxManifestPaths() + 1);
        $total = count($all);
        $truncated = $total > $this->maxManifestPaths();
        if ($truncated) {
            $all = array_slice($all, 0, $this->maxManifestPaths());
            $total = count($all);
        }

        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);

        return [
            'root' => $root,
            'path' => $subdir,
            'paths' => array_values($slice),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'truncated' => $truncated || ($offset + $perPage < $total),
            'skipped_dirs' => $this->skipDirs(),
        ];
    }

    /**
     * Summary for planner context.
     */
    public function repoIndexForPlanner(int $maxSample = 40): string
    {
        try {
            $root = $this->paths->repoRoot();
        } catch (\Throwable $e) {
            return 'Repository root unavailable: '.$e->getMessage();
        }

        $active = $this->paths->activeProject();
        $lines = [
            'Repository root: '.$root,
        ];
        if ($active !== null) {
            $lines[] = 'Active project: '.$active->name.' (host: '.$active->host_path.')';
        }

        $top = [];
        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if ($this->paths->shouldSkipDir($name)) {
                continue;
            }
            $full = $root.DIRECTORY_SEPARATOR.$name;
            $top[] = is_dir($full) ? $name.'/' : $name;
        }
        sort($top);
        $lines[] = 'Top-level entries: '.implode(', ', array_slice($top, 0, 30));

        $counts = [];
        foreach (['app', 'config', 'routes', 'resources', 'database', 'tests'] as $dir) {
            $sub = $root.DIRECTORY_SEPARATOR.$dir;
            if (! is_dir($sub)) {
                continue;
            }
            try {
                $n = count($this->globPaths($dir.'/**/*.php', 5000));
                $counts[$dir] = $n;
            } catch (\Throwable) {
                //
            }
        }
        if ($counts !== []) {
            $parts = [];
            foreach ($counts as $d => $n) {
                $parts[] = $d.': '.$n.' php files (sample cap)';
            }
            $lines[] = 'Directory counts: '.implode('; ', $parts);
        }

        $controllers = $this->controllersFromRoutesFile();
        if ($controllers !== []) {
            $lines[] = 'Controllers from routes/web.php: '.implode(', ', array_slice($controllers, 0, 15));
        }

        try {
            $sample = $this->manifest('', 1, $maxSample)['paths'];
            if ($sample !== []) {
                $lines[] = 'Sample paths: '.implode(', ', array_slice($sample, 0, $maxSample));
            }
        } catch (\Throwable) {
            //
        }

        $lines[] = 'Laravel conventions: controllers at app/Http/Controllers/{Name}.php, config at config/{name}.php';

        return implode("\n", $lines);
    }

    /**
     * Extract PHP/class symbols from text for discovery.
     *
     * @return list<string>
     */
    public function extractSymbolsFromText(string $text): array
    {
        $symbols = [];
        if (preg_match_all('/\b([A-Z][A-Za-z0-9_]*Controller)\b/', $text, $m)) {
            foreach ($m[1] as $s) {
                $symbols[] = $s;
            }
        }
        // Use # delimiters — a literal / inside the character class must not close a /-delimited pattern.
        if (preg_match_all('#\b(config/[a-z0-9_.-]+\.php)\b#i', $text, $m)) {
            foreach ($m[1] as $s) {
                $symbols[] = $s;
            }
        }
        if (preg_match_all('/\b(\.env\.example)\b/', $text, $m)) {
            foreach ($m[1] as $s) {
                $symbols[] = $s;
            }
        }
        if (preg_match_all('/\b(routes\/web\.php)\b/', $text, $m)) {
            foreach ($m[1] as $s) {
                $symbols[] = $s;
            }
        }

        return array_values(array_unique($symbols));
    }

    protected function baseFinder(string $in): Finder
    {
        return Finder::create()
            ->in($in)
            ->ignoreUnreadableDirs()
            ->exclude($this->skipDirs());
    }

    /**
     * @return list<string>
     */
    protected function collectRelativePaths(Finder $finder, string $root, int $limit): array
    {
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $paths = [];

        foreach ($finder as $file) {
            if (count($paths) >= $limit) {
                break;
            }
            $absolute = $file->getRealPath();
            if ($absolute === false) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($absolute, strlen($root))), '/');
            if ($rel !== '') {
                $paths[] = $rel;
            }
        }

        sort($paths);

        return $paths;
    }
}
