<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyTeamsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_company_teams_catalog(): void
    {
        $this->getJson('/api/company-teams')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'core-engineering');
    }

    #[Test]
    public function it_installs_team_for_active_project(): void
    {
        Project::query()->create([
            'name' => 'Shop',
            'host_path' => '/workspace/shop',
            'container_path' => '/workspace/shop',
            'is_active' => true,
        ]);
        Setting::setValue('company_staff_enabled', '1');

        $this->postJson('/api/company-teams/install', ['team_slug' => 'growth-sales'])
            ->assertOk()
            ->assertJsonPath('team_slug', 'growth-sales')
            ->assertJsonStructure(['installed']);
    }

    #[Test]
    public function it_dispatches_queued_wakeups_when_enabled(): void
    {
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('agent_wakeups_enabled', '1');

        $this->postJson('/api/agent-wakeups/dispatch')
            ->assertOk()
            ->assertJsonStructure(['processed', 'skipped', 'failed']);
    }
}
