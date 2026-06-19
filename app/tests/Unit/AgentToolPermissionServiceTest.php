<?php

namespace Tests\Unit;

use App\Services\Agents\AgentToolPermissionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentToolPermissionServiceTest extends TestCase
{
    #[Test]
    public function direct_answer_cannot_write_files_or_query_db(): void
    {
        $service = app(AgentToolPermissionService::class);
        $allowed = $service->allowedTools('direct_answer');

        $this->assertContains('file_read_safe', $allowed);
        $this->assertNotContains('file_write_proposed', $allowed);
        $this->assertNotContains('db_query', $allowed);
    }

    #[Test]
    public function planner_is_read_only(): void
    {
        $service = app(AgentToolPermissionService::class);
        $allowed = $service->allowedTools('planner');

        $this->assertNotContains('file_write_proposed', $allowed);
        $this->assertNotContains('db_query', $allowed);
    }

    #[Test]
    public function seo_writer_cannot_write_code_files(): void
    {
        $service = app(AgentToolPermissionService::class);

        $this->assertFalse($service->isAllowed('seo-writer', 'file_write_proposed'));
        $this->assertFalse($service->isAllowed('seo-writer', 'db_query'));
    }

    #[Test]
    public function format_tools_block_lists_only_allowed_tools(): void
    {
        $block = app(AgentToolPermissionService::class)->formatToolsBlock('direct_answer');

        $this->assertStringContainsString('file_read_safe', $block);
        $this->assertStringNotContainsString('file_write_proposed', $block);
        $this->assertStringNotContainsString('memory', $block);
        $this->assertStringNotContainsString('docs_lookup', $block);
    }

    #[Test]
    public function writer_cannot_propose_file_writes(): void
    {
        $service = app(AgentToolPermissionService::class);

        $this->assertFalse($service->isAllowed('writer', 'file_write_proposed'));
        $this->assertFalse($service->isAllowed('writer', 'db_query'));
    }
}
