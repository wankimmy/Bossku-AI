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
}
