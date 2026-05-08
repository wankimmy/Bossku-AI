<?php

namespace Tests\Unit;

use App\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
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
}
