<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Gan\EvaluationRubric;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the GAN generator-evaluator rubric. Proves: weighted scoring,
 * threshold pass/fail, dimension clamping, missing-dimension detection, and
 * the "weights must sum to 1.0" invariant.
 */
class GanRubricTest extends TestCase
{
    #[Test]
    public function default_weights_sum_to_one(): void
    {
        $rubric = new EvaluationRubric;

        $this->assertEqualsWithDelta(1.0, array_sum($rubric->weights()), 0.001);
    }

    #[Test]
    public function perfect_scores_pass(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 10.0,
            'originality' => 10.0,
            'craft' => 10.0,
            'functionality' => 10.0,
        ]);

        $this->assertTrue($score->passed);
        $this->assertSame(10.0, $score->total);
    }

    #[Test]
    public function zero_scores_fail(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 0.0,
            'originality' => 0.0,
            'craft' => 0.0,
            'functionality' => 0.0,
        ]);

        $this->assertFalse($score->passed);
        $this->assertSame(0.0, $score->total);
    }

    #[Test]
    public function weighted_average_is_correct(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 8.0,        // 8 * 0.3 = 2.4
            'originality' => 5.0,   // 5 * 0.2 = 1.0
            'craft' => 9.0,         // 9 * 0.3 = 2.7
            'functionality' => 7.0, // 7 * 0.2 = 1.4
        ]);

        // 2.4 + 1.0 + 2.7 + 1.4 = 7.5
        $this->assertSame(7.5, $score->total);
        $this->assertTrue($score->passed); // 7.5 >= 7.0
    }

    #[Test]
    public function below_threshold_fails(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 6.0,
            'originality' => 5.0,
            'craft' => 7.0,
            'functionality' => 8.0,
        ]);

        // 6*0.3 + 5*0.2 + 7*0.3 + 8*0.2 = 1.8 + 1.0 + 2.1 + 1.6 = 6.5
        $this->assertSame(6.5, $score->total);
        $this->assertFalse($score->passed);
    }

    #[Test]
    public function scores_are_clamped_to_0_10(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 15.0,       // clamped to 10
            'originality' => -5.0,  // clamped to 0
            'craft' => 8.0,
            'functionality' => 7.0,
        ]);

        // 10*0.3 + 0*0.2 + 8*0.3 + 7*0.2 = 3.0 + 0 + 2.4 + 1.4 = 6.8
        $this->assertSame(6.8, $score->total);
    }

    #[Test]
    public function missing_dimensions_are_flagged(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 8.0,
            'craft' => 7.0,
            // originality and functionality missing
        ]);

        $this->assertContains('originality', $score->missingDimensions);
        $this->assertContains('functionality', $score->missingDimensions);
    }

    #[Test]
    public function missing_dimensions_score_zero(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 10.0,
            'originality' => 10.0,
            'craft' => 10.0,
            // functionality missing -> 0
        ]);

        // 10*0.3 + 10*0.2 + 10*0.3 + 0*0.2 = 3 + 2 + 3 + 0 = 8.0
        $this->assertSame(8.0, $score->total);
        $this->assertTrue($score->passed);
    }

    #[Test]
    public function custom_weights_must_sum_to_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EvaluationRubric(['a' => 0.5, 'b' => 0.6]);
    }

    #[Test]
    public function custom_threshold(): void
    {
        $rubric = new EvaluationRubric(threshold: 8.5);
        $score = $rubric->score([
            'design' => 9.0,
            'originality' => 7.0,
            'craft' => 9.0,
            'functionality' => 8.0,
        ]);

        // 9*0.3 + 7*0.2 + 9*0.3 + 8*0.2 = 2.7 + 1.4 + 2.7 + 1.6 = 8.4
        $this->assertSame(8.4, $score->total);
        $this->assertFalse($score->passed); // 8.4 < 8.5
    }

    #[Test]
    public function breakdown_shows_per_dimension_contribution(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score([
            'design' => 8.0,
            'originality' => 5.0,
            'craft' => 9.0,
            'functionality' => 7.0,
        ]);

        $this->assertSame(8.0, $score->breakdown['design']['raw']);
        $this->assertSame(0.3, $score->breakdown['design']['weight']);
        $this->assertSame(2.4, $score->breakdown['design']['contribution']);
    }

    #[Test]
    public function to_array_serializes_all_fields(): void
    {
        $rubric = new EvaluationRubric;
        $score = $rubric->score(['design' => 8.0, 'originality' => 7.0, 'craft' => 9.0, 'functionality' => 8.0]);
        $arr = $score->toArray();

        $this->assertArrayHasKey('total', $arr);
        $this->assertArrayHasKey('threshold', $arr);
        $this->assertArrayHasKey('passed', $arr);
        $this->assertArrayHasKey('breakdown', $arr);
        $this->assertArrayHasKey('missing_dimensions', $arr);
    }
}