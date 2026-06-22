<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContinuationRunFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::query()->delete();
        Setting::setValue('routing_llm_enabled', '0');
        Setting::setValue('ai_council_enabled', '0');
        config(['bossku_models.router.enabled' => false]);
    }

    #[Test]
    public function continuation_run_inherits_parent_thread_and_conversation(): void
    {
        $this->mock(ModelFallbackService::class, function ($mock): void {
            $mock->shouldReceive('chatWithFallbacks')
                ->andReturn(['text' => 'Continuing the docs task.', 'model' => 'mock']);
        });

        $parent = Run::query()->create([
            'prompt' => 'read docs/PRODUCT_SPEC.md',
            'status' => 'completed',
            'metadata' => [
                'conversation' => [
                    ['role' => 'user', 'content' => 'read docs/PRODUCT_SPEC.md'],
                    ['role' => 'assistant', 'content' => 'Ready to continue.'],
                ],
                'context_anchors' => [
                    'docs_targets' => ['docs/PRODUCT_SPEC.md'],
                    'target_paths' => ['docs/PRODUCT_SPEC.md'],
                    'task_kind' => 'docs_read',
                ],
            ],
        ]);

        $result = app(OrchestratorService::class)->run('proceed', null, [], [
            'parent_run_id' => $parent->id,
            'context_anchors' => $parent->metadata['context_anchors'],
        ]);

        $child = Run::query()->findOrFail((string) ($result['run_id'] ?? ''));
        $this->assertSame($parent->id, $child->parent_run_id);
        $this->assertNotNull($child->thread_id);
        $anchors = is_array($child->metadata) ? ($child->metadata['context_anchors'] ?? []) : [];
        $this->assertContains('docs/PRODUCT_SPEC.md', $anchors['docs_targets'] ?? []);
    }
}
