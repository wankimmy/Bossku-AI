<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyStaffApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_lists_and_updates_company_staff_for_the_active_project(): void
    {
        $this->project();

        $seed = $this->postJson('/api/company-staff/seed')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.role_slug', 'project-manager')
            ->json('data');

        $id = $seed[1]['id'];

        $this->patchJson('/api/company-staff/'.$id, [
            'display_name' => 'Lead Engineer',
            'staff_active' => false,
            'runtime_mode' => 'advisory',
            'council_enabled' => false,
            'trigger_keywords' => ['architecture', 'delivery'],
        ])
            ->assertOk()
            ->assertJsonPath('display_name', 'Lead Engineer')
            ->assertJsonPath('staff_active', false)
            ->assertJsonPath('runtime_mode', 'advisory')
            ->assertJsonPath('council_enabled', false);

        $this->getJson('/api/company-staff')
            ->assertOk()
            ->assertJsonCount(10, 'data');
    }

    private function project(): Project
    {
        return Project::query()->create([
            'name' => 'Company Staff API Project',
            'host_path' => sys_get_temp_dir(),
            'container_path' => sys_get_temp_dir(),
            'is_active' => true,
        ]);
    }
}
