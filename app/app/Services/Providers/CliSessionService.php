<?php

namespace App\Services\Providers;

use App\Jobs\PollCliSessionJob;
use App\Models\BosskuAi\CliSession;
use App\Models\BosskuAi\Run;
use App\Services\Project\RunExecutionContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CliSessionService
{
    public function __construct(
        private readonly ProviderCliRegistry $registry,
        private readonly RunExecutionContext $runContext,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function start(Run $run, string $providerId, string $prompt, array $options = []): CliSession
    {
        if (! (bool) config('bossku.cli_providers_enabled', true)) {
            throw new \RuntimeException('CLI provider sessions are disabled.');
        }

        $entry = $this->registry->find($providerId);
        if ($entry === null) {
            throw new \InvalidArgumentException('Unknown provider: '.$providerId);
        }

        $detected = $this->registry->detectInstalled();
        $match = collect($detected)->firstWhere('id', $providerId);
        if ($match === null) {
            throw new \RuntimeException('Provider CLI not installed: '.$providerId);
        }

        $cwd = $this->resolveCwd($run);
        $command = $this->buildLaunchCommand($match, $prompt, $options);
        $defaultAsync = (bool) config('bossku.cli_session_async_default', true);
        if (PHP_OS_FAMILY === 'Windows' && ! array_key_exists('async', $options)) {
            $defaultAsync = (bool) config('bossku.cli_session_async_default_windows', false);
        }
        $async = (bool) ($options['async'] ?? $defaultAsync);

        $session = CliSession::query()->create([
            'run_id' => $run->getKey(),
            'provider' => $providerId,
            'status' => 'running',
            'command' => $command,
            'metadata' => [
                'cwd' => $cwd,
                'async' => $async,
                'prompt_preview' => mb_substr($prompt, 0, 500),
            ],
            'started_at' => now(),
        ]);

        if ($async) {
            return $this->startAsync($session, $cwd, $command, $options);
        }

        return $this->runBlocking($session, $cwd, $command, $options);
    }

    public function refreshRunningSession(CliSession $session): bool
    {
        if ($session->status !== 'running') {
            return false;
        }

        $meta = is_array($session->metadata) ? $session->metadata : [];
        $pid = (int) ($meta['pid'] ?? 0);
        if ($pid > 0 && $this->isProcessRunning($pid)) {
            return true;
        }

        $outputFile = (string) ($meta['output_file'] ?? '');
        if ($outputFile !== '' && is_file($outputFile)) {
            $stdout = (string) @file_get_contents($outputFile);
            $session->update([
                'status' => 'completed',
                'ended_at' => now(),
                'metadata' => array_merge($meta, [
                    'stdout_preview' => mb_substr($stdout, 0, 4000),
                    'exit_code' => 0,
                ]),
            ]);

            return false;
        }

        $session->update([
            'status' => 'failed',
            'ended_at' => now(),
            'metadata' => array_merge($meta, [
                'error' => 'CLI process ended without captured output.',
            ]),
        ]);

        return false;
    }

    public function show(string $sessionId): ?CliSession
    {
        $session = CliSession::query()->find($sessionId);
        if ($session === null) {
            return null;
        }

        if ($session->status === 'running') {
            $this->refreshRunningSession($session);

            return $session->refresh();
        }

        return $session;
    }

    public function recordHookEvent(string $sessionId, string $event, array $payload = []): ?CliSession
    {
        $session = CliSession::query()->find($sessionId);
        if ($session === null) {
            return null;
        }

        $meta = is_array($session->metadata) ? $session->metadata : [];
        $hooks = is_array($meta['hooks'] ?? null) ? $meta['hooks'] : [];
        $hooks[] = ['event' => $event, 'payload' => $payload, 'at' => now()->toIso8601String()];
        $hooks = array_slice($hooks, -50);
        $session->update([
            'metadata' => array_merge($meta, ['hooks' => $hooks]),
            'external_session_id' => (string) ($payload['session_id'] ?? $session->external_session_id),
        ]);

        if ($event === 'completed' || $event === 'failed') {
            $session->update([
                'status' => $event === 'completed' ? 'completed' : 'failed',
                'ended_at' => now(),
            ]);
        }

        return $session->refresh();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function startAsync(CliSession $session, string $cwd, string $command, array $options): CliSession
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->runBlocking($session, $cwd, $command, array_merge($options, [
                'timeout' => (int) ($options['timeout'] ?? 600),
            ]));
        }

        $outputFile = storage_path('app/cli-sessions/'.$session->getKey().'.log');
        @mkdir(dirname($outputFile), 0775, true);

        $shellCommand = $command.' > '.escapeshellarg($outputFile).' 2>&1 & echo $!';
        try {
            $probe = Process::path($cwd)->timeout(10)->run(['sh', '-c', $shellCommand]);
            $pid = (int) trim($probe->output());
            $session->update([
                'metadata' => array_merge(is_array($session->metadata) ? $session->metadata : [], [
                    'pid' => $pid > 0 ? $pid : null,
                    'output_file' => $outputFile,
                ]),
            ]);
            PollCliSessionJob::dispatch((string) $session->getKey())->delay(now()->addSeconds(3));
        } catch (\Throwable $e) {
            $session->update([
                'status' => 'failed',
                'ended_at' => now(),
                'metadata' => array_merge(is_array($session->metadata) ? $session->metadata : [], [
                    'error' => $e->getMessage(),
                ]),
            ]);
            Log::warning('bossku.cli_session.async_start_failed', [
                'session_id' => $session->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return $session->refresh();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function runBlocking(CliSession $session, string $cwd, string $command, array $options): CliSession
    {
        try {
            $result = Process::path($cwd)->timeout((int) ($options['timeout'] ?? 300))->run($command);
            $session->update([
                'status' => $result->successful() ? 'completed' : 'failed',
                'ended_at' => now(),
                'metadata' => array_merge(is_array($session->metadata) ? $session->metadata : [], [
                    'exit_code' => $result->exitCode(),
                    'stdout_preview' => mb_substr($result->output(), 0, 4000),
                    'stderr_preview' => mb_substr($result->errorOutput(), 0, 2000),
                ]),
            ]);
        } catch (\Throwable $e) {
            $session->update([
                'status' => 'failed',
                'ended_at' => now(),
                'metadata' => array_merge(is_array($session->metadata) ? $session->metadata : [], [
                    'error' => $e->getMessage(),
                ]),
            ]);
            Log::warning('bossku.cli_session.failed', [
                'run_id' => $session->run_id,
                'provider' => $session->provider,
                'error' => $e->getMessage(),
            ]);
        }

        return $session->refresh();
    }

    protected function resolveCwd(Run $run): string
    {
        $this->runContext->bind((string) $run->getKey());
        try {
            return $this->runContext->executionContext(app(\App\Services\Project\ProjectPathResolver::class))->repoRoot;
        } finally {
            $this->runContext->clear();
        }
    }

    protected function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $check = Process::run(['sh', '-c', 'kill -0 '.(int) $pid.' 2>/dev/null']);

        return $check->successful();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $options
     */
    protected function buildLaunchCommand(array $entry, string $prompt, array $options): string
    {
        $bin = (string) ($entry['command_path'] ?? $entry['commands'][0] ?? 'true');
        $escapedPrompt = escapeshellarg($prompt);

        return match ($entry['id'] ?? '') {
            'claude' => $bin.' -p '.$escapedPrompt,
            'codex' => $bin.' exec '.$escapedPrompt,
            default => $bin.' '.$escapedPrompt,
        };
    }
}
