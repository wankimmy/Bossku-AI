<?php

namespace Tests\Unit;

use App\Services\BosskuAi\LoopStatus\LoopStatusInspector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the loop-status transcript inspector. Proves: healthy runs report
 * clean, repeated tool calls are detected, parse errors are flagged, overdue
 * steps surface, and max-iterations is caught.
 */
class LoopStatusInspectorTest extends TestCase
{
    #[Test]
    public function clean_run_is_healthy(): void
    {
        $steps = [
            ['agent' => 'planner', 'status' => 'success', 'output' => '{"plan": "step 1"}', 'step_number' => 1],
            ['agent' => 'executor', 'status' => 'success', 'output' => '{"files_changed": []}', 'step_number' => 2],
        ];

        $report = (new LoopStatusInspector)->inspect($steps);

        $this->assertTrue($report->healthy);
        $this->assertSame(2, $report->stepCount);
        $this->assertEmpty($report->findings);
    }

    #[Test]
    public function repeated_identical_output_is_detected(): void
    {
        $identical = '{"plan": "same thing"}';
        $steps = [
            ['agent' => 'executor', 'status' => 'success', 'output' => $identical, 'step_number' => 1],
            ['agent' => 'executor', 'status' => 'success', 'output' => $identical, 'step_number' => 2],
        ];

        $report = (new LoopStatusInspector)->inspect($steps);

        $this->assertFalse($report->healthy);
        $this->assertNotEmpty($report->findings);
        $this->assertSame('repeated_tool_call', $report->findings[0]['type']);
    }

    #[Test]
    public function parse_errors_are_flagged(): void
    {
        $steps = [
            ['agent' => 'planner', 'status' => 'failed', 'output' => 'json_decode_error: invalid JSON at position 5', 'step_number' => 1],
        ];

        $report = (new LoopStatusInspector)->inspect($steps);

        $this->assertFalse($report->healthy);
        $this->assertNotEmpty($report->parseErrors);
    }

    #[Test]
    public function running_steps_are_overdue(): void
    {
        $steps = [
            ['agent' => 'executor', 'status' => 'running', 'output' => '', 'step_number' => 1, 'created_at' => '2026-06-23T10:00:00Z'],
        ];

        $report = (new LoopStatusInspector)->inspect($steps);

        $this->assertFalse($report->healthy);
        $this->assertNotEmpty($report->overdueSteps);
    }

    #[Test]
    public function max_iterations_is_detected(): void
    {
        $steps = [
            ['agent' => 'build-fixer', 'status' => 'failed', 'output' => 'max_iterations reached without green', 'step_number' => 6],
        ];

        $report = (new LoopStatusInspector)->inspect($steps);

        $this->assertFalse($report->healthy);
        $this->assertTrue($report->maxIterationsReached);
    }

    #[Test]
    public function empty_steps_are_healthy(): void
    {
        $report = (new LoopStatusInspector)->inspect([]);

        $this->assertTrue($report->healthy);
        $this->assertSame(0, $report->stepCount);
    }

    #[Test]
    public function to_array_serializes_report(): void
    {
        $report = (new LoopStatusInspector)->inspect([]);

        $arr = $report->toArray();

        $this->assertArrayHasKey('healthy', $arr);
        $this->assertArrayHasKey('findings', $arr);
        $this->assertArrayHasKey('step_count', $arr);
    }
}