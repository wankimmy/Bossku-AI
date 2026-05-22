<?php

namespace App\Services\Project;

use App\Support\StringCoercion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Runs allowlisted project commands (git, docker compose, php artisan, tests) in the active repo root.
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
    ];

    /** @var list<string> */
    private const FORBIDDEN_SUBSTRINGS = [
        ';', '|', '&&', '||', '`', '$(',
        'git push', 'git reset', 'git clean', 'git rebase', 'git merge',
        'git commit', 'git add', 'git stash',
        'docker run', 'docker build', 'docker system', 'docker volume',
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
    public function runAllowedProjectCommands(array $commandsRun): array
    {
        $executed = [];
        $ranRestore = false;

        if (! $this->enabled()) {
            foreach ($this->normalizeCommandList($commandsRun) as $command) {
                $executed[] = $this->skippedRow($command, 'auto_execute_project_commands disabled');
            }

            return [
                'executed' => $executed,
                'post_git_status' => null,
                'ran_restore' => false,
            ];
        }

        $cwd = $this->repoRoot();

        foreach ($this->normalizeCommandList($commandsRun) as $command) {
            $validation = $this->validateCommand($command, $cwd);
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
            $postGitStatus = $this->captureGitStatus($cwd);
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
        $out = [];
        foreach ($commandsRun as $item) {
            $command = is_array($item)
                ? StringCoercion::toString($item['command'] ?? null, '')
                : StringCoercion::toString($item, '');
            $command = trim($command);
            if ($command !== '') {
                $out[] = $command;
            }
        }

        return $out;
    }

    public function validateCommand(string $command, ?string $repoRoot = null): ?string
    {
        $command = trim(preg_replace('/\s+/', ' ', $command) ?? $command);
        if ($command === '') {
            return 'Empty command.';
        }

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

            return $this->validateComposePaths($command, $repoRoot ?? $this->repoRoot());
        }

        foreach (self::GIT_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        foreach (self::SHELL_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        return 'Command blocked: only git, docker compose, php artisan, phpunit, and composer test/install are allowed.';
    }

    protected function validateComposePaths(string $command, string $repoRoot): ?string
    {
        if (! preg_match_all('/-f\s+([^\s]+)/', $command, $matches)) {
            return null;
        }

        $repoReal = realpath($repoRoot) ?: $repoRoot;
        foreach ($matches[1] as $file) {
            $file = trim($file, '\'"');
            if ($file === '') {
                continue;
            }
            $absolute = str_starts_with($file, '/')
                ? $file
                : $repoRoot.'/'.ltrim($file, '/');
            $real = realpath($absolute);
            if ($real === false) {
                return 'Command blocked: compose file not found under project root.';
            }
            if (! str_starts_with($real, $repoReal)) {
                return 'Command blocked: compose file must be inside the active project.';
            }
        }

        return null;
    }

    protected function repoRoot(): string
    {
        return $this->paths->repoRoot();
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
        if ((bool) config('bossku.allow_docker_compose_commands', true) && ! $this->dockerComposeEnabled()) {
            Log::warning('bossku.docker_sock_unavailable', [
                'hint' => 'Mount /var/run/docker.sock on the Bossku backend service to run docker compose commands.',
            ]);
        }
    }
}
