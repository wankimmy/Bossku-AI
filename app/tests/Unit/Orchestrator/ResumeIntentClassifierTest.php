<?php

namespace Tests\Unit\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Orchestrator\ResumeIntentClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResumeIntentClassifierTest extends TestCase
{
    private ResumeIntentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ModelFallbackService::class, function ($mock): void {
            $mock->shouldReceive('chatWithFallbacks')->never();
        });
        $this->mock(RuntimeSettings::class, function ($mock): void {
            $mock->shouldReceive('routerModel')->andReturn('mock-router');
            $mock->shouldReceive('reasoningModel')->andReturn('mock-reasoning');
        });

        $this->classifier = app(ResumeIntentClassifier::class);
    }

    #[Test]
    public function it_replans_on_stage_scope_cues(): void
    {
        $this->assertSame('replan', $this->classifier->classify(
            "## User clarification\n- start with stage 1 first",
            ['stage' => 'executor_escalation', 'has_free_text' => true],
        ));
        $this->assertSame('replan', $this->classifier->classify(
            'only do the API layer',
            ['stage' => 'executor_escalation', 'has_free_text' => true],
        ));
        $this->assertSame('replan', $this->classifier->classify(
            'please change the approach',
            ['stage' => 'executor_stuck', 'has_free_text' => true],
        ));
    }

    #[Test]
    public function it_continues_on_proceed_without_scope_change(): void
    {
        $this->assertSame('continue', $this->classifier->classify(
            'yes, proceed',
            ['stage' => 'executor_escalation', 'option_only' => false, 'has_free_text' => true],
        ));
        $this->assertSame('continue', $this->classifier->classify(
            'go ahead',
            ['stage' => 'auditor_escalation', 'has_free_text' => true],
        ));
    }

    #[Test]
    public function it_continues_for_option_only_answers(): void
    {
        $this->assertSame('continue', $this->classifier->classify(
            '## User clarification',
            [
                'stage' => 'executor_escalation',
                'option_only' => true,
                'answers' => [['question_id' => 'q1', 'option_id' => 'proceed']],
            ],
        ));
    }

    #[Test]
    public function it_aborts_on_stop_keywords(): void
    {
        $this->assertSame('abort', $this->classifier->classify(
            'stop',
            ['stage' => 'executor_escalation', 'has_free_text' => true],
        ));
        $this->assertSame('abort', $this->classifier->classify(
            'never mind, cancel this',
            ['stage' => 'executor_stuck', 'has_free_text' => true],
        ));
    }
}
