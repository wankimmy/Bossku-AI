<?php

namespace Tests\Unit;

use App\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BosskuToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function log_tool_writes_ok_payload(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'log',
            'payload' => ['message' => 'unit-test-message'],
        ]);
        $this->assertSame('ok', $out['status']);
    }

    #[Test]
    public function unknown_tool_blocked(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'rm_rf_root',
            'payload' => [],
        ]);
        $this->assertSame('blocked', $out['status']);
    }

    #[Test]
    public function invoke_emits_single_array_event_for_stream(): void
    {
        $registry = app(ToolRegistry::class);
        $events = [];
        $registry->invoke(null, null, [
            'tool' => 'log',
            'payload' => ['message' => 'emit-test'],
        ], function (array $evt) use (&$events) {
            $events[] = $evt;
        });

        $this->assertCount(1, $events);
        $this->assertSame('tool_call', $events[0]['type']);
        $this->assertSame('log', $events[0]['tool']);
        $this->assertSame('tools', $events[0]['agent']);
        $this->assertStringContainsString('emit-test', (string) ($events[0]['summary'] ?? ''));
    }

    #[Test]
    public function file_read_emit_includes_path_in_summary(): void
    {
        $registry = app(ToolRegistry::class);
        $events = [];
        $registry->invoke(null, null, [
            'tool' => 'file_read_safe',
            'payload' => ['path' => 'routes/api.php'],
        ], function (array $evt) use (&$events) {
            $events[] = $evt;
        });

        $this->assertCount(1, $events);
        $this->assertStringContainsString('routes/api.php', (string) ($events[0]['summary'] ?? ''));
    }

    #[Test]
    public function direct_answer_role_cannot_use_db_query(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'db_query',
            'payload' => ['sql' => 'select 1'],
        ], null, 'direct_answer');

        $this->assertSame('blocked', $out['status']);
        $this->assertStringContainsString('not allowed', (string) ($out['result']['error'] ?? ''));
    }
}
