<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Hooks\HookRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the plugin Hooks surface. Proves: handler registration, trigger
 * order, output mutation in place, and the (input, output) => void contract.
 */
class HookRegistryTest extends TestCase
{
    #[Test]
    public function trigger_with_no_handlers_returns_output_unchanged(): void
    {
        $registry = new HookRegistry;
        $output = $registry->trigger('tool.definition', ['tool' => 'bash'], ['description' => 'Run a command']);

        $this->assertSame(['description' => 'Run a command'], $output);
    }

    #[Test]
    public function handler_mutates_output_in_place(): void
    {
        $registry = new HookRegistry;
        $registry->on('tool.definition', function (array $input, array &$output): void {
            $output['description'] .= ' (Pest preferred for PHP tests)';
        });

        $output = $registry->trigger('tool.definition', ['tool' => 'bash'], ['description' => 'Run a command']);

        $this->assertSame('Run a command (Pest preferred for PHP tests)', $output['description']);
    }

    #[Test]
    public function multiple_handlers_run_in_registration_order(): void
    {
        $registry = new HookRegistry;
        $registry->on('chat.system.transform', function (array $in, array &$out): void {
            $out['system'] .= ' [A]';
        });
        $registry->on('chat.system.transform', function (array $in, array &$out): void {
            $out['system'] .= ' [B]';
        });

        $output = $registry->trigger('chat.system.transform', [], ['system' => 'base']);

        $this->assertSame('base [A] [B]', $output['system']);
    }

    #[Test]
    public function handler_receives_input_context(): void
    {
        $registry = new HookRegistry;
        $captured = null;
        $registry->on('tool.execute.before', function (array $input, array &$output) use (&$captured): void {
            $captured = $input;
        });

        $registry->trigger('tool.execute.before', ['tool' => 'edit', 'path' => 'src/x.php'], ['approved' => false]);

        $this->assertSame(['tool' => 'edit', 'path' => 'src/x.php'], $captured);
    }

    #[Test]
    public function permission_ask_handler_can_auto_approve(): void
    {
        $registry = new HookRegistry;
        // Simulate the tdd-loop skill auto-approving php artisan test.
        $registry->on('permission.ask', function (array $input, array &$output): void {
            $command = $input['command'] ?? '';
            if (str_starts_with($command, 'php artisan test')) {
                $output['decision'] = 'allow';
            }
        });

        $output = $registry->trigger('permission.ask', ['command' => 'php artisan test'], ['decision' => 'ask']);

        $this->assertSame('allow', $output['decision']);
    }

    #[Test]
    public function tool_execute_after_handler_can_add_post_edit_check(): void
    {
        $registry = new HookRegistry;
        $registry->on('tool.execute.after', function (array $input, array &$output): void {
            if (($input['tool'] ?? '') === 'edit') {
                $output['post_actions'][] = 'run_lint';
            }
        });

        $output = $registry->trigger('tool.execute.after', ['tool' => 'edit', 'path' => 'x.php'], ['post_actions' => []]);

        $this->assertContains('run_lint', $output['post_actions']);
    }

    #[Test]
    public function registered_hooks_lists_hook_names(): void
    {
        $registry = new HookRegistry;
        $registry->on('tool.definition', fn () => null);
        $registry->on('permission.ask', fn () => null);

        $hooks = $registry->registeredHooks();

        $this->assertContains('tool.definition', $hooks);
        $this->assertContains('permission.ask', $hooks);
    }

    #[Test]
    public function handler_count_returns_per_hook_count(): void
    {
        $registry = new HookRegistry;
        $registry->on('tool.definition', fn () => null);
        $registry->on('tool.definition', fn () => null);
        $registry->on('permission.ask', fn () => null);

        $this->assertSame(2, $registry->handlerCount('tool.definition'));
        $this->assertSame(1, $registry->handlerCount('permission.ask'));
        $this->assertSame(0, $registry->handlerCount('nonexistent'));
    }

    #[Test]
    public function clear_removes_all_handlers(): void
    {
        $registry = new HookRegistry;
        $registry->on('tool.definition', fn () => null);
        $registry->clear();

        $this->assertSame(0, $registry->handlerCount('tool.definition'));
        $this->assertSame([], $registry->registeredHooks());
    }
}