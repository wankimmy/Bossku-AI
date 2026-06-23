<?php

namespace App\Services\Agents;

/**
 * An ordered list of PermissionRules with last-match-wins evaluation. Ported
 * from opencode's PermissionV1.Ruleset. Compose rules in order; the evaluator
 * walks them and the last matching rule's action wins. If no rule matches,
 * the default action is returned (caller chooses; typically 'allow' for
 * read-only tools and 'deny' for write tools).
 *
 * This is the structured successor to AgentToolPermissionService's static
 * deniedByRole array. The ruleset can be built from agent frontmatter, from
 * config, or programmatically; the evaluator is the single decision point.
 */
final class PermissionRuleset
{
    /** @var list<PermissionRule> */
    private array $rules = [];

    /** @param  list<PermissionRule>  $rules */
    public function __construct(array $rules = [])
    {
        foreach ($rules as $rule) {
            $this->add($rule);
        }
    }

    public function add(PermissionRule $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    /** @return list<PermissionRule> */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Decide the action for a tool+path. Last matching rule wins; if none
     * match, return the caller-supplied default.
     *
     * @param  PermissionRule::ACTION_*  $default
     * @return PermissionRule::ACTION_*
     */
    public function evaluate(string $tool, ?string $path, string $default): string
    {
        $action = $default;
        foreach ($this->rules as $rule) {
            if ($rule->matches($tool, $path)) {
                $action = $rule->action;
            }
        }

        return $action;
    }

    /**
     * Convenience: is the tool allowed (without an ask) for the given path?
     */
    public function isAllowed(string $tool, ?string $path = null): bool
    {
        return $this->evaluate($tool, $path, PermissionRule::ACTION_ALLOW) === PermissionRule::ACTION_ALLOW;
    }
}