<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Specialists\SpecialistAgentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialistAgentRouterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_matches_approved_specialists_for_the_active_project(): void
    {
        $projectA = Project::query()->create([
            'name' => 'Shop',
            'host_path' => '/workspace/shop',
            'container_path' => '/workspace/shop',
            'is_active' => true,
        ]);
        $projectB = Project::query()->create([
            'name' => 'Portal',
            'host_path' => '/workspace/portal',
            'container_path' => '/workspace/portal',
            'is_active' => false,
        ]);

        $approved = SpecialistAgent::query()->create([
            'project_id' => $projectA->id,
            'role_slug' => 'checkout-specialist',
            'display_name' => 'Checkout Specialist',
            'description' => 'Handles checkout fee, payment, and cart bugs.',
            'trigger_keywords' => ['checkout', 'fee', 'cart'],
            'persona_content' => 'Focus on checkout pricing and payment handoff risk.',
            'approval_status' => 'approved',
        ]);

        SpecialistAgent::query()->create([
            'project_id' => $projectA->id,
            'role_slug' => 'draft-payment-specialist',
            'display_name' => 'Draft Payment Specialist',
            'description' => 'This draft must not route yet.',
            'trigger_keywords' => ['checkout', 'fee'],
            'persona_content' => 'Draft only.',
            'approval_status' => 'draft',
        ]);

        SpecialistAgent::query()->create([
            'project_id' => $projectB->id,
            'role_slug' => 'portal-checkout-specialist',
            'display_name' => 'Portal Checkout Specialist',
            'description' => 'Wrong project specialist.',
            'trigger_keywords' => ['checkout', 'fee', 'cart'],
            'persona_content' => 'Wrong project.',
            'approval_status' => 'approved',
        ]);

        $match = app(SpecialistAgentRouter::class)
            ->matchForPrompt('Fix the checkout fee calculation in the cart', $projectA);

        $this->assertSame($approved->id, $match?->id);
    }

    #[Test]
    public function it_returns_null_when_no_approved_project_specialist_matches(): void
    {
        $project = Project::query()->create([
            'name' => 'Shop',
            'host_path' => '/workspace/shop',
            'container_path' => '/workspace/shop',
            'is_active' => true,
        ]);

        SpecialistAgent::query()->create([
            'project_id' => $project->id,
            'role_slug' => 'draft-checkout-specialist',
            'display_name' => 'Draft Checkout Specialist',
            'description' => 'Draft agent.',
            'trigger_keywords' => ['checkout'],
            'persona_content' => 'Draft only.',
            'approval_status' => 'draft',
        ]);

        $match = app(SpecialistAgentRouter::class)
            ->matchForPrompt('Fix checkout issue', $project);

        $this->assertNull($match);
    }
}
