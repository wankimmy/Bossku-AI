<?php

namespace App\Services\Project;

use App\Support\StringCoercion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Runs allowlisted project commands (git, docker compose, php, composer, npm/yarn/pnpm) in the active repo
 * or another directory under the Docker workspace mount.
 */
class ProjectCommandRunner
{
    /** @var list<string> */
    private const GIT_PREFIXES = [
        'git restore',
        'git checkout',
        'git status',
        'git diff',
    ];

    /** @var list<string> */
    private const SHELL_PREFIXES = [
        'docker compose',
        'php artisan',
        'php vendor/bin/phpunit',
        'composer test',
        'composer install',
        'composer run',
        'composer dump-autoload',
    ];

    /** @var list<string> */
    private const PACKAGE_MANAGER_PREFIXES = [
        'npm ',
        'npm install',
        'npm uninstall',
        'npm run',
        'npm ci',
        'npm test',
        'npm audit',
        'npm ls',
        'npm list',
        'npx ',
        'yarn ',
        'yarn install',
        'yarn run',
        'yarn add',
        'yarn remove',
        'pnpm ',
        'pnpm install',
        'pnpm run',
        'pnpm add',
        'pnpm remove',
    ];

    /** @var list<string> */
    private const FORBIDDEN_SUBSTRINGS = [
        ';', '|', '&&', '||', '`', '$(',
        'git push', 'git reset', 'git clean', 'git rebase', 'git merge',
        'git commit', 'git add', 'git stash',
        'docker run', 'docker build', 'docker system', 'docker volume',
        'npm publish', 'yarn publish', 'pnpm publish',
        'rm -rf', 'mkfs', 'dd ',
    ];

    public function __construct(private readonly ProjectPathResolver $paths) {}

    public function enabled(): bool
    {
        return (bool) config('bossku.auto_execute_project_commands', config('bossku.auto_execute_git_commands', true));
    }

    public function dockerComposeEnabled(): bool
    {
        if (! (bool) config('bossku.allow_docker_compose_commands', true)) {
            return false;
        }

        return is_readable('/var/run/docker.sock');
    }

    public function packageManagersEnabled(): bool
    {
        return (bool) config('bossku.allow_package_manager_commands', true);
    }

    /**
     * @param  list<mixed>  $commandsRun
     * @return array{
     *   executed: list<array{command: string, exit_code: int, stdout: string, stderr: string, ok: bool, skipped?: bool, reason?: string}>,
     *   post_git_status: string|null,
     *   ran_restore: bool,
     * }
     */
    public function runAllowedGitCommands(array $commandsRun): array
    {
        return $this->runAllowedProjectCommands($commandsRun);
    }

    /**
     * @param  list<mixed>  $commandsRun
     * @return array{
     *   executed: list<array{command: string, exit_code: int, stdout: string, stderr: string, ok: bool, skipped?: bool, reason?: string}>,
     *   post_git_status: string|null,
     *   ran_restore: bool,
     * }
     */
    public function runAllowedProjectCommands(array $commandsRun, ?ExecutionContext $context = null): array
    {
        $executed = [];
        $ranRestore = false;

        if (! $this->enabled()) {
            foreach ($this->normalizeCommandEntries($commandsRun) as $entry) {
                $executed[] = $this->skippedRow($entry['command'], 'auto_execute_project_commands disabled');
            }

            return [
                'executed' => $executed,
                'post_git_status' => null,
                'ran_restore' => false,
            ];
        }

        $defaultCwd = $context?->repoRoot ?? $this->repoRoot();

        foreach ($this->normalizeCommandEntries($commandsRun) as $entry) {
            $command = $entry['command'];
            $cwd = $entry['cwd'] ?? $defaultCwd;

            $cwdError = $this->validateWorkingDirectory($cwd, $defaultCwd);
            if ($cwdError !== null) {
                $executed[] = $this->skippedRow($command, $cwdError);

                continue;
            }

            $validation = $this->validateCommand($command, $defaultCwd, $context);
            if ($validation !== null) {
                $executed[] = $this->skippedRow($command, $validation);

                continue;
            }

            if ($this->isRestoreCommand($command)) {
                $ranRestore = true;
            }

            $executed[] = $this->runOne($command, $cwd);
        }

        $postGitStatus = null;
        if ($executed !== [] && $this->ranAnyGitCommand($executed)) {
            $postGitStatus = $this->captureGitStatus($defaultCwd);
        }

        return [
            'executed' => $executed,
            'post_git_status' => $postGitStatus,
            'ran_restore' => $ranRestore,
        ];
    }

    public function captureGitStatus(?string $cwd = null): ?string
    {
        try {
            $cwd = $cwd ?? $this->repoRoot();
        }
        catch (\Throwable) {
            return null;
        }

        $result = $this->runOne('git status --porcelain', $cwd);

        return $result['ok'] ? trim($result['stdout']) : null;
    }

    /**
     * @return list<string>
     */
    public function normalizeCommandList(array $commandsRun): array
    {
        return array_map(
            static fn (array $entry): string => $entry['command'],
            $this->normalizeCommandEntries($commandsRun),
        );
    }

    /**
     * @param  list<mixed>  $commandsRun
     * @return list<array{command: string, cwd: string|null}>
     */
    public function normalizeCommandEntries(array $commandsRun): array
    {
        $out = [];
        foreach ($commandsRun as $item) {
            if (is_array($item)) {
                $command = StringCoercion::toString($item['command'] ?? null, '');
                $cwdRaw = $item['cwd'] ?? $item['working_directory'] ?? null;
                $cwd = is_string($cwdRaw) ? trim($cwdRaw) : null;
                if ($cwd === '') {
                    $cwd = null;
                }
            } else {
                $command = StringCoercion::toString($item, '');
                $cwd = null;
            }
            $command = trim($command);
            if ($command !== '') {
                $out[] = ['command' => $command, 'cwd' => $cwd];
            }
        }

        return $out;
    }

    public function validateCommand(string $command, ?string $repoRoot = null, ?ExecutionContext $context = null): ?string
    {
        $command = trim(preg_replace('/\s+/', ' ', $command) ?? $command);
        if ($command === '') {
            return 'Empty command.';
        }

        $repoRoot = $repoRoot ?? $context?->repoRoot ?? $this->repoRoot();

        $lower = strtolower($command);
        foreach (self::FORBIDDEN_SUBSTRINGS as $forbidden) {
            if (str_contains($lower, strtolower($forbidden))) {
                return 'Command blocked: contains disallowed token.';
            }
        }

        if (str_starts_with($lower, 'docker compose')) {
            if (! $this->dockerComposeEnabled()) {
                return 'Command blocked: docker compose requires /var/run/docker.sock on the Bossku backend (local dev).';
            }

            $composeError = $this->validateComposePaths($command, $repoRoot);
            if ($composeError !== null) {
                return $composeError;
            }

            return $this->validateEmbeddedPaths($command, $repoRoot);
        }

        foreach (self::GIT_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        foreach (self::SHELL_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return $this->validateEmbeddedPaths($command, $repoRoot);
            }
        }

        if ($this->isPackageManagerCommand($lower)) {
            if (! $this->packageManagersEnabled()) {
                return 'Command blocked: package manager commands are disabled (BOSSKU_ALLOW_PACKAGE_MANAGER_COMMANDS=false).';
            }

            return $this->validateEmbeddedPaths($command, $repoRoot);
        }

        foreach ($this->extraCommandPrefixes() as $prefix) {
            if (str_starts_with($lower, strtolower($prefix))) {
                return $this->validateEmbeddedPaths($command, $repoRoot);
            }
        }

        return 'Command blocked: only git, docker compose, php artisan, phpunit, composer, and npm/yarn/pnpm commands are allowed.';
    }

    protected function isPackageManagerCommand(string $lower): bool
    {
        foreach (self::PACKAGE_MANAGER_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return (bool) preg_match('/^(npm|npx|yarn|pnpm)\b/', $lower);
    }

    /**
     * @return list<string>
     */
    protected function extraCommandPrefixes(): array
    {
        $raw = config('bossku.project_command_extra_prefixes', []);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode('|', $raw)));
        }

        return is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : [];
    }

    protected function validateWorkingDirectory(string $cwd, string $repoRoot): ?string
    {
        $cwd = trim($cwd);
        if ($cwd === '') {
            return 'Empty working directory.';
        }

        $real = realpath($cwd);
        if ($real === false || ! is_dir($real)) {
            return 'Command blocked: working directory does not exist or is not mounted in the container.';
        }

        if ($this->pathIsAllowedRoot($real, $repoRoot)) {
            return null;
        }

        return 'Command blocked: working directory must be inside the active project or the workspace mount ('.$this->workspaceMount().').';
    }

    protected function validateEmbeddedPaths(string $command, string $repoRoot): ?string
    {
        if (! (bool) config('bossku.allow_workspace_command_paths', true)) {
            return null;
        }

        $patterns = [
            '/(?:--prefix|-C)\s+([^\s]+)/i',
            '/(?:--cwd|--dir)\s+([^\s]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $command, $matches)) {
                continue;
            }
            foreach ($matches[1] as $path) {
                $error = $this->validateReferencedPath(trim($path, '\'"'), $repoRoot);
                if ($error !== null) {
                    return $error;
                }
            }
        }

        return null;
    }

    protected function validateReferencedPath(string $path, string $repoRoot): ?string
    {
        if ($path === '' || $path === '.') {
            return null;
        }

        $absolute = str_starts_with($path, '/')
            ? $path
            : $repoRoot.'/'.ltrim($path, '/');

        $real = realpath($absolute);
        if ($real === false) {
            return 'Command blocked: path not found under project or workspace: '.$path;
        }

        if ($this->pathIsAllowedRoot($real, $repoRoot)) {
            return null;
        }

        return 'Command blocked: path must be inside the active project or workspace mount ('.$this->workspaceMount().').';
    }

    protected function pathIsAllowedRoot(string $realPath, string $repoRoot): bool
    {
        $repoReal = realpath($repoRoot) ?: $repoRoot;
        if (str_starts_with($realPath, $repoReal)) {
            return true;
        }

        if (! (bool) config('bossku.allow_workspace_command_paths', true)) {
            return false;
        }

        $mount = $this->workspaceMount();
        $mountReal = realpath($mount);

        return $mountReal !== false
            && is_dir($mountReal)
            && str_starts_with($realPath, $mountReal);
    }

    protected function workspaceMount(): string
    {
        return rtrim((string) config('bossku.workspace_mount', '/workspace'), '/');
    }

    protected function validateComposePaths(string $command, string $repoRoot): ?string
    {
        if (! preg_match_all('/-f\s+([^\s]+)/', $command, $matches)) {
            return null;
        }

        foreach ($matches[1] as $file) {
            $error = $this->validateReferencedPath(trim($file, '\'"'), $repoRoot);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    protected function repoRoot(?ExecutionContext $context = null): string
    {
        return $context?->repoRoot ?? $this->paths->repoRoot();
    }

    /**
     * @return array{command: string, exit_code: int, stdout: string, stderr: string, ok: bool}
     */
    protected function runOne(string $command, string $cwd): array
    {
        $timeout = (int) config('bossku.project_command_timeout_seconds', config('bossku.git_command_timeout_seconds', 120));
        $maxOutput = (int) config('bossku.project_command_max_output_chars', 32768);

        try {
            $result = Process::timeout($timeout)->path($cwd)->run($command);
        }
        catch (\Throwable $e) {
            return [
                'command' => $command,
                'exit_code' => -1,
                'stdout' => '',
                'stderr' => $e->getMessage(),
                'ok' => false,
            ];
        }

        return [
            'command' => $command,
            'exit_code' => $result->exitCode() ?? -1,
            'stdout' => $this->truncateOutput($result->output(), $maxOutput),
            'stderr' => $this->truncateOutput($result->errorOutput(), $maxOutput),
            'ok' => $result->successful(),
        ];
    }

    protected function truncateOutput(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 20)."\n…(output truncated)";
    }

    /**
     * @param  list<array<string, mixed>>  $executed
     */
    protected function ranAnyGitCommand(array $executed): bool
    {
        foreach ($executed as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cmd = strtolower((string) ($row['command'] ?? ''));
            if (str_starts_with($cmd, 'git ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{command: string, exit_code: int, stdout: string, stderr: string, ok: bool, skipped: bool, reason: string}
     */
    protected function skippedRow(string $command, string $reason): array
    {
        return [
            'command' => $command,
            'exit_code' => -1,
            'stdout' => '',
            'stderr' => $reason,
            'ok' => false,
            'skipped' => true,
            'reason' => $reason,
        ];
    }

    protected function isRestoreCommand(string $command): bool
    {
        $lower = strtolower(trim($command));

        return str_starts_with($lower, 'git restore')
            || str_starts_with($lower, 'git checkout');
    }

    public function logDockerAvailability(): void
    {
        if (! (bool) config('bossku.allow_docker_compose_commands', true) || $this->dockerComposeEnabled()) {
            return;
        }

        $key = 'bossku.docker_sock_warned';
        if (cache()->has($key)) {
            return;
        }

        cache()->put($key, true, now()->addHour());
        Log::warning('bossku.docker_sock_unavailable', [
            'hint' => 'Mount /var/run/docker.sock on the Bossku backend service to run docker compose commands.',
        ]);
    }
}
