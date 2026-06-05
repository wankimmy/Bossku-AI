<?php

namespace Tests\Unit;

use App\Support\AgentTools;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentToolsTest extends TestCase
{
    #[Test]
    public function it_maps_roles_to_runtime_tools(): void
    {
        $tools = AgentTools::forRole('designer');

        $this->assertContains('file_write_proposed', $tools);
        $this->assertContains('file_read_safe', $tools);
    }

    #[Test]
    public function it_parses_yaml_frontmatter_tools_array(): void
    {
        $raw = <<<'MD'
---
name: planner
tools: ["Read", "Grep", "Glob", "memory"]
---

# Planner
MD;

        $this->assertSame(['Read', 'Grep', 'Glob', 'memory'], AgentTools::parseFrontmatterTools($raw));
    }

    #[Test]
    public function it_formats_tools_block_for_prompt_injection(): void
    {
        $block = AgentTools::formatToolsBlock('executor');

        $this->assertStringContainsString('## Allowed tools (executor)', $block);
        $this->assertStringContainsString('file_write_proposed', $block);
    }
}
