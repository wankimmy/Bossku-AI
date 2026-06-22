<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Project\ProjectService;
use App\Services\Specialists\DynamicSpecialistSynthesizer;
use App\Services\Specialists\SpecialistAgentDraftingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DynamicSpecialistSynthesizerTest extends TestCase
{
    use RefreshDatabase;

    private function synthesizer(callable $responder): DynamicSpecialistSynthesizer
    {
        $fake = new class extends ModelFallbackService
        {
            /** @var callable */
            public $responder;

            public function __construct() {}

            public function chatWithFallbacks(array $models, array $messages, float $temperature, int $retryCount, string $role, ?callable $isValidJson = null, ?int $maxTokensAnthropic = null, ?string $runId = null, ?string $runStepId = null, array $metadata = []): array
            {
                $parsed = ($this->responder)();

                return ['text' => json_encode($parsed) ?: '{}', 'parsed' => $parsed, 'model_used' => 'fake', 'model_resolved' => 'fake', 'provider_used' => 'fake', 'fallback_used' => false, 'fallback_reason' => null, 'input_tokens' => 0, 'output_tokens' => 0];
            }
        };
        $fake->responder = $responder;

        return new DynamicSpecialistSynthesizer($fake, app(ModelRoutingConfig::class));
    }

    #[Test]
    public function synthesizes_a_tailored_spec(): void
    {
        $spec = $this->synthesizer(fn () => [
            'display_name' => 'Stripe Webhook Specialist',
            'role_slug' => 'stripe-webhook',
            'description' => 'Handles Stripe webhook signature verification and idempotency.',
            'trigger_keywords' => ['Stripe', 'WEBHOOK', 'idempotency'],
            'expertise' => ['payment events', 'signature verification'],
            'persona_content' => '# Stripe Webhook Specialist\n## When to use\n...',
        ])->synthesize('add stripe webhook handling', ['summary' => 'wire webhooks'], null);

        $this->assertNotNull($spec);
        $this->assertSame('Stripe Webhook Specialist', $spec['display_name']);
        // role_slug normalized to kebab + -specialist suffix.
        $this->assertSame('stripe-webhook-specialist', $spec['role_slug']);
        // keywords lowercased + de-duped.
        $this->assertContains('stripe', $spec['trigger_keywords']);
        $this->assertContains('webhook', $spec['trigger_keywords']);
    }

    #[Test]
    public function returns_null_on_invalid_model_output(): void
    {
        $spec = $this->synthesizer(fn () => ['nonsense' => true])
            ->synthesize('do something', [], null);

        $this->assertNull($spec);
    }

    #[Test]
    public function draft_from_spec_persists_unique_auto_created_agent(): void
    {
        $project = Project::query()->create([
            'name' => 'Demo',
            'host_path' => '/tmp/demo',
            'container_path' => '/tmp/demo',
            'is_active' => true,
        ]);

        $spec = [
            'display_name' => 'GraphQL Schema Specialist',
            'role_slug' => 'graphql-schema-specialist',
            'description' => 'Designs GraphQL schemas.',
            'trigger_keywords' => ['graphql', 'schema'],
            'expertise' => ['type design'],
            'persona_content' => '# GraphQL Schema Specialist',
        ];

        $drafting = app(SpecialistAgentDraftingService::class);

        $a = $drafting->draftFromSpec($project, $spec, ['run_id' => 'run-1']);
        $b = $drafting->draftFromSpec($project, $spec, ['run_id' => 'run-2']);

        $this->assertSame('draft', $a->approval_status);
        $this->assertSame('dynamic_synthesis', $a->metadata['source']);
        $this->assertTrue($a->metadata['auto_created']);
        $this->assertContains('graphql', $a->trigger_keywords);
        // Second draft of the same role gets a unique slug.
        $this->assertNotSame($a->role_slug, $b->role_slug);
        $this->assertSame(2, SpecialistAgent::query()->where('project_id', $project->id)->count());
    }
}
