<?php

namespace App\Services\Project;

use Illuminate\Support\Facades\File;

class ProjectSkillsBootstrapService
{
    /** @var list<string> */
    private const COPY_PATHS = [
        'skill-index.json',
        'ai-assistant/skills',
        'ai-assistant/references',
    ];

    public function __construct(
        private readonly ProjectPathResolver $paths,
        private readonly ProjectService $projects,
    ) {}

    /**
     * Copy Bossku-AI toolkit skills into the active project repo.
     *
     * @return array{
     *   message: string,
     *   project_id: string,
     *   project_name: string,
     *   target_root: string,
     *   toolkit_root: string,
     *   copied: list<string>
     * }
     */
    public function bootstrapIntoActiveProject(): array
    {
        $active = $this->projects->activeProject();
        if ($active === null) {
            throw new \InvalidArgumentException('No active project. Register and activate a project under Project → Paths first.');
        }

        $targetRoot = $this->paths->repoRoot();
        $toolkitRoot = $this->toolkitRepoRoot();

        if (! $this->hasValidSkillIndex($toolkitRoot)) {
            throw new \RuntimeException('Bossku-AI toolkit has no valid skill-index.json at '.$toolkitRoot);
        }

        $copied = [];
        foreach (self::COPY_PATHS as $relative) {
            $source = $toolkitRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! file_exists($source)) {
                continue;
            }

            $dest = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_dir($source)) {
                $this->copyDirectoryMerge($source, $dest);
            }
            else {
                File::ensureDirectoryExists(dirname($dest));
                File::copy($source, $dest);
            }

            $copied[] = $relative;
        }

        if (! $this->hasValidSkillIndex($targetRoot)) {
            throw new \RuntimeException('Skills were copied but skill-index.json is still invalid in the active project.');
        }

        return [
            'message' => 'BosskuAI skills installed into the active project.',
            'project_id' => $active->id,
            'project_name' => $active->name,
            'target_root' => $targetRoot,
            'toolkit_root' => $toolkitRoot,
            'copied' => $copied,
        ];
    }

    private function toolkitRepoRoot(): string
    {
        $fallback = (string) config('bossku.repo_root');
        $real = realpath($fallback);

        return $real !== false ? $real : $fallback;
    }

    private function hasValidSkillIndex(string $root): bool
    {
        $path = $root.DIRECTORY_SEPARATOR.'skill-index.json';
        if (! is_readable($path)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) && is_array($decoded['skills'] ?? null);
    }

    private function copyDirectoryMerge(string $source, string $dest): void
    {
        if (! is_dir($dest)) {
            File::copyDirectory($source, $dest);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $target = $dest.DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($target);

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($item->getPathname(), $target);
        }
    }
}
