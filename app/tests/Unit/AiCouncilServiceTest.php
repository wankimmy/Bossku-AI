<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\Company\CompanyStaffService;
use App\Services\Council\AiCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiCouncilServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('staff_council_enabled', '1');
        Setting::setValue('ai_council_enabled', '1');
    }

    #[Test]
    public function it_skips_trivial_prompts(): void
    {
        $run = Run::factory()->create(['prompt' => 'hi']);
        $result = app(AiCouncilService::class)->deliberate(
            $run,
            'hi',
            'Hello!',
            ['workflow' => 'direct_answer'],
            null,
        );

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('Hello!', $result['final_output']);
    }

    #[Test]
    public function it_returns_needs_clarification_when_audience_missing(): void
    {
        $run = Run::factory()->create(['prompt' => 'Help me decide landing page positioning']);
        $result = app(AiCouncilService::class)->deliberate(
            $run,
            'Help me decide landing page positioning',
            'Draft positioning ideas.',
            ['workflow' => 'writer_only', 'skill' => 'marketing'],
            null,
        );

        $this->assertSame('needs_clarification', $result['status']);
        $this->assertNotEmpty($result['questions']);
    }

    #[Test]
    public function it_completes_with_staff_voices_for_seo_prompt(): void
    {
        $project = Project::query()->create([
            'name' => 'Shop',
            'host_path' => '/workspace/shop',
            'container_path' => '/workspace/shop',
            'is_active' => true,
        ]);
        app(CompanyStaffService::class)->seedDefaults($project);

        $run = Run::factory()->create(['prompt' => 'Write SEO metadata for our SaaS landing page targeting developers']);
        $result = app(AiCouncilService::class)->deliberate(
            $run,
            'Write SEO metadata for our SaaS landing page targeting developers',
            'Title: BosskuAI',
            ['workflow' => 'writer_only', 'skill' => 'seo'],
            $project,
        );

        $this->assertSame('completed', $result['status']);
        $this->assertNotEmpty($result['voices']);
        $this->assertNotSame('', trim((string) ($result['final_output'] ?? '')));
    }
}
