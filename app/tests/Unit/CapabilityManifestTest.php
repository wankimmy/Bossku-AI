<?php

namespace Tests\Unit;

use App\Services\Agents\Capabilities\Capability;
use App\Services\Agents\Capabilities\CapabilityDeniedException;
use App\Services\Agents\Capabilities\CapabilityManifest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the capability-gated host services model. Proves: broader
 * declared capabilities satisfy narrower required ones (prefix matching),
 * undeclared capabilities are denied (least-privilege), and the assert gate
 * throws with a useful message.
 */
class CapabilityManifestTest extends TestCase
{
    #[Test]
    public function exact_capability_match_is_allowed(): void
    {
        $manifest = new CapabilityManifest([new Capability('file.read')]);

        $this->assertTrue($manifest->allows('file.read'));
    }

    #[Test]
    public function broader_declared_satisfies_narrower_required(): void
    {
        $manifest = new CapabilityManifest([new Capability('command.run')]);

        $this->assertTrue($manifest->allows('command.run'));
        $this->assertTrue($manifest->allows('command.run.sudo'));
        $this->assertTrue($manifest->allows('command.run.docker'));
    }

    #[Test]
    public function narrower_declared_does_not_satisfy_broader_required(): void
    {
        $manifest = new CapabilityManifest([new Capability('command.run.sudo')]);

        $this->assertTrue($manifest->allows('command.run.sudo'));
        $this->assertFalse($manifest->allows('command.run'));
    }

    #[Test]
    public function undeclared_capability_is_denied(): void
    {
        $manifest = new CapabilityManifest([new Capability('file.read')]);

        $this->assertFalse($manifest->allows('file.write'));
        $this->assertFalse($manifest->allows('command.run'));
        $this->assertFalse($manifest->allows('db.query'));
    }

    #[Test]
    public function empty_manifest_denies_everything(): void
    {
        $manifest = new CapabilityManifest;

        $this->assertFalse($manifest->allows('file.read'));
        $this->assertFalse($manifest->allows('command.run'));
    }

    #[Test]
    public function assert_passes_for_allowed_capability(): void
    {
        $manifest = new CapabilityManifest(['file.read', 'file.write']);

        // Should not throw.
        $manifest->assert('file.read', 'executor');
        $manifest->assert('file.write', 'executor');

        $this->assertTrue(true); // assertion passed if we got here
    }

    #[Test]
    public function assert_throws_for_denied_capability(): void
    {
        $manifest = new CapabilityManifest(['file.read']);

        $this->expectException(CapabilityDeniedException::class);
        $this->expectExceptionMessage("Capability denied: agent 'planner' attempted 'file.write'");

        $manifest->assert('file.write', 'planner');
    }

    #[Test]
    public function exception_carries_declared_list_for_debugging(): void
    {
        $manifest = new CapabilityManifest(['file.read', 'file.search']);

        try {
            $manifest->assert('file.write', 'auditor');
            $this->fail('Expected exception');
        } catch (CapabilityDeniedException $e) {
            $this->assertSame('file.write', $e->requiredCapability);
            $this->assertSame('auditor', $e->agentRole);
            $this->assertSame(['file.read', 'file.search'], $e->declared);
        }
    }

    #[Test]
    public function string_capabilities_are_accepted_in_constructor(): void
    {
        $manifest = new CapabilityManifest(['file.read', 'command.run']);

        $this->assertTrue($manifest->allows('file.read'));
        $this->assertTrue($manifest->allows('command.run.sudo'));
    }

    #[Test]
    public function executor_typical_manifest(): void
    {
        $manifest = new CapabilityManifest([
            'file.read', 'file.write', 'file.edit', 'command.run', 'db.query', 'log',
        ]);

        $this->assertTrue($manifest->allows('file.read'));
        $this->assertTrue($manifest->allows('file.write'));
        $this->assertTrue($manifest->allows('file.edit'));
        $this->assertTrue($manifest->allows('command.run'));
        $this->assertTrue($manifest->allows('command.run.sudo'));
        $this->assertTrue($manifest->allows('db.query'));
        $this->assertTrue($manifest->allows('log'));
        $this->assertFalse($manifest->allows('mcp.call'));
    }

    #[Test]
    public function planner_is_read_only(): void
    {
        $manifest = new CapabilityManifest(['file.read', 'file.search', 'file.glob', 'log']);

        $this->assertTrue($manifest->allows('file.read'));
        $this->assertFalse($manifest->allows('file.write'));
        $this->assertFalse($manifest->allows('file.edit'));
        $this->assertFalse($manifest->allows('command.run'));
    }

    #[Test]
    public function capability_can_be_single_word(): void
    {
        $manifest = new CapabilityManifest(['log']);

        $this->assertTrue($manifest->allows('log'));
    }

    #[Test]
    public function declared_returns_all_capability_names(): void
    {
        $manifest = new CapabilityManifest(['file.read', 'command.run']);

        $this->assertSame(['file.read', 'command.run'], $manifest->declared());
    }
}