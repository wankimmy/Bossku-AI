<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ProviderCliRegistry
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return [
            $this->entry('claude', ['claude', 'claude-code'], ['--version'], ['resume_flag' => '--resume', 'session_id_flag' => '--session-id']),
            $this->entry('codex', ['codex'], ['--version'], ['resume_flag' => 'resume']),
            $this->entry('cursor', ['cursor-agent', 'cursor'], ['--version']),
            $this->entry('gemini', ['gemini'], ['--version']),
            $this->entry('opencode', ['opencode'], ['--version']),
        ];
    }

    public function find(string $providerId): ?array
    {
        foreach ($this->all() as $entry) {
            if (($entry['id'] ?? '') === $providerId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detectInstalled(): array
    {
        $out = [];
        foreach ($this->all() as $entry) {
            $detected = $this->detectOne($entry);
            if ($detected !== null) {
                $out[] = $detected;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    protected function detectOne(array $entry): ?array
    {
        $commands = is_array($entry['commands'] ?? null) ? $entry['commands'] : [];
        foreach ($commands as $bin) {
            $path = $this->which((string) $bin);
            if ($path === null) {
                continue;
            }

            $version = $this->probeVersion($path, is_array($entry['version_args'] ?? null) ? $entry['version_args'] : ['--version']);

            return array_merge($entry, [
                'installed' => true,
                'command_path' => $path,
                'version' => $version,
            ]);
        }

        return null;
    }

    /**
     * @param  list<string>  $commands
     * @param  list<string>  $versionArgs
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function entry(string $id, array $commands, array $versionArgs, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'display_name' => ucfirst($id),
            'commands' => $commands,
            'version_args' => $versionArgs,
            'installed' => false,
        ], $extra);
    }

    protected function which(string $command): ?string
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $probe = $isWindows
            ? Process::run(['where', $command])
            : Process::run(['which', $command]);

        if (! $probe->successful()) {
            return null;
        }

        $parts = explode("\n", trim($probe->output()));
        $line = trim($parts[0] ?? '');

        return $line !== '' ? $line : null;
    }

    /**
     * @param  list<string>  $versionArgs
     */
    protected function probeVersion(string $path, array $versionArgs): ?string
    {
        $result = Process::timeout(5)->run(array_merge([$path], $versionArgs));
        $text = trim($result->output()."\n".$result->errorOutput());

        return $text !== '' ? Str::limit($text, 120) : null;
    }
}
