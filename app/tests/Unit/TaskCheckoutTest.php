<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Checkout\CheckoutConflictException;
use App\Services\BosskuAi\Checkout\TaskCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the atomic task checkout primitive. Proves: single-agent checkout
 * succeeds, concurrent checkout conflicts (409), release returns to
 * available, complete marks done, force-release is the admin escape hatch,
 * and the lock token prevents unauthorized release.
 */
class TaskCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TaskCheckoutService
    {
        return app(TaskCheckoutService::class);
    }

    private function taskRef(string $id = 'task-1'): array
    {
        return ['type' => 'work_issue', 'id' => $id];
    }

    #[Test]
    public function checkout_succeeds_for_available_task(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef(), ['title' => 'Fix bug']);

        $token = $svc->checkout($this->taskRef(), 'executor');

        $this->assertNotEmpty($token);
        $owner = $svc->owner($this->taskRef());
        $this->assertSame('executor', $owner['assignee']);
        $this->assertSame('in_progress', $owner['status']);
        $this->assertSame($token, $owner['lock_token']);
    }

    #[Test]
    public function checkout_conflicts_when_already_owned(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());

        $svc->checkout($this->taskRef(), 'agent-a');

        $this->expectException(CheckoutConflictException::class);
        $svc->checkout($this->taskRef(), 'agent-b');
    }

    #[Test]
    public function same_agent_can_recheckout_own_task(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());

        $token1 = $svc->checkout($this->taskRef(), 'agent-a');
        // The same agent re-acquiring (e.g. after a resume) gets a new token.
        $token2 = $svc->checkout($this->taskRef(), 'agent-a');

        $this->assertNotSame($token1, $token2);
    }

    #[Test]
    public function release_returns_task_to_available(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());
        $token = $svc->checkout($this->taskRef(), 'agent-a');

        $released = $svc->release($this->taskRef(), $token);

        $this->assertTrue($released);
        $owner = $svc->owner($this->taskRef());
        $this->assertSame('available', $owner['status']);
        $this->assertNull($owner['assignee']);
    }

    #[Test]
    public function release_with_wrong_token_fails(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());
        $svc->checkout($this->taskRef(), 'agent-a');

        $released = $svc->release($this->taskRef(), 'wrong-token');

        $this->assertFalse($released);
        $this->assertSame('in_progress', $svc->owner($this->taskRef())['status']);
    }

    #[Test]
    public function complete_marks_done_and_clears_token(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());
        $token = $svc->checkout($this->taskRef(), 'agent-a');

        $done = $svc->complete($this->taskRef(), $token);

        $this->assertTrue($done);
        $owner = $svc->owner($this->taskRef());
        $this->assertSame('done', $owner['status']);
        $this->assertNull($owner['lock_token']);
    }

    #[Test]
    public function force_release_is_the_admin_escape_hatch(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());
        $svc->checkout($this->taskRef(), 'agent-a');

        // Force release without the token.
        $svc->forceRelease($this->taskRef());

        $owner = $svc->owner($this->taskRef());
        $this->assertSame('available', $owner['status']);
        $this->assertNull($owner['assignee']);
    }

    #[Test]
    public function after_release_another_agent_can_checkout(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());
        $token = $svc->checkout($this->taskRef(), 'agent-a');
        $svc->release($this->taskRef(), $token);

        $token2 = $svc->checkout($this->taskRef(), 'agent-b');

        $this->assertNotEmpty($token2);
        $this->assertSame('agent-b', $svc->owner($this->taskRef())['assignee']);
    }

    #[Test]
    public function ensure_available_is_idempotent(): void
    {
        $svc = $this->service();
        $ref = $this->taskRef();

        $svc->ensureAvailable($ref, ['title' => 'first']);
        $svc->ensureAvailable($ref, ['title' => 'second']);

        // Only one row exists.
        $this->assertDatabaseCount('bossku_ai_task_checkouts', 1);
    }

    #[Test]
    public function owner_returns_null_for_nonexistent_task(): void
    {
        $svc = $this->service();
        $this->assertNull($svc->owner($this->taskRef('nonexistent')));
    }

    #[Test]
    public function checkout_for_nonexistent_row_throws_conflict(): void
    {
        $svc = $this->service();
        // No ensureAvailable called — the row doesn't exist, so the UPDATE
        // affects 0 rows and we get a conflict.
        $this->expectException(CheckoutConflictException::class);
        $svc->checkout($this->taskRef('nonexistent'), 'agent-a');
    }

    #[Test]
    public function run_id_is_recorded_on_checkout(): void
    {
        $svc = $this->service();
        $svc->ensureAvailable($this->taskRef());
        $svc->checkout($this->taskRef(), 'executor', 'run-uuid-123');

        $owner = $svc->owner($this->taskRef());
        $this->assertSame('run-uuid-123', $owner['run_id']);
    }
}