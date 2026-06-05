<?php

namespace Tests\Feature;

use App\Models\BosskuAi\AgentPersona;
use Database\Seeders\AgentPersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentPersonaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentPersonaSeeder::class);
    }

    #[Test]
    public function index_lists_pipeline_roles(): void
    {
        $this->getJson('/api/agent-personas')
            ->assertOk()
            ->assertJsonStructure(['data' => [['role', 'display_name', 'enabled']]]);
    }

    #[Test]
    public function index_bootstraps_defaults_when_table_empty(): void
    {
        AgentPersona::query()->delete();

        $expected = count(\App\Services\BosskuAi\AgentPersonaService::PIPELINE_ROLES);

        $this->getJson('/api/agent-personas')
            ->assertOk()
            ->assertJsonCount($expected, 'data');

        $this->assertSame($expected, AgentPersona::query()->count());
    }

    #[Test]
    public function show_returns_role_detail(): void
    {
        $this->getJson('/api/agent-personas/executor')
            ->assertOk()
            ->assertJsonPath('role', 'executor')
            ->assertJsonStructure(['builtin_preview', 'content', 'enabled']);
    }

    #[Test]
    public function update_persists_content_and_enabled(): void
    {
        $this->putJson('/api/agent-personas/executor', [
            'content' => 'Updated persona body.',
            'enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->assertDatabaseHas('bossku_ai_agent_personas', [
            'role' => 'executor',
            'enabled' => 0,
        ]);
    }

    #[Test]
    public function unknown_role_returns_404(): void
    {
        $this->getJson('/api/agent-personas/not_a_real_role')
            ->assertNotFound();
    }

    #[Test]
    public function reset_restores_default_content(): void
    {
        AgentPersona::query()->where('role', 'executor')->update(['content' => 'Temporary.']);

        $this->postJson('/api/agent-personas/executor/reset')
            ->assertOk();

        $row = AgentPersona::query()->find('executor');
        $this->assertNotSame('Temporary.', $row?->content);
    }
}
