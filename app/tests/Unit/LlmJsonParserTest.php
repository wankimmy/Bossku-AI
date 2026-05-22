<?php

namespace Tests\Unit;

use App\Support\LlmJsonParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LlmJsonParserTest extends TestCase
{
    #[Test]
    public function it_parses_fenced_json(): void
    {
        $raw = "```json\n{\"status\":\"success\",\"patch_summary\":\"done\"}\n```";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('success', $out['data']['status'] ?? null);
    }

    #[Test]
    public function it_parses_json_after_prose(): void
    {
        $raw = "Here is the result:\n{\"status\":\"partial\",\"summary\":\"Updated routes\"}\nThanks.";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('partial', $out['data']['status'] ?? null);
    }

    #[Test]
    public function it_fails_on_invalid_json(): void
    {
        $out = LlmJsonParser::parseObject('{"status": "broken"');

        $this->assertFalse($out['ok']);
        $this->assertSame('parse', $out['error']);
    }

    #[Test]
    public function it_fails_on_empty(): void
    {
        $out = LlmJsonParser::parseObject('   ');

        $this->assertFalse($out['ok']);
        $this->assertSame('empty', $out['error']);
    }
}
