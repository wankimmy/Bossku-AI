<?php

namespace App\Services\Llm;

class ModelRegistry
{
    private static array $pricingTable = [
        'claude-opus'   => ['input_per_1m' => 15.0,  'output_per_1m' => 75.0],
        'claude-sonnet' => ['input_per_1m' => 3.0,   'output_per_1m' => 15.0],
        'claude-haiku'  => ['input_per_1m' => 0.25,  'output_per_1m' => 1.25],
        'gpt-4o'        => ['input_per_1m' => 5.0,   'output_per_1m' => 15.0],
        'gpt-4'         => ['input_per_1m' => 30.0,  'output_per_1m' => 60.0],
        'gpt-3.5'       => ['input_per_1m' => 0.5,   'output_per_1m' => 1.5],
        // Ollama Cloud aliases — zero default cost until configured; still "known" for reporting.
        'kimi-k2'       => ['input_per_1m' => 0.0,   'output_per_1m' => 0.0],
        'glm-5'         => ['input_per_1m' => 0.0,   'output_per_1m' => 0.0],
        'qwen3'         => ['input_per_1m' => 0.0,   'output_per_1m' => 0.0],
        'deepseek'      => ['input_per_1m' => 0.0,   'output_per_1m' => 0.0],
    ];

    public static function pricing(string $model): array
    {
        $lower = strtolower($model);

        foreach (self::$pricingTable as $key => $price) {
            if (str_contains($lower, $key)) {
                return $price;
            }
        }

        return ['input_per_1m' => 0.0, 'output_per_1m' => 0.0];
    }

    public static function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::pricing($model);

        return ($inputTokens / 1_000_000) * $pricing['input_per_1m']
             + ($outputTokens / 1_000_000) * $pricing['output_per_1m'];
    }

    public static function hasKnownPricing(string $model): bool
    {
        $lower = strtolower($model);

        foreach (array_keys(self::$pricingTable) as $key) {
            if (str_contains($lower, $key)) {
                return true;
            }
        }

        return false;
    }
}
