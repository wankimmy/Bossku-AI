<?php

namespace App\Services\BosskuAi;

class ContextBudgetGuard
{
    public function __construct(
        protected ModelRoutingConfig $config
    ) {}

    /**
     * @param  array<string, mixed>  $plan  orchestrator JSON
     * @return array<string, mixed> narrowed plan
     */
    public function narrowPlan(array $plan, string $executorProfile): array
    {
        $prof = $this->config->executorProfile($executorProfile);
        $maxFiles = (int) ($prof['max_context_files'] ?? 15);
        $list = $plan['target_file_list'] ?? [];
        if (! is_array($list)) {
            return $plan;
        }
        if (count($list) > $maxFiles) {
            $plan['target_file_list'] = array_slice($list, 0, $maxFiles);
            $plan['context_budget_note'] = 'Target file list truncated to '.$maxFiles.' by context budget guard.';
        }

        return $plan;
    }

    public function estimateTokens(string $text): int
    {
        return (int) max(1, round(strlen($text) / 4));
    }

    /** @param array<string, mixed> $plan */
    public function overBudget(array $plan, int $charBudget): bool
    {
        $encoded = json_encode($plan) ?: '';

        return strlen($encoded) > $charBudget;
    }
}
