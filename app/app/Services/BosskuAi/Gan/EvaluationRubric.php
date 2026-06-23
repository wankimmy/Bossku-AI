<?php

namespace App\Services\BosskuAi\Gan;

/**
 * A weighted evaluation rubric for the GAN generator-evaluator harness.
 * Ported from ECC's gan-style-harness skill. The evaluator scores each
 * dimension 0-10; the weighted average must meet or exceed the threshold
 * (default 7.0) for the work to pass. "Be Ruthlessly Strict" calibration:
 * every issue must have a "how to fix", and a 7 is "meets bar", not "good".
 *
 * Dimensions (from ECC):
 * - design (0.3): visual hierarchy, spacing, typography, color, consistency
 * - originality (0.2): reaches past LLM defaults (no AI-purple gradients)
 * - craft (0.3): code quality, semantic HTML, accessibility, responsive
 * - functionality (0.2): does it work, edge cases handled, no broken states
 */
final class EvaluationRubric
{
    /** @var array<string, float> dimension => weight (must sum to 1.0) */
    private array $weights;

    /**
     * @param  array<string, float>  $weights  dimension => weight
     * @param  float  $threshold  minimum weighted average to pass (0-10 scale)
     */
    public function __construct(array $weights = [], public readonly float $threshold = 7.0)
    {
        $this->weights = $weights !== [] ? $weights : [
            'design' => 0.3,
            'originality' => 0.2,
            'craft' => 0.3,
            'functionality' => 0.2,
        ];

        $sum = array_sum($this->weights);
        if (abs($sum - 1.0) > 0.001) {
            throw new \InvalidArgumentException("Rubric weights must sum to 1.0, got: {$sum}");
        }
    }

    /** @return array<string, float> */
    public function weights(): array
    {
        return $this->weights;
    }

    public function threshold(): float
    {
        return $this->threshold;
    }

    /**
     * Score a set of dimension scores against this rubric.
     *
     * @param  array<string, float>  $scores  dimension => 0-10 score
     * @return EvaluationScore the weighted average, pass/fail, and per-dimension breakdown
     */
    public function score(array $scores): EvaluationScore
    {
        $weighted = 0.0;
        $breakdown = [];

        foreach ($this->weights as $dim => $weight) {
            $raw = $scores[$dim] ?? 0.0;
            $clamped = max(0.0, min(10.0, $raw));
            $contribution = $clamped * $weight;
            $weighted += $contribution;
            $breakdown[$dim] = [
                'raw' => $clamped,
                'weight' => $weight,
                'contribution' => $contribution,
            ];
        }

        $weighted = round($weighted, 2);
        $passed = $weighted >= $this->threshold;

        return new EvaluationScore(
            total: $weighted,
            threshold: $this->threshold,
            passed: $passed,
            breakdown: $breakdown,
            missingDimensions: array_values(array_diff(array_keys($this->weights), array_keys($scores))),
        );
    }
}