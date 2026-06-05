<?php

namespace Database\Factories;

use App\Models\BosskuAi\Run;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class BosskuAiRunFactory extends Factory
{
    protected $model = Run::class;

    public function definition(): array
    {
        return [
            'prompt' => fake()->sentence(),
            'final_output' => null,
            'status' => 'completed',
            'total_latency_ms' => 100,
            'total_token_estimate' => 50,
            'metadata' => [],
        ];
    }
}
