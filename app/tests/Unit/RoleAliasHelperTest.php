<?php

namespace Tests\Unit;

use App\Services\Llm\RoleAliasHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleAliasHelperTest extends TestCase
{
    #[Test]
    public function normalizes_planner_to_orchestrator(): void
    {
        $this->assertSame('orchestrator', RoleAliasHelper::normalize('planner'));
    }

    #[Test]
    public function normalizes_coder_to_executor(): void
    {
        $this->assertSame('executor', RoleAliasHelper::normalize('coder'));
    }

    #[Test]
    public function variants_include_alias_and_canonical(): void
    {
        $variants = RoleAliasHelper::variants('planner');
        $this->assertContains('planner', $variants);
        $this->assertContains('orchestrator', $variants);
    }
}
