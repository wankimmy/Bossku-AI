<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\LlmProvider;
use Illuminate\Console\Command;

class ProviderHealthCheckCommand extends Command
{
    protected $signature = 'bosskuai:provider-health';

    protected $description = 'Run health checks against all active LLM providers and update their status.';

    public function handle(): int
    {
        $providers = LlmProvider::where('is_active', true)->get();

        if ($providers->isEmpty()) {
            $this->warn('No active providers found.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($providers as $provider) {
            $binding = "llm.provider.{$provider->slug}";

            try {
                /** @var \App\Services\Llm\Contracts\LlmProviderInterface $instance */
                $instance = app()->make($binding);
                $health   = $instance->healthCheck();

                $provider->health_status       = $health->status;
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
                $provider->health_status       = 'down';
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
