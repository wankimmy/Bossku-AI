<?php

namespace Tests\Unit;

use App\Models\BosskuAi\AgentPersona;
use App\Services\BosskuAi\AgentPersonaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentPersonaServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function append_prepends_when_enabled_with_content(): void
    {
        AgentPersona::query()->create([
            'role' => 'executor',
            'display_name' => 'Executor',
            'content' => 'Be terse.',
            'enabled' => true,
        ]);

        $svc = app(AgentPersonaService::class);
        $out = $svc->appendToSystem('executor', 'Built-in rules.');

        $this->assertStringContainsString('## Agent persona (Executor)', $out);
        $this->assertStringContainsString('Be terse.', $out);
        $this->assertStringContainsString('Built-in rules.', $out);
    }

    #[Test]
    public function append_skips_when_disabled(): void
    {
        AgentPersona::query()->create([
            'role' => 'executor',
            'display_name' => 'Executor',
            'content' => 'Ignored.',
            'enabled' => false,
        ]);

        $svc = app(AgentPersonaService::class);
        $this->assertSame('Built-in only.', $svc->appendToSystem('executor', 'Built-in only.'));
    }

    #[Test]
    public function apply_to_messages_mutates_system_role(): void
    {
        AgentPersona::query()->create([
            'role' => 'router',
            'display_name' => 'Router',
            'content' => 'Route carefully.',
            'enabled' => true,
        ]);

        $svc = app(AgentPersonaService::class);
        $messages = [
            ['role' => 'system', 'content' => 'Base.'],
            ['role' => 'user', 'content' => 'Hi'],
        ];
        $out = $svc->applyToMessages('router', $messages);

        $this->assertStringContainsString('Route carefully.', $out[0]['content']);
    }

    #[Test]
    public function wrap_handoff_adds_framing(): void
    {
        $svc = app(AgentPersonaService::class);
        $wrapped = $svc->wrapHandoffUserContent('executor', 'orchestrator', 'Go build it.', '{"task":"x"}');

        $this->assertStringContainsString('## Handoff: orchestrator → executor', $wrapped);
        $this->assertStringContainsString('Go build it.', $wrapped);
        $this->assertStringContainsString('{"task":"x"}', $wrapped);
    }

    #[Test]
    public function normalize_role_maps_aliases(): void
    {
        $svc = app(AgentPersonaService::class);
        $this->assertSame('orchestrator', $svc->normalizeRole('planner'));
        $this->assertSame('security_auditor', $svc->normalizeRole('security-auditor'));
    }
}
