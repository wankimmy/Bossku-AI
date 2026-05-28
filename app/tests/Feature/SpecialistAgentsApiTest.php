<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\SpecialistAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialistAgentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bossku.api_auth_enabled' => false]);
    }

    #[Test]
    public function it_lists_updates_and_reviews_specialist_agents(): void
    {
        $project = Project::query()->create([
            'name' => 'Bossku-AI',
            'host_path' => '/workspace/Bossku-AI',
            'container_path' => '/workspace/Bossku-AI',
            'is_active' => true,
        ]);

        $agent = SpecialistAgent::query()->create([
            'project_id' => $project->id,
            'role_slug' => 'orchestrator-specialist',
            'display_name' => 'Orchestrator Specialist',
            'description' => 'Draft orchestrator specialist.',
            'trigger_keywords' => ['orchestrator'],
            'persona_content' => 'Focus on orchestrator flows.',
            'approval_status' => 'draft',
            'pixel_palette' => 2,
            'pixel_hue_shift' => 0,
        ]);

        $this->getJson('/api/specialist-agents')
            ->assertOk()
            ->assertJsonFragment(['role_slug' => 'orchestrator-specialist']);

        $this->patchJson("/api/specialist-agents/{$agent->id}", [
            'trigger_keywords' => ['orchestrator', 'approval'],
            'pixel_palette' => 4,
            'seat_id' => 'f-abc',
        ])
            ->assertOk()
            ->assertJsonPath('trigger_keywords.1', 'approval')
            ->assertJsonPath('pixel_palette', 4);

        $this->postJson("/api/specialist-agents/{$agent->id}/approve")
            ->assertOk()
            ->assertJsonPath('approval_status', 'approved');

        $this->postJson("/api/specialist-agents/{$agent->id}/archive")
            ->assertOk()
            ->assertJsonPath('approval_status', 'archived');
    }

    #[Test]
    public function it_creates_a_specialist_agent_draft_from_a_run(): void
    {
        $project = Project::query()->create([
            'name' => 'Bossku-AI',
            'host_path' => '/workspace/Bossku-AI',
            'container_path' => '/workspace/Bossku-AI',
            'is_active' => true,
        ]);
        $run = Run::factory()->create([
            'prompt' => 'Improve orchestrator approval flow.',
            'selected_skill_name' => 'orchestrator-approval',
            'metadata' => ['active_project_id' => $project->id],
            'status' => 'completed',
        ]);

        $this->postJson("/api/runs/{$run->id}/create-specialist-agent")
            ->assertOk()
            ->assertJsonPath('agent.approval_status', 'draft')
            ->assertJsonPath('agent.project_id', $project->id);
    }
}
