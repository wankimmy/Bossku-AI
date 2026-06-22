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

    #[Test]
    public function executor_frontmatter_editor_aliases_map_to_runtime_tools(): void
    {
        $service = app(AgentToolPermissionService::class);
        $raw = <<<'MD'
---
name: executor
tools: ["Read", "Grep", "Glob", "Write", "db_query", "log"]
---
# Executor
MD;

        $allowed = $service->allowedTools('executor', $raw);

        $this->assertContains('file_read_safe', $allowed);
        $this->assertContains('file_search', $allowed);
        $this->assertContains('file_glob', $allowed);
        $this->assertContains('file_write_proposed', $allowed);
        $this->assertContains('db_query', $allowed);
        $this->assertContains('log', $allowed);
        $this->assertNotContains('Read', $allowed);
        $this->assertNotContains('Write', $allowed);
    }

    #[Test]
    public function planner_frontmatter_editor_aliases_remain_read_only(): void
    {
        $service = app(AgentToolPermissionService::class);
        $raw = <<<'MD'
---
name: planner
tools: ["Read", "Grep", "Glob", "db_query"]
---
# Planner
MD;

        $allowed = $service->allowedTools('planner', $raw);

        $this->assertContains('file_read_safe', $allowed);
        $this->assertContains('file_search', $allowed);
        $this->assertContains('file_glob', $allowed);
        $this->assertNotContains('file_write_proposed', $allowed);
        $this->assertNotContains('db_query', $allowed);
    }

    #[Test]
    public function unknown_editor_aliases_are_ignored(): void
    {
        $service = app(AgentToolPermissionService::class);
        $raw = <<<'MD'
---
name: executor
tools: ["Read", "Task", "Shell", "Bash", "log"]
---
# Executor
MD;

        $allowed = $service->allowedTools('executor', $raw);

        // "Task" is unknown and dropped; "Shell"/"Bash" both alias to run_command (deduped).
        $this->assertSame(['file_read_safe', 'run_command', 'log'], $allowed);
    }

    #[Test]
    public function edit_alias_maps_to_surgical_file_edit(): void
    {
        $service = app(AgentToolPermissionService::class);

        $this->assertSame(
            ['file_edit'],
            $service->normalizeTools(['Edit']),
        );
    }

    #[Test]
    public function write_alias_still_maps_to_file_write_proposed(): void
    {
        $service = app(AgentToolPermissionService::class);

        $this->assertSame(
            ['file_write_proposed'],
            $service->normalizeTools(['Write']),
        );
    }

    #[Test]
    public function executor_can_use_surgical_edit_but_read_only_roles_cannot(): void
    {
        $service = app(AgentToolPermissionService::class);

        $this->assertTrue($service->isAllowed('executor', 'file_edit'));
        $this->assertFalse($service->isAllowed('auditor', 'file_edit'));
        $this->assertFalse($service->isAllowed('planner', 'file_edit'));
    }
}
