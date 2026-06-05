<?php

namespace App\Services\Project;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Ensures paths under the workspace mount are writable before auto-apply writes.
 */
class WorkspaceWriteGuard
{
    /** @var array<string, true> */
    private static array $syncedProjectRoots = [];

    /**
     * @throws \RuntimeException
     */
    public function ensureWritable(string $absolutePath, string $projectRoot = ''): void
    {
        $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        $dir = is_dir($absolutePath) ? $absolutePath : dirname($absolutePath);

        if ($dir === '' || $dir === '.') {
            throw new \RuntimeException('Cannot resolve directory for write target.');
        }

        if (! is_dir($dir)) {
            $this->ensureDirectory($dir, $projectRoot);
        }

        if ($this->canWriteToDirectory($dir)) {
            return;
        }

        $this->attemptChmod($dir, 0775);
        if (is_file($absolutePath)) {
            $this->attemptChmod($absolutePath, 0664);
        }

        if ($this->canWriteToDirectory($dir)) {
            return;
        }

        if ($projectRoot !== '') {
            $this->syncProjectPermissions($projectRoot);
            if ($this->canWriteToDirectory($dir)) {
                return;
            }
        }

        $mount = rtrim((string) config('bossku.workspace_mount', '/workspace'), '/');
        $hint = 'Restart the backend container (BOSSKU_WORKSPACE_WRITABLE=true) or ensure the active project under '.$mount.' is mounted with write access.';

        throw new \RuntimeException(
            'Project path is not writable by PHP: '.$absolutePath.'. '.$hint,
        );
    }

    protected function ensureDirectory(string $dir, string $projectRoot): void
    {
        if (is_dir($dir)) {
            return;
        }

        $parent = dirname($dir);
        if ($parent !== $dir && ! is_dir($parent)) {
            $this->ensureDirectory($parent, $projectRoot);
        }

        if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            if ($projectRoot !== '') {
                $this->syncProjectPermissions($projectRoot);
            }
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Failed to create directory: '.$dir);
            }
        }

        $this->attemptChmod($dir, 0775);
    }

    /**
     * Probe with a real write — is_writable() lies on Docker Desktop bind mounts.
     */
    protected function canWriteToDirectory(string $dir): bool
    {
        if (is_writable($dir)) {
            return true;
        }

        $probe = $dir.DIRECTORY_SEPARATOR.'.bossku-writable-'.uniqid('', true);
        $ok = @file_put_contents($probe, '') !== false;
        if ($ok) {
            @unlink($probe);
        }

        return $ok;
    }

    protected function syncProjectPermissions(string $projectRoot): void
    {
        if (! config('bossku.workspace_writable', true)) {
            return;
        }

        $projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);
        if ($projectRoot === '' || isset(self::$syncedProjectRoots[$projectRoot])) {
            return;
        }

        self::$syncedProjectRoots[$projectRoot] = true;

        $script = '/usr/local/bin/bossku-chmod-project';
        if (! is_executable($script)) {
            return;
        }

        $result = Process::timeout(120)->run([
            'sudo', '-n', $script, $projectRoot,
        ]);

        if (! $result->successful()) {
            Log::warning('bossku.workspace_chmod_failed', [
                'project_root' => $projectRoot,
                'exit_code' => $result->exitCode(),
                'output' => $result->errorOutput() ?: $result->output(),
            ]);
            unset(self::$syncedProjectRoots[$projectRoot]);
        }
    }

    protected function attemptChmod(string $path, int $mode): void
    {
        if (! file_exists($path)) {
            return;
        }

        @chmod($path, $mode);
    }
}
