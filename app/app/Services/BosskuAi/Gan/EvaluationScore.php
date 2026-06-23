<?php

namespace App\Services\BosskuAi\Gan;

/**
 * The result of evaluating a generated artifact against a rubric. Ported
 * from ECC's GAN evaluator output. The score is the weighted average (0-10);
 * passed is whether it met the threshold; breakdown shows per-dimension
 * contributions; missingDimensions flags dimensions the evaluator didn't score.
 *
 * The evaluator must provide a "how to fix" for every dimension scoring <7
 * (the "Be Ruthlessly Strict" calibration: no critique without a fix).
 */
final readonly class EvaluationScore
{
    /**
     * @param  float  $total  weighted average (0-10)
     * @param  float  $threshold  the pass threshold
     * @param  bool  $passed  total >= threshold
     * @param  array<string, array{raw: float, weight: float, contribution: float}>  $breakdown
     * @param  list<string>  $missingDimensions  dimensions the evaluator didn't score
     */
    public function __construct(
        public float $total,
        public float $threshold,
        public bool $passed,
        public array $breakdown,
        public array $missingDimensions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'threshold' => $this->threshold,
            'passed' => $this->passed,
            'breakdown' => $this->breakdown,
            'missing_dimensions' => $this->missingDimensions,
        ];
    }
}