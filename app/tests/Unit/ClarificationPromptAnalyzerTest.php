<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Setting;
use App\Services\Orchestrator\ClarificationPromptAnalyzer;
use App\Services\Orchestrator\ClarificationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClarificationPromptAnalyzerTest extends TestCase
{
    #[Test]
    public function clear_create_file_prompt_skips_clarification(): void
    {
        $this->assertTrue(ClarificationPromptAnalyzer::isClearEnough(
            'Create hello-world.txt with Hello World',
            ['workflow' => 'orchestrator_executor'],
        ));
    }

    #[Test]
    public function audit_prompt_is_not_clear(): void
    {
        $this->assertFalse(ClarificationPromptAnalyzer::isClearEnough(
            'help me audit splitlah repo audit full',
            ['workflow' => 'orchestrator_executor_auditor_security'],
        ));
    }

    #[Test]
    public function direct_answer_workflow_is_clear(): void
    {
        $this->assertTrue(ClarificationPromptAnalyzer::isClearEnough(
            'Explain Laravel policy vs gate',
            ['workflow' => 'direct_answer'],
        ));
    }

    #[Test]
    public function smart_ask_returns_empty_proceed_for_clear_prompt(): void
    {
        Setting::query()->delete();
        Setting::setValue('orchestrator_clarification_mode', 'smart');

        $service = app(ClarificationService::class);
        $out = $service->ask(
            'Create hello-world.txt with Hello World',
            [],
            ['workflow' => 'orchestrator_executor'],
            'pre_execution',
        );

        $this->assertTrue($out['ready_to_proceed']);
        $this->assertSame([], $out['questions']);
    }
}
