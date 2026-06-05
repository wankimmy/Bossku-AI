<?php

namespace App\Services\Project;

/**
 * Detect stack hints for the active project (any registered repo under /workspace).
 * Used in agent prompts so executors use each project's compose layout, not a fixed service name.
 */
class ProjectRuntimeHints
{
    /** @var list<string> */
    private const COMPOSE_FILENAMES = [
        'docker-compose.yml',
        'docker-compose.yaml',
        'compose.yml',
        'compose.yaml',
    ];

    /** @var list<string> */
    private const INFRA_SERVICE_MARKERS = [
        'mysql', 'mariadb', 'postgres', 'postgresql', 'redis', 'memcached', 'mongo', 'mongodb',
        'db', 'database', 'nginx', 'proxy', 'mailhog', 'meilisearch', 'elasticsearch',
        'rabbitmq', 'kafka', 'zookeeper',
    ];

    public function __construct(private readonly ProjectPathResolver $paths) {}

    /**
     * @return array{
     *   framework: string,
     *   compose_file: string|null,
     *   compose_services: list<string>,
     *   suggested_compose_service: string|null,
     *   has_artisan: bool,
     *   has_composer: bool,
     *   has_package_json: bool,
     *   suggested_commands: list<string>,
     * }
     */
    public function summarize(?string $repoRoot = null): array
    {
        try {
            $root = $repoRoot ?? $this->paths->repoRoot();
        } catch (\Throwable) {
            return $this->emptySummary();
        }

        $composeFile = $this->detectComposeFile($root);
        $services = $composeFile !== null ? $this->parseComposeServices($composeFile) : [];
        $suggestedService = $this->suggestedComposeService($services);
        $hasArtisan = is_file($root.'/artisan');
        $hasComposer = is_file($root.'/composer.json');
        $hasPackageJson = is_file($root.'/package.json');
        $framework = $this->detectFramework($root, $hasArtisan, $hasComposer, $hasPackageJson);

        return [
            'framework' => $framework,
            'compose_file' => $composeFile !== null ? basename($composeFile) : null,
            'compose_services' => $services,
            'suggested_compose_service' => $suggestedService,
            'has_artisan' => $hasArtisan,
            'has_composer' => $hasComposer,
            'has_package_json' => $hasPackageJson,
            'suggested_commands' => $this->suggestedCommands(
                $framework,
                $suggestedService,
                $hasArtisan,
                $hasComposer,
                $hasPackageJson,
            ),
        ];
    }

    public function forPrompt(?string $repoRoot = null): string
    {
        $hints = $this->summarize($repoRoot);
        if ($hints['compose_file'] === null && $hints['framework'] === 'unknown' && ! $hints['has_composer']) {
            return '';
        }

        $parts = ['Project runtime (this repo only — do not assume another project\'s layout):'];
        $parts[] = 'Stack: '.$hints['framework'].'.';

        if ($hints['compose_file'] !== null) {
            $serviceList = $hints['compose_services'] === []
                ? 'no services parsed'
                : implode(', ', $hints['compose_services']);
            $parts[] = 'Compose: '.$hints['compose_file'].' (services: '.$serviceList.').';
            if ($hints['suggested_compose_service'] !== null) {
                $svc = $hints['suggested_compose_service'];
                $parts[] = 'For `docker compose exec`, prefer service "'.$svc.'" from this compose file (not a hardcoded name from another repo).';
            } else {
                $parts[] = 'Pick the app service name from this compose file for `docker compose exec <service> …`.';
            }
        } elseif ($hints['has_artisan']) {
            $parts[] = 'No compose file in repo root; run `php artisan …` or `php vendor/bin/phpunit` from the repository root in commands_run.';
        }

        if ($hints['suggested_commands'] !== []) {
            $parts[] = 'Example commands for this project: '.implode('; ', array_slice($hints['suggested_commands'], 0, 4)).'.';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{
     *   framework: string,
     *   compose_file: string|null,
     *   compose_services: list<string>,
     *   suggested_compose_service: string|null,
     *   has_artisan: bool,
     *   has_composer: bool,
     *   has_package_json: bool,
     *   suggested_commands: list<string>,
     * }
     */
    private function emptySummary(): array
    {
        return [
            'framework' => 'unknown',
            'compose_file' => null,
            'compose_services' => [],
            'suggested_compose_service' => null,
            'has_artisan' => false,
            'has_composer' => false,
            'has_package_json' => false,
            'suggested_commands' => [],
        ];
    }

    private function detectComposeFile(string $root): ?string
    {
        foreach (self::COMPOSE_FILENAMES as $name) {
            $path = $root.DIRECTORY_SEPARATOR.$name;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parseComposeServices(string $composePath): array
    {
        $content = @file_get_contents($composePath);
        if ($content === false || $content === '') {
            return [];
        }

        if (! preg_match('/^services:\s*$/m', $content)) {
            return [];
        }

        $services = [];
        if (preg_match_all('/^  ([a-zA-Z][a-zA-Z0-9_-]*):\s*$/m', $content, $matches)) {
            foreach ($matches[1] as $name) {
                if (! in_array($name, $services, true)) {
                    $services[] = $name;
                }
            }
        }

        return $services;
    }

    /**
     * @param  list<string>  $services
     */
    private function suggestedComposeService(array $services): ?string
    {
        foreach ($services as $name) {
            if (! $this->looksLikeInfraService($name)) {
                return $name;
            }
        }

        return $services[0] ?? null;
    }

    private function looksLikeInfraService(string $name): bool
    {
        $lower = strtolower($name);
        foreach (self::INFRA_SERVICE_MARKERS as $marker) {
            if ($lower === $marker || str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function detectFramework(string $root, bool $hasArtisan, bool $hasComposer, bool $hasPackageJson): string
    {
        if ($hasArtisan) {
            return 'laravel';
        }

        if ($hasComposer) {
            $json = @file_get_contents($root.'/composer.json');
            if ($json !== false && (str_contains($json, '"laravel/framework"') || str_contains($json, 'laravel/lumen'))) {
                return 'laravel';
            }

            return 'php';
        }

        if ($hasPackageJson) {
            return 'node';
        }

        return 'unknown';
    }

    /**
     * @return list<string>
     */
    private function suggestedCommands(
        string $framework,
        ?string $composeService,
        bool $hasArtisan,
        bool $hasComposer,
        bool $hasPackageJson,
    ): array {
        $commands = ['git status', 'git diff'];

        if ($composeService !== null) {
            if ($hasArtisan) {
                $commands[] = 'docker compose up -d';
                $commands[] = 'docker compose exec '.$composeService.' php artisan test';
            } else {
                $commands[] = 'docker compose config';
                $commands[] = 'docker compose exec '.$composeService.' sh -c "echo ok"';
            }
        } elseif ($hasArtisan) {
            $commands[] = 'php artisan test';
        } elseif ($hasComposer) {
            $commands[] = 'composer test';
        }

        if ($hasPackageJson && $framework === 'node' && $composeService === null) {
            $commands[] = 'npm test';
        }

        return array_values(array_unique($commands));
    }
}
