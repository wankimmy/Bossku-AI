<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorStuckDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorStuckDetectorTest extends TestCase
{
    #[Test]
    public function failed_status_alone_is_not_stuck(): void
    {
        $this->assertFalse(ExecutorStuckDetector::isStuck([
            'status' => 'failed',
            'known_issues' => ['Could not read files'],
        ]));
    }

    #[Test]
    public function needs_user_input_is_detected_via_wants_user_input(): void
    {
        $this->assertTrue(ExecutorStuckDetector::wantsUserInput([
            'status' => 'success',
            'needs_user_input' => true,
        ]));
    }

    #[Test]
    public function hard_blocker_in_known_issues_is_stuck(): void
    {
        $this->assertTrue(ExecutorStuckDetector::isStuck([
            'status' => 'failed',
            'known_issues' => ['file_put_contents: Permission denied'],
        ]));
    }

    #[Test]
    public function exhausted_revision_rounds_is_stuck(): void
    {
        $this->assertTrue(ExecutorStuckDetector::isStuck(
            ['status' => 'success'],
            ['status' => 'needs_revision'],
            1,
            1,
        ));
    }
}
