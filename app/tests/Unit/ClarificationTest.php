<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ClarificationService;
use App\Services\Orchestrator\ExecutorStuckDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClarificationTest extends TestCase
{
    #[Test]
    public function executor_stuck_detector_does_not_flag_failed_without_blocker(): void
    {
        $this->assertFalse(ExecutorStuckDetector::isStuck([
            'status' => 'failed',
            'known_issues' => ['Could not read files'],
        ]));
    }

    #[Test]
    public function executor_stuck_detector_flags_needs_user_input_via_wants_user_input(): void
    {
        $this->assertTrue(ExecutorStuckDetector::wantsUserInput([
            'status' => 'success',
            'needs_user_input' => true,
        ]));
        $this->assertFalse(ExecutorStuckDetector::isStuck([
            'status' => 'success',
            'needs_user_input' => true,
        ]));
    }

    #[Test]
    public function clarification_normalize_produces_questions(): void
    {
        $service = app(ClarificationService::class);
        $out = $service->normalize([
            'summary' => 'Need scope',
            'assumptions' => ['Will audit read-only'],
            'ready_to_proceed' => true,
            'questions' => [
                [
                    'id' => 'q1',
                    'prompt' => 'Full or partial audit?',
                    'options' => [
                        ['id' => 'a', 'label' => 'Full', 'recommendation' => true],
                        ['id' => 'b', 'label' => 'Partial'],
                    ],
                ],
            ],
        ], 'pre_execution', 'audit my repo');

        $this->assertFalse($out['ready_to_proceed']);
        $this->assertCount(1, $out['questions']);
        $this->assertSame('q1', $out['questions'][0]['id']);
        $this->assertCount(3, $out['questions'][0]['options']);
    }

    #[Test]
    public function normalize_options_to_three_pads_short_lists(): void
    {
        $service = app(ClarificationService::class);
        $options = $service->normalizeOptionsToThree([
            ['id' => 'a', 'label' => 'Only one', 'recommendation' => true],
        ], 'pre_execution');

        $this->assertCount(3, $options);
        $this->assertSame('a', $options[0]['id']);
        $this->assertSame('Only one', $options[0]['label']);
    }

    #[Test]
    public function format_answers_block_includes_selections(): void
    {
        $service = app(ClarificationService::class);
        $block = $service->formatAnswersBlock(
            [
                ['question_id' => 'q1', 'option_id' => 'a', 'free_text' => 'Focus on API layer'],
            ],
            [
                [
                    'id' => 'q1',
                    'prompt' => 'Scope?',
                    'options' => [['id' => 'a', 'label' => 'API only']],
                ],
            ],
        );

        $this->assertStringContainsString('User clarification answers', $block);
        $this->assertStringContainsString('API only', $block);
        $this->assertStringContainsString('Focus on API layer', $block);
    }
}
