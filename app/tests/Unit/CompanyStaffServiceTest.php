<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Company\CompanyStaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyStaffServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_product_team_plus_roster_once_per_project(): void
    {
        $project = $this->project();

        $first = app(CompanyStaffService::class)->seedDefaults($project);
        $second = app(CompanyStaffService::class)->seedDefaults($project);

        $this->assertCount(10, $first);
        $this->assertCount(10, $second);
        $this->assertSame(10, SpecialistAgent::query()->where('project_id', $project->id)->where('is_company_staff', true)->count());
        $this->assertSame(
            [
                'project-manager',
                'tech-lead',
                'ui-ux-designer',
                'blog-writer',
                'seo-writer',
                'marketing-manager',
                'sales-manager',
                'qa',
                'security',
                'customer-support',
            ],
            SpecialistAgent::query()
                ->where('project_id', $project->id)
                ->where('is_company_staff', true)
                ->orderBy('staff_sort_order')
                ->pluck('role_slug')
                ->all(),
        );

        $this->assertSame('mixed', SpecialistAgent::query()->where('role_slug', 'project-manager')->value('runtime_mode'));
        $this->assertSame('mixed', SpecialistAgent::query()->where('role_slug', 'tech-lead')->value('runtime_mode'));
        $this->assertSame('advisory', SpecialistAgent::query()->where('role_slug', 'seo-writer')->value('runtime_mode'));
    }

    #[Test]
    public function it_selects_relevant_staff_for_council_from_prompt_plan_and_workflow(): void
    {
        $project = $this->project();
        app(CompanyStaffService::class)->seedDefaults($project);

        $selected = app(CompanyStaffService::class)->selectForCouncil(
            'Write an SEO blog post and sales outreach plan for the new checkout flow.',
            [
                'checklist' => [
                    ['id' => 'plan-1', 'title' => 'Draft landing page copy', 'owner' => 'executor'],
                ],
                'risk_notes' => ['Marketing claim must be accurate.'],
            ],
            ['workflow' => 'writer_only', 'needs_executor' => false],
            $project,
        );

        $roles = $selected->pluck('role_slug')->all();

        $this->assertContains('project-manager', $roles);
        $this->assertContains('tech-lead', $roles);
        $this->assertContains('blog-writer', $roles);
        $this->assertContains('seo-writer', $roles);
        $this->assertContains('marketing-manager', $roles);
        $this->assertContains('sales-manager', $roles);
    }

    private function project(): Project
    {
        return Project::query()->create([
            'name' => 'Company Staff Project',
            'host_path' => sys_get_temp_dir(),
            'container_path' => sys_get_temp_dir(),
            'is_active' => true,
        ]);
    }
}
