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

    #[Test]
    public function it_parses_json_when_prose_contains_extra_braces(): void
    {
        $raw = "Note: use {path} and {id} placeholders.\n{\"status\":\"success\",\"patch_summary\":\"applied\"}\nDone.";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('success', $out['data']['status'] ?? null);
        $this->assertSame('applied', $out['data']['patch_summary'] ?? null);
    }

    #[Test]
    public function it_parses_valid_object_when_multiple_brace_sections_exist(): void
    {
        $raw = "Invalid fragment: {not json}\nValid:\n{\"status\":\"partial\",\"patch_summary\":\"ok\"}";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('partial', $out['data']['status'] ?? null);
    }

    #[Test]
    public function it_parses_fenced_json_with_trailing_commentary(): void
    {
        $raw = "```json\n{\"status\":\"success\",\"patch_summary\":\"done\"}\n```\nLet me know if you need more.";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('success', $out['data']['status'] ?? null);
    }

    #[Test]
    public function it_parses_json_inside_thinking_blocks_removed(): void
    {
        $raw = "<thinking>{\"status\":\"wrong\"}</thinking>\n{\"status\":\"success\",\"patch_summary\":\"real\"}";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('success', $out['data']['status'] ?? null);
    }
}
