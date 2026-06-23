<?php

namespace App\Services\BosskuAi\Hooks;

/**
 * Runtime hook controls via environment variables. Ported from ECC's
 * ECC_HOOK_PROFILE / ECC_DISABLED_HOOKS pattern. Lets operators gate hook
 * strictness and disable specific hooks without editing files.
 *
 * Env vars:
 *   BOSSKU_HOOK_PROFILE=minimal|standard|strict  (default: standard)
 *   BOSSKU_DISABLED_HOOKS=tool.definition,permission.ask  (comma-separated)
 *
 * Profiles:
 *   minimal  — only tool.execute.before/after (safety-critical hooks)
 *   standard — all hooks enabled (default)
 *   strict   — all hooks + extra validation (hooks may not silently swallow errors)
 */
final class HookProfile
{
    public const MINIMAL = 'minimal';

    public const STANDARD = 'standard';

    public const STRICT = 'strict';

    /** @var list<string> hooks that run in each profile */
    private const PROFILE_HOOKS = [
        self::MINIMAL => ['tool.execute.before', 'tool.execute.after'],
        self::STANDARD => ['tool.definition', 'tool.execute.before', 'tool.execute.after', 'permission.ask', 'command.execute.before', 'chat.system.transform', 'chat.messages.transform', 'session.compacting', 'compaction.autocontinue'],
        self::STRICT => ['tool.definition', 'tool.execute.before', 'tool.execute.after', 'permission.ask', 'command.execute.before', 'chat.system.transform', 'chat.messages.transform', 'session.compacting', 'compaction.autocontinue'],
    ];

    public static function current(): string
    {
        $profile = strtolower((string) env('BOSSKU_HOOK_PROFILE', self::STANDARD));

        return in_array($profile, [self::MINIMAL, self::STANDARD, self::STRICT], true) ? $profile : self::STANDARD;
    }

    /**
     * Is this hook enabled given the current profile and the disabled list?
     */
    public static function isEnabled(string $hook): bool
    {
        $disabled = self::disabledHooks();
        if (in_array($hook, $disabled, true)) {
            return false;
        }

        $profile = self::current();
        $allowed = self::PROFILE_HOOKS[$profile] ?? self::PROFILE_HOOKS[self::STANDARD];

        return in_array($hook, $allowed, true);
    }

    /** @return list<string> */
    public static function disabledHooks(): array
    {
        $raw = (string) env('BOSSKU_DISABLED_HOOKS', '');

        return $raw === '' ? [] : array_map('trim', explode(',', $raw));
    }

    /** @return list<string> hooks active in the current profile (minus disabled) */
    public static function activeHooks(): array
    {
        $profile = self::current();
        $allowed = self::PROFILE_HOOKS[$profile] ?? self::PROFILE_HOOKS[self::STANDARD];
        $disabled = self::disabledHooks();

        return array_values(array_filter($allowed, fn (string $h) => ! in_array($h, $disabled, true)));
    }
}