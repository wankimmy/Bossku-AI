<?php

namespace App\Services\BosskuAi\Hooks;

/**
 * The plugin hook registry. Ported from opencode's Hooks surface
 * (packages/plugin/src/index.ts). Skills and plugins register handlers for
 * named hooks; the orchestrator triggers hooks at lifecycle points. Each
 * hook handler is `(input, output) => void` — it mutates output in place.
 *
 * This lets skills participate in orchestration rather than just being
 * read-only context injections. Examples:
 *   - bosskuai-laravel-tdd registers a tool.definition hook that rewrites the
 *     bash tool description to emphasize Pest commands.
 *   - bosskuai-rigorous-code-review registers a tool.execute.after hook that
 *     auto-runs after edit/write tools.
 *   - bosskuai-tdd-loop registers a permission.ask hook that auto-approves
 *     php artisan test.
 *
 * Hook names (ported from opencode, adapted for BosskuAI):
 *   tool.definition, tool.execute.before, tool.execute.after,
 *   permission.ask, command.execute.before, chat.system.transform,
 *   chat.messages.transform, session.compacting, compaction.autocontinue
 */
final class HookRegistry
{
    /** @var array<string, list<callable(array<string,mixed>, array<string,mixed>): void>> */
    private array $handlers = [];

    /**
     * Register a handler for a named hook. Handlers run in registration order.
     *
     * @param  string  $hook  the hook name (see class docstring)
     * @param  callable(array<string,mixed>, array<string,mixed>): void  $handler  (input, output) => void — mutates output in place
     */
    public function on(string $hook, callable $handler): self
    {
        $this->handlers[$hook][] = $handler;

        return $this;
    }

    /**
     * Trigger a hook. Each registered handler receives (input, output) and
     * may mutate output in place. The final output is returned.
     *
     * @param  string  $hook
     * @param  array<string, mixed>  $input  the event input (read-only context)
     * @param  array<string, mixed>  $output  the output to mutate
     * @return array<string, mixed> the mutated output
     */
    public function trigger(string $hook, array $input, array $output): array
    {
        foreach ($this->handlers[$hook] ?? [] as $handler) {
            $handler($input, $output);
        }

        return $output;
    }

    /** @return list<string> registered hook names */
    public function registeredHooks(): array
    {
        return array_keys($this->handlers);
    }

    /** @return int handler count for a hook */
    public function handlerCount(string $hook): int
    {
        return count($this->handlers[$hook] ?? []);
    }

    /** Remove all handlers (for testing). */
    public function clear(): void
    {
        $this->handlers = [];
    }
}