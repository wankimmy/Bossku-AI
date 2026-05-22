<?php

namespace App\Services\Project;

/**
 * Detect when the active repository is the Bossku-AI orchestrator itself (not only a user app).
 */
class BosskuToolkitDetector
{
    public function isBosskuToolkitRepository(?string $repoRoot = null): bool
    {
        try {
            $root = $repoRoot ?? app(ProjectPathResolver::class)->repoRoot();
        } catch (\Throwable) {
            $root = (string) config('bossku.repo_root');
        }

        $real = realpath($root);
        if ($real === false || ! is_dir($real)) {
            return false;
        }

        $toolkitRoot = realpath((string) config('bossku.repo_root'));
        if ($toolkitRoot !== false && $real === $toolkitRoot) {
            return true;
        }

        return is_file($real.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Orchestrator'.DIRECTORY_SEPARATOR.'OrchestratorService.php')
            && is_file($real.DIRECTORY_SEPARATOR.'docker-compose.yml')
            && is_dir($real.DIRECTORY_SEPARATOR.'web');
    }
}
