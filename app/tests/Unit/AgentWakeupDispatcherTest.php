<?php

namespace Tests\Unit;

use App\Models\BosskuAi\AgentWakeupRequest;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\Company\AgentWakeupDispatcher;
use App\Services\Company\CompanyStaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentWakeupDispatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_coalesces_duplicate_wakeup_requests(): void
    {
        $project = Project::query()->create([
            'name' => 'Shop',
            'host_path' => '/workspace/shop',
            'container_path' => '/workspace/shop',
            'is_active' => true,
        ]);
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('agent_wakeups_enabled', '1');
        $staff = app(CompanyStaffService::class)->seedDefaults($project);
        $agent = $staff->firstWhere('role_slug', 'seo-writer');
        $run = Run::factory()->create(['prompt' => 'Parent run']);

        $dispatcher = app(AgentWakeupDispatcher::class);
        $first = $dispatcher->enqueue($agent, null, $run, 'manual_wakeup', ['task_key' => 'seo-1']);
        $second = $dispatcher->enqueue($agent, null, $run, 'manual_wakeup', ['task_key' => 'seo-1']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AgentWakeupRequest::query()->count());
    }
}
