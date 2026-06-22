<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Company\CompanyPortabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyPortabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompanyPortabilityService $portability;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portability = app(CompanyPortabilityService::class);
    }

    private function seedCompany(): Project
    {
        $project = Project::query()->create([
            'name' => 'Acme Origin',
            'host_path' => '/tmp/acme',
            'container_path' => '/tmp/acme',
            'is_active' => true,
        ]);

        $parent = Goal::query()->create(['project_id' => $project->id, 'title' => 'Launch', 'priority' => 'high', 'progress' => 30]);
        Goal::query()->create(['project_id' => $project->id, 'parent_goal_id' => $parent->id, 'title' => 'Backend', 'progress' => 60]);

        $manager = SpecialistAgent::query()->create([
            'project_id' => $project->id,
            'role_slug' => 'cto',
            'display_name' => 'CTO',
            'approval_status' => 'approved',
            'metadata' => ['api_key' => 'sk-live-SECRET', 'note' => 'keep'],
        ]);
        SpecialistAgent::query()->create([
            'project_id' => $project->id,
            'role_slug' => 'backend-eng',
            'display_name' => 'Backend Engineer',
            'approval_status' => 'approved',
            'reports_to_agent_id' => $manager->id,
        ]);

        return $project;
    }

    #[Test]
    public function export_scrubs_secrets_and_uses_stable_references(): void
    {
        $bundle = $this->portability->export($this->seedCompany());

        $this->assertSame(CompanyPortabilityService::VERSION, $bundle['bundle_version']);
        $this->assertCount(2, $bundle['goals']);
        $this->assertCount(2, $bundle['agents']);

        $cto = collect($bundle['agents'])->firstWhere('role_slug', 'cto');
        $this->assertSame(CompanyPortabilityService::REDACTED, $cto['metadata']['api_key']);
        $this->assertSame('keep', $cto['metadata']['note']);

        $backend = collect($bundle['agents'])->firstWhere('role_slug', 'backend-eng');
        $this->assertSame('cto', $backend['reports_to_role_slug']);
    }

    #[Test]
    public function import_recreates_org_with_goal_tree_and_reporting_lines(): void
    {
        $bundle = $this->portability->export($this->seedCompany());

        $imported = $this->portability->import($bundle, 'Acme Clone');

        $this->assertSame('Acme Clone', $imported->name);
        $this->assertFalse($imported->is_active);

        // Goal tree preserved.
        $goals = Goal::query()->where('project_id', $imported->id)->get();
        $this->assertCount(2, $goals);
        $child = $goals->firstWhere('title', 'Backend');
        $parent = $goals->firstWhere('title', 'Launch');
        $this->assertSame($parent->id, $child->parent_goal_id);

        // Reporting line preserved by role_slug remap.
        $agents = SpecialistAgent::query()->where('project_id', $imported->id)->get();
        $cto = $agents->firstWhere('role_slug', 'cto');
        $backend = $agents->firstWhere('role_slug', 'backend-eng');
        $this->assertSame($cto->id, $backend->reports_to_agent_id);

        // Secret stayed scrubbed in the imported copy.
        $this->assertSame(CompanyPortabilityService::REDACTED, $cto->metadata['api_key']);
    }

    #[Test]
    public function import_handles_name_collision(): void
    {
        $bundle = $this->portability->export($this->seedCompany());

        $a = $this->portability->import($bundle);
        $b = $this->portability->import($bundle);

        $this->assertNotSame($a->name, $b->name);
    }
}
