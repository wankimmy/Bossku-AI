<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShortPathRoutingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::query()->delete();
        Setting::setValue('routing_llm_enabled', '0');
        Setting::setValue('ai_council_enabled', '0');
        Setting::setValue('company_staff_enabled', '1');
        config(['bossku_models.router.enabled' => false]);
    }

    #[Test]
    public function meta_capability_prompt_completes_without_executor_pipeline(): void
    {
        $this->mock(ModelFallbackService::class, function ($mock): void {
            $mock->shouldReceive('chatWithFallbacks')
                ->andReturn(['text' => 'I am BosskuAI with coding, chat, and specialist modes.', 'model' => 'mock']);
        });

        $result = app(OrchestratorService::class)->run('what are you good at?');

        $runId = (string) ($result['run_id'] ?? '');
        $this->assertNotSame('', $runId);

        $run = Run::query()->findOrFail($runId);
        $this->assertSame('completed', $run->status);
        $workflow = is_array($run->metadata) ? ($run->metadata['routing_decision']['workflow'] ?? null) : null;
        $this->assertSame('direct_answer', $workflow);

        $executorSteps = RunStep::query()
            ->where('run_id', $runId)
            ->where('type', 'like', '%executor%')
            ->count();
        $this->assertSame(0, $executorSteps);
    }

    #[Test]
    public function council_precheck_resume_uses_answer_context_and_completes(): void
    {
        Setting::setValue('ai_council_enabled', '1');
        Setting::setValue('staff_council_enabled', '1');

        $this->mock(ModelFallbackService::class, function ($mock): void {
            $mock->shouldReceive('chatWithFallbacks')
                ->andReturn(['text' => 'Position the landing page for enterprise B2B SaaS teams.', 'model' => 'mock']);
        });

        $run = Run::factory()->create([
            'prompt' => 'Help me decide the best landing page positioning',
            'status' => 'awaiting_input',
            'metadata' => [
                'checkpoint' => [
                    'phase' => 'awaiting_clarification',
                    'stage' => 'council_precheck',
                    'clarification' => [
                        'questions' => [
                            ['id' => 'audience', 'prompt' => 'Who is the target audience or buyer?'],
                        ],
                        'assumptions' => [],
                        'summary' => 'The AI council needs a little more context before it can answer accurately.',
                    ],
                    'pipeline' => [
                        'user_prompt' => 'Help me decide the best landing page positioning',
                        'conversation' => [],
                        'model_route' => [
                            'workflow' => 'writer_only',
                            'skill' => 'marketing',
                            'risk_level' => 'low',
                            'memory_mode' => 'read_only',
                        ],
                        'models_resolved' => ['writer' => 'mock', 'direct_answer' => 'mock'],
                        'router_meta' => [],
                        'mem_payload' => [],
                        'token_acc' => 0,
                        't_run' => microtime(true),
                    ],
                ],
            ],
        ]);

        app(OrchestratorService::class)->continueRun($run->id, [
            ['question_id' => 'audience', 'free_text' => 'Enterprise B2B SaaS teams'],
        ]);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertArrayNotHasKey('checkpoint', $run->metadata ?? []);
        $this->assertStringContainsString('enterprise', strtolower((string) $run->final_output));
    }

    #[Test]
    public function anaphoric_docs_follow_up_does_not_finish_on_direct_answer_short_path(): void
    {
        $this->mock(ModelFallbackService::class, function ($mock): void {
            $mock->shouldReceive('chatWithFallbacks')
                ->andReturn(['text' => '{"goal":"Read docs/PRODUCT_SPEC.md","checklist":[]}', 'model' => 'mock']);
        });

        $conversation = [
            ['role' => 'user', 'content' => 'read docs/PRODUCT_SPEC.md'],
            ['role' => 'assistant', 'content' => 'I can inspect that file in the active repo.'],
        ];

        $result = app(OrchestratorService::class)->run('ok read it', null, $conversation);

        $run = Run::query()->findOrFail((string) ($result['run_id'] ?? ''));
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $workflow = $meta['routing_decision']['workflow'] ?? null;
        $anchors = is_array($meta['context_anchors'] ?? null) ? $meta['context_anchors'] : [];
        $this->assertNotSame('direct_answer', $workflow);
        $this->assertTrue(
            ($meta['routing_decision']['needs_repo_context'] ?? false) === true
            || in_array('docs/PRODUCT_SPEC.md', $anchors['docs_targets'] ?? [], true),
        );
    }
}
