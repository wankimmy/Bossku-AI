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

    #[Test]
    public function file_read_safe_returns_line_numbered_paged_content(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'file_read_safe',
            'payload' => ['path' => 'AGENTS.md', 'offset' => 2, 'limit' => 3],
        ]);

        $this->assertSame('ok', $out['status']);
        $result = $out['result'];
        $this->assertTrue($result['found']);
        $this->assertSame(2, $result['offset']);
        $this->assertGreaterThan(0, $result['total_lines']);
        $this->assertLessThanOrEqual(3, $result['returned_lines']);
        $this->assertStringStartsWith('2: ', strtok((string) $result['preview'], "\n"));
    }

    #[Test]
    public function file_search_finds_matches_with_path(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'file_search',
            'payload' => ['q' => 'Changelog', 'glob' => 'CHANGELOG.md'],
        ]);

        $this->assertSame('ok', $out['status']);
        $this->assertGreaterThanOrEqual(1, $out['result']['count']);
        $this->assertArrayHasKey('path', $out['result']['matches'][0]);
        $this->assertContains($out['result']['engine'] ?? null, ['ripgrep', 'php']);
    }

    #[Test]
    public function file_edit_applies_surgical_change_through_approval_path(): void
    {
        $registry = app(ToolRegistry::class);

        $name = 'fe_'.uniqid().'.txt';
        $abs = storage_path('framework/testing/'.$name);
        $rel = 'app/storage/framework/testing/'.$name;
        @mkdir(dirname($abs), 0777, true);
        file_put_contents($abs, "alpha\nbeta\ngamma\n");

        try {
            $out = $registry->invoke(null, null, [
                'tool' => 'file_edit',
                'payload' => ['path' => $rel, 'old_string' => 'beta', 'new_string' => 'BETA'],
            ]);

            $this->assertSame('ok', $out['status']);
            $this->assertNotEmpty($out['result']['approval_id'] ?? null);
            $this->assertStringContainsString('BETA', (string) ($out['result']['diff'] ?? ''));
        } finally {
            @unlink($abs);
        }
    }

    #[Test]
    public function run_command_executes_allowlisted_command(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'run_command',
            'payload' => ['command' => 'git status'],
        ]);

        $this->assertSame('ok', $out['status']);
        $this->assertSame('git status', $out['result']['command']);
        $this->assertArrayHasKey('exit_code', $out['result']);
    }

    #[Test]
    public function run_command_blocks_forbidden_command(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'run_command',
            'payload' => ['command' => 'rm -rf /'],
        ]);

        // The runner refuses non-allowlisted/destructive commands and never executes them.
        $this->assertSame('ok', $out['status']);
        $this->assertTrue($out['result']['skipped'] ?? false);
        $this->assertFalse($out['result']['ok']);
    }

    #[Test]
    public function read_only_role_cannot_run_commands(): void
    {
        $registry = app(ToolRegistry::class);
        $out = $registry->invoke(null, null, [
            'tool' => 'run_command',
            'payload' => ['command' => 'git status'],
        ], null, 'auditor');

        $this->assertSame('blocked', $out['status']);
    }
}
