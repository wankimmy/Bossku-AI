<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorResponseParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorResponseParserTest extends TestCase
{
    #[Test]
    public function it_accepts_summary_without_patch_summary(): void
    {
        $decoded = ['summary' => 'Updated API routes'];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded));
        $normalized = ExecutorResponseParser::normalize($decoded);

        $this->assertSame('success', $normalized['status']);
        $this->assertSame('Updated API routes', $normalized['patch_summary']);
    }

    #[Test]
    public function it_defaults_status_when_only_patch_summary_present(): void
    {
        $decoded = ['patch_summary' => 'Done'];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded));
        $normalized = ExecutorResponseParser::normalize($decoded);

        $this->assertSame('success', $normalized['status']);
        $this->assertSame('Done', $normalized['patch_summary']);
    }
}
