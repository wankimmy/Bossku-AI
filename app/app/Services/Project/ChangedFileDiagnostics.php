<?php

namespace App\Services\Project;

use Illuminate\Support\Facades\Process;

/**
 * Fast per-file diagnostics on just-changed files — the pragmatic, single-shot
 * analogue of opencode's "read diagnostics after edit" LSP step.
 *
 * Bossku-AI's executor is single-shot (it does not hold a long-lived LSP
 * session), so instead of a full language-server client this runs the cheapest
 * authoritative check available for each file type and reports objective
 * failures. A broken edit that produces invalid syntax is caught here and folded
 * into the run's known_issues before the auditor round, driving a revise instead
 * of shipping a file that will not parse.
 *
 * Extend by adding an extension → checker entry in checkFile().
 */
class ChangedFileDiagnostics
{
    public function __construct(private readonly ProjectPathResolver $paths) {}

    /**
     * @param  list<string>  $relativePaths
     * @return list<array{path: string, ok: bool, checker: string, errors: list<string>}>
     */
    public function check(array $relativePaths): array
    {
        $out = [];
        foreach (array_unique($relativePaths) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            $result = $this->checkFile($path);
            if ($result !== null) {
                $out[] = $result;
            }
        }

        return $out;
    }

    /**
     * @return array{path: string, ok: bool, checker: string, errors: list<string>}|null
     *                                                                                    null when no diagnostic applies to this file type
     */
    public function checkFile(string $relativePath): ?array
    {
        try {
            $resolved = $this->paths->resolve($relativePath);
        } catch (\Throwable) {
            return null;
        }
        $absolute = $resolved['absolute'];
        if (! is_file($absolute)) {
            return null;
        }

        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => $this->lintPhp($resolved['relative'], $absolute),
            'json' => $this->lintJson($resolved['relative'], $absolute),
            'yaml', 'yml' => $this->lintYaml($resolved['relative'], $absolute),
            default => null,
        };
    }

    /** @return array{path: string, ok: bool, checker: string, errors: list<string>} */
    protected function lintPhp(string $relative, string $absolute): array
    {
        try {
            $result = Process::timeout(20)->run([PHP_BINARY, '-l', '-d', 'display_errors=0', $absolute]);
        } catch (\Throwable $e) {
            return $this->row($relative, 'php -l', false, [$e->getMessage()]);
        }

        if ($result->successful()) {
            return $this->row($relative, 'php -l', true, []);
        }

        $message = trim($result->errorOutput()) !== '' ? trim($result->errorOutput()) : trim($result->output());
        // php -l echoes the absolute path; keep messages portable across hosts.
        $message = str_replace($absolute, $relative, $message);

        return $this->row($relative, 'php -l', false, $message !== '' ? [$message] : ['PHP syntax error']);
    }

    /** @return array{path: string, ok: bool, checker: string, errors: list<string>} */
    protected function lintJson(string $relative, string $absolute): array
    {
        $raw = (string) file_get_contents($absolute);
        if (trim($raw) === '') {
            return $this->row($relative, 'json', true, []);
        }
        json_decode($raw);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->row($relative, 'json', true, []);
        }

        return $this->row($relative, 'json', false, ['Invalid JSON: '.json_last_error_msg()]);
    }

    /** @return array{path: string, ok: bool, checker: string, errors: list<string>} */
    protected function lintYaml(string $relative, string $absolute): array
    {
        if (! class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            return $this->row($relative, 'yaml', true, []);
        }
        try {
            \Symfony\Component\Yaml\Yaml::parseFile($absolute);

            return $this->row($relative, 'yaml', true, []);
        } catch (\Throwable $e) {
            return $this->row($relative, 'yaml', false, ['Invalid YAML: '.$e->getMessage()]);
        }
    }

    /**
     * @param  list<string>  $errors
     * @return array{path: string, ok: bool, checker: string, errors: list<string>}
     */
    private function row(string $path, string $checker, bool $ok, array $errors): array
    {
        return ['path' => $path, 'ok' => $ok, 'checker' => $checker, 'errors' => $errors];
    }
}
