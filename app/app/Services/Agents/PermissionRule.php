<?php

namespace App\Services\Agents;

/**
 * A single permission rule: allow / deny / ask a tool (with an optional path
 * pattern) for a given agent role. Ported from opencode's PermissionV1.Rule
 * model — last-match-wins wildcard evaluation.
 *
 * Rules compose into a Ruleset; the evaluator walks rules in order and the
 * final matching rule wins. This is more expressive than the static
 * deniedByRole array: a role can allow file_write in general but ask for
 * *.env files, deny db_query for the planner but allow it for the executor,
 * etc. The existing AgentToolPermissionService deniedByRole map is the seed;
 * this class is the structured successor.
 */
final class PermissionRule
{
    public const ACTION_ALLOW = 'allow';

    public const ACTION_DENY = 'deny';

    public const ACTION_ASK = 'ask';

    /** @var self::ACTION_* */
    public readonly string $action;

    /**
     * @param  self::ACTION_*  $action
     * @param  string  $tool  runtime tool name (file_edit, run_command, ...) or '*' for any
     * @param  string|null  $pattern  optional wildcard path pattern (e.g. '*.env', 'database/migrations/*')
     */
    public function __construct(
        public readonly string $tool,
        string $action,
        public readonly ?string $pattern = null,
    ) {
        if (! in_array($action, [self::ACTION_ALLOW, self::ACTION_DENY, self::ACTION_ASK], true)) {
            throw new \InvalidArgumentException("Invalid permission action: {$action}");
        }
        $this->action = $action;
    }

    /**
     * Does this rule apply to the given tool and path? A '*' tool matches any
     * tool; a null pattern matches any path; a pattern uses simple glob (* and ?).
     */
    public function matches(string $tool, ?string $path): bool
    {
        $toolOk = $this->tool === '*' || $this->tool === $tool;
        if (! $toolOk) {
            return false;
        }
        if ($this->pattern === null) {
            return true;
        }
        if ($path === null) {
            // A patterned rule with no path given matches only if the pattern is '*'.
            return $this->pattern === '*';
        }

        return self::wildcardMatch($this->pattern, $path);
    }

    /**
     * Simple glob: '*' = any sequence (incl '/'), '?' = single char. Case-insensitive.
     * Mirrors opencode's double-wildcard matching.
     */
    public static function wildcardMatch(string $pattern, string $subject): bool
    {
        $regex = '/^'.str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($pattern, '/'),
        ).'$/i';

        return (bool) preg_match($regex, $subject);
    }
}