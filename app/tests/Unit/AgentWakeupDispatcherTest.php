<?php

namespace Tests\Unit;

use App\Jobs\ProcessAgentWakeupRequestJob;
use App\Models\BosskuAi\AgentWakeupRequest;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\Company\AgentWakeupDispatcher;
use App\Services\Company\CompanyStaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    #[Test]
    public function it_marks_queued_wakeups_processing_and_dispatches_jobs(): void
    {
        Queue::fake();

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

        $request = app(AgentWakeupDispatcher::class)
            ->enqueue($agent, null, $run, 'manual_wakeup', ['task_key' => 'seo-2']);

        $result = app(AgentWakeupDispatcher::class)->dispatchQueued();

        $this->assertSame(1, $result['processed']);
        $this->assertSame('processing', $request->refresh()->status);
        Queue::assertPushed(ProcessAgentWakeupRequestJob::class);
    }

    #[Test]
    public function enqueue_is_noop_when_wakeups_are_disabled(): void
    {
        Setting::setValue('agent_wakeups_enabled', '0');

        $request = app(AgentWakeupDispatcher::class)
            ->enqueue(null, null, null, 'manual_wakeup', ['task_key' => 'disabled']);

        $this->assertFalse($request->exists);
        $this->assertSame(0, AgentWakeupRequest::query()->count());
    }
}
