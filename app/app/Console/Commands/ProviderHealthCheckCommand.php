<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\LlmProvider;
use App\Services\Llm\ModelRouter;
use Illuminate\Console\Command;

class ProviderHealthCheckCommand extends Command
{
    protected $signature = 'bosskuai:provider-health';

    protected $description = 'Run health checks against registered LLM providers and update DB provider status.';

    public function handle(ModelRouter $router): int
    {
        $providers = LlmProvider::where('is_active', true)->get();

        if ($providers->isEmpty()) {
            $this->warn('No active providers found.');

            return self::SUCCESS;
        }

        $registered = $router->registeredProviders();
        $rows = [];

        foreach ($providers as $provider) {
            $normalized = $router->normalizeProviderSlug((string) $provider->slug);
            $instance = $registered[$normalized] ?? null;

            if ($instance === null) {
                $provider->health_status = 'down';
                $provider->last_health_check_at = now();
                $provider->save();

                $rows[] = [
                    $provider->name,
                    $provider->slug,
                    'down',
                    '—',
                    "No runtime provider registered for slug '{$provider->slug}' (normalized: {$normalized}).",
                ];

                continue;
            }

            try {
                $health = $instance->healthCheck();

                $provider->health_status = $health->status;
                $provider->last_health_check_at = now();
                $provider->save();

                $rows[] = [
                    $provider->name,
                    $provider->slug,
                    $health->status,
                    $health->latencyMs !== null ? $health->latencyMs.'ms' : '—',
                    $health->error ?? '—',
                ];
            } catch (\Throwable $e) {
                $provider->health_status = 'down';
                $provider->last_health_check_at = now();
                $provider->save();

                $rows[] = [
                    $provider->name,
                    $provider->slug,
                    'down',
                    '—',
                    $e->getMessage(),
                ];
            }
        }

        $this->table(
            ['Name', 'Slug', 'Status', 'Latency', 'Error'],
            $rows,
        );

        return self::SUCCESS;
    }
}
