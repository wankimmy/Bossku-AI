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
    public function it_parses_json_in_a_mislabelled_text_fence(): void
    {
        // A model wraps JSON (sometimes after the [BOSSKUAI] header) in a ```text fence.
        $raw = "```text\n[BOSSKUAI]\nAgent: executor\n{\"status\":\"success\",\"summary\":\"ok\"}\n```";
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
    public function it_fails_when_no_object_is_present(): void
    {
        $out = LlmJsonParser::parseObject('Sorry, I could not complete that request.');

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
    public function it_recovers_object_truncated_after_a_complete_value(): void
    {
        // Output token budget exhausted mid-object: the closing brace never arrives.
        $out = LlmJsonParser::parseObject('{"status":"partial","summary":"Updated routes"');

        $this->assertTrue($out['ok']);
        $this->assertSame('partial', $out['data']['status'] ?? null);
        $this->assertSame('Updated routes', $out['data']['summary'] ?? null);
    }

    #[Test]
    public function it_recovers_when_cut_off_mid_string(): void
    {
        $out = LlmJsonParser::parseObject('{"summary":"this answer was cut off right here');

        $this->assertTrue($out['ok']);
        $this->assertSame('this answer was cut off right here', $out['data']['summary'] ?? null);
    }

    #[Test]
    public function it_recovers_truncated_nested_structure_and_drops_dangling_key(): void
    {
        $raw = '{"task_summary":"do x","checklist":[{"id":"1","title":"a"},{"id":"2","title":"b"}],"handoff_message"';
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('do x', $out['data']['task_summary'] ?? null);
        $this->assertIsArray($out['data']['checklist'] ?? null);
        $this->assertCount(2, $out['data']['checklist']);
        $this->assertArrayNotHasKey('handoff_message', $out['data']);
    }

    #[Test]
    public function it_strips_trailing_commas(): void
    {
        $out = LlmJsonParser::parseObject("{\"status\":\"success\",\"items\":[1,2,3,],}");

        $this->assertTrue($out['ok']);
        $this->assertSame('success', $out['data']['status'] ?? null);
        $this->assertSame([1, 2, 3], $out['data']['items'] ?? null);
    }

    #[Test]
    public function it_supplies_null_for_a_dangling_key_with_colon(): void
    {
        $out = LlmJsonParser::parseObject('{"status":"partial","patch_summary":');

        $this->assertTrue($out['ok']);
        $this->assertSame('partial', $out['data']['status'] ?? null);
        $this->assertArrayHasKey('patch_summary', $out['data']);
        $this->assertNull($out['data']['patch_summary']);
    }

    #[Test]
    public function it_still_prefers_well_formed_json_over_recovery(): void
    {
        // A complete object must decode untouched even though extra prose follows.
        $raw = "{\"status\":\"success\",\"patch_summary\":\"done\"}\nTrailing note that is not JSON.";
        $out = LlmJsonParser::parseObject($raw);

        $this->assertTrue($out['ok']);
        $this->assertSame('success', $out['data']['status'] ?? null);
        $this->assertSame('done', $out['data']['patch_summary'] ?? null);
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
