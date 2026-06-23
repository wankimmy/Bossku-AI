<?php

namespace Tests\Unit;

use App\Services\Agents\BackgroundJobService;
use App\Services\Agents\SubagentTaskContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the background subagent task contract. Proves: the don't-poll
 * instruction text, the <task> result envelope format, the background job
 * lifecycle (start → complete/fail → delivered), and pending-job retrieval.
 */
class SubagentTaskContractTest extends TestCase
{
    #[Test]
    public function background_instruction_contains_dont_poll_directive(): void
    {
        $this->assertStringContainsString('DO NOT sleep, poll', SubagentTaskContract::BACKGROUND_INSTRUCTION);
        $this->assertStringContainsString('Continue with other work', SubagentTaskContract::BACKGROUND_INSTRUCTION);
        $this->assertStringContainsString('Do not duplicate its work', SubagentTaskContract::BACKGROUND_INSTRUCTION);
    }

    #[Test]
    public function result_envelope_wraps_output_in_task_tag(): void
    {
        $envelope = SubagentTaskContract::resultEnvelope('task-123', 'completed', 'Built the feature', 'Files changed: 3');

        $this->assertStringContainsString('<task id="task-123" state="completed">', $envelope);
        $this->assertStringContainsString('<summary>Built the feature</summary>', $envelope);
        $this->assertStringContainsString('<task_result>Files changed: 3</task_result>', $envelope);
        $this->assertStringContainsString('</task>', $envelope);
    }

    #[Test]
    public function result_envelope_omits_empty_summary(): void
    {
        $envelope = SubagentTaskContract::resultEnvelope('t1', 'running', '', 'working...');

        $this->assertStringNotContainsString('<summary>', $envelope);
        $this->assertStringContainsString('<task_result>working...</task_result>', $envelope);
    }

    #[Test]
    public function result_envelope_error_state(): void
    {
        $envelope = SubagentTaskContract::resultEnvelope('t1', 'error', 'Failed', 'Timeout');

        $this->assertStringContainsString('state="error"', $envelope);
    }

    #[Test]
    public function result_delivery_instruction_integrates_result(): void
    {
        $this->assertStringContainsString('Review the <task_result>', SubagentTaskContract::RESULT_DELIVERY_INSTRUCTION);
        $this->assertStringContainsString('Do not re-do the task', SubagentTaskContract::RESULT_DELIVERY_INSTRUCTION);
    }

    #[Test]
    public function background_job_lifecycle_start_complete_delivered(): void
    {
        $svc = new BackgroundJobService;
        $svc->useMemoryStore();
        $taskId = 'bg-1';

        $svc->start($taskId, 'parent-run-1');
        $this->assertTrue($svc->isRunning($taskId));

        $svc->complete($taskId, 'task output here');
        $this->assertFalse($svc->isRunning($taskId));

        $job = $svc->get($taskId);
        $this->assertSame('completed', $job['state']);
        $this->assertSame('task output here', $job['result']);

        $pending = $svc->pendingForParent('parent-run-1');
        $this->assertCount(1, $pending);
        $this->assertSame($taskId, $pending[0]['task_id']);

        $svc->markDelivered($taskId);
        $this->assertCount(0, $svc->pendingForParent('parent-run-1'));
    }

    #[Test]
    public function background_job_lifecycle_start_fail(): void
    {
        $svc = new BackgroundJobService;
        $svc->useMemoryStore();
        $taskId = 'bg-fail';

        $svc->start($taskId, 'parent-1');
        $svc->fail($taskId, 'Something went wrong');

        $job = $svc->get($taskId);
        $this->assertSame('error', $job['state']);
        $this->assertSame('Something went wrong', $job['result']);

        $pending = $svc->pendingForParent('parent-1');
        $this->assertCount(1, $pending);
    }

    #[Test]
    public function pending_filters_by_parent_run(): void
    {
        $svc = new BackgroundJobService;
        $svc->useMemoryStore();

        $svc->start('bg-a', 'parent-1');
        $svc->start('bg-b', 'parent-2');
        $svc->complete('bg-a', 'done a');
        $svc->complete('bg-b', 'done b');

        $p1 = $svc->pendingForParent('parent-1');
        $p2 = $svc->pendingForParent('parent-2');

        $this->assertCount(1, $p1);
        $this->assertSame('bg-a', $p1[0]['task_id']);
        $this->assertCount(1, $p2);
        $this->assertSame('bg-b', $p2[0]['task_id']);
    }

    #[Test]
    public function running_jobs_not_in_pending(): void
    {
        $svc = new BackgroundJobService;
        $svc->useMemoryStore();

        $svc->start('bg-running', 'parent-1');

        $this->assertCount(0, $svc->pendingForParent('parent-1'));
    }

    #[Test]
    public function get_returns_null_for_unknown_task(): void
    {
        $svc = new BackgroundJobService;
        $svc->useMemoryStore();

        $this->assertNull($svc->get('nonexistent'));
        $this->assertFalse($svc->isRunning('nonexistent'));
    }

    #[Test]
    public function mark_delivered_on_unknown_task_is_noop(): void
    {
        $svc = new BackgroundJobService;
        $svc->useMemoryStore();

        // Should not throw.
        $svc->markDelivered('nonexistent');
        $this->assertTrue(true);
    }
}