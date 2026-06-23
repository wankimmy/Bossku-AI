<?php

namespace Tests\Unit;

use App\Services\Agents\AgentToolPermissionService;
use App\Services\Agents\BashArity;
use App\Services\Agents\PermissionRule;
use App\Services\Agents\PermissionRuleset;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the opencode-style permission ruleset (last-match-wins wildcard)
 * and the bash command arity helper. These cover the new structured permission
 * surface; the existing AgentToolPermissionServiceTest covers the legacy
 * allowedTools/isAllowed API that remains the primary entry point.
 */
class PermissionRulesetTest extends TestCase
{
    #[Test]
    public function empty_ruleset_returns_default(): void
    {
        $rs = new PermissionRuleset;

        $this->assertSame(PermissionRule::ACTION_ALLOW, $rs->evaluate('file_edit', null, PermissionRule::ACTION_ALLOW));
        $this->assertSame(PermissionRule::ACTION_DENY, $rs->evaluate('file_edit', null, PermissionRule::ACTION_DENY));
    }

    #[Test]
    public function last_matching_rule_wins(): void
    {
        $rs = (new PermissionRuleset)
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_ALLOW))
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_DENY));

        $this->assertSame(PermissionRule::ACTION_DENY, $rs->evaluate('file_edit', null, PermissionRule::ACTION_ALLOW));
    }

    #[Test]
    public function wildcard_tool_matches_any_tool(): void
    {
        $rs = (new PermissionRuleset)
            ->add(new PermissionRule('*', PermissionRule::ACTION_DENY))
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_ALLOW));

        $this->assertSame(PermissionRule::ACTION_ALLOW, $rs->evaluate('file_edit', null, PermissionRule::ACTION_ALLOW));
        $this->assertSame(PermissionRule::ACTION_DENY, $rs->evaluate('file_write_proposed', null, PermissionRule::ACTION_ALLOW));
    }

    #[Test]
    public function path_pattern_overrides_broader_rule(): void
    {
        // Executor can write files generally, but *.env triggers an ask.
        $rs = (new PermissionRuleset)
            ->add(new PermissionRule('file_write_proposed', PermissionRule::ACTION_ALLOW))
            ->add(new PermissionRule('file_write_proposed', PermissionRule::ACTION_ASK, '*.env'));

        $this->assertSame(PermissionRule::ACTION_ALLOW, $rs->evaluate('file_write_proposed', 'src/Controller.php', PermissionRule::ACTION_DENY));
        $this->assertSame(PermissionRule::ACTION_ASK, $rs->evaluate('file_write_proposed', '.env', PermissionRule::ACTION_DENY));
        $this->assertSame(PermissionRule::ACTION_ASK, $rs->evaluate('file_write_proposed', 'app/.env', PermissionRule::ACTION_DENY));
    }

    #[Test]
    public function migration_path_pattern(): void
    {
        $rs = (new PermissionRuleset)
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_ALLOW))
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_ASK, 'database/migrations/*'));

        $this->assertSame(PermissionRule::ACTION_ASK, $rs->evaluate('file_edit', 'database/migrations/2024_01_01_create_users.php', PermissionRule::ACTION_DENY));
        $this->assertSame(PermissionRule::ACTION_ALLOW, $rs->evaluate('file_edit', 'app/Models/User.php', PermissionRule::ACTION_DENY));
    }

    #[Test]
    public function wildcard_match_supports_glob_star_and_question(): void
    {
        $this->assertTrue(PermissionRule::wildcardMatch('*.env', '.env'));
        $this->assertTrue(PermissionRule::wildcardMatch('*.env', 'app/.env'));
        $this->assertTrue(PermissionRule::wildcardMatch('database/migrations/*', 'database/migrations/2024_create_users.php'));
        $this->assertTrue(PermissionRule::wildcardMatch('*.test.ts', 'foo.test.ts'));
        $this->assertFalse(PermissionRule::wildcardMatch('*.env', 'env.txt'));
        $this->assertTrue(PermissionRule::wildcardMatch('config/?.php', 'config/a.php'));
        $this->assertFalse(PermissionRule::wildcardMatch('config/?.php', 'config/ab.php'));
    }

    #[Test]
    public function is_allowed_helper(): void
    {
        $rs = (new PermissionRuleset)
            ->add(new PermissionRule('*', PermissionRule::ACTION_DENY))
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_ALLOW))
            ->add(new PermissionRule('file_edit', PermissionRule::ACTION_DENY, '*.env'));

        $this->assertTrue($rs->isAllowed('file_edit', 'src/x.php'));
        $this->assertFalse($rs->isAllowed('file_edit', '.env'));
        // file_write_proposed has no explicit allow, so the catch-all deny wins.
        $this->assertFalse($rs->isAllowed('file_write_proposed', 'x.php'));
    }

    #[Test]
    public function service_ruleset_for_role_mirrors_denied_by_role(): void
    {
        $service = app(AgentToolPermissionService::class);
        $rs = $service->rulesetForRole('planner');

        // Planner is read-only: file_edit and file_write_proposed are denied.
        $this->assertSame(PermissionRule::ACTION_DENY, $rs->evaluate('file_edit', null, PermissionRule::ACTION_ALLOW));
        $this->assertSame(PermissionRule::ACTION_DENY, $rs->evaluate('file_write_proposed', null, PermissionRule::ACTION_ALLOW));
        // Read tools are allowed.
        $this->assertSame(PermissionRule::ACTION_ALLOW, $rs->evaluate('file_read_safe', null, PermissionRule::ACTION_DENY));
        $this->assertSame(PermissionRule::ACTION_ALLOW, $rs->evaluate('file_search', null, PermissionRule::ACTION_DENY));
    }

    #[Test]
    public function service_decide_returns_ask_when_no_allow_rule(): void
    {
        $service = app(AgentToolPermissionService::class);

        // Executor: file_write_proposed is allowed (no path rule), so decide = allow.
        $this->assertSame(PermissionRule::ACTION_ALLOW, $service->decide('executor', 'file_write_proposed', 'src/x.php'));
        // Planner: file_write_proposed is denied.
        $this->assertSame(PermissionRule::ACTION_DENY, $service->decide('planner', 'file_write_proposed', 'src/x.php'));
        // Unknown tool: denied by default.
        $this->assertSame(PermissionRule::ACTION_DENY, $service->decide('executor', 'nonexistent_tool'));
    }

    #[Test]
    public function bash_arity_collapses_flags(): void
    {
        $this->assertSame('npm run test', BashArity::label('npm run test'));
        $this->assertSame('npm run test …', BashArity::label('npm run test --filter=Foo --bar'));
        $this->assertSame('php artisan test', BashArity::label('php artisan test'));
        // php artisan migrate has arity 3 (php|artisan|migrate), so flags collapse.
        $this->assertSame('php artisan migrate …', BashArity::label('php artisan migrate --force --seed'));
        $this->assertSame('git commit …', BashArity::label('git commit -m "fix: thing" --no-verify'));
        $this->assertSame('docker compose exec backend …', BashArity::label('docker compose exec backend php artisan test'));
        // composer require has arity 3, so the package name is kept and flags collapse.
        $this->assertSame('composer require laravel/sail …', BashArity::label('composer require laravel/sail --dev'));
        $this->assertSame('phpunit …', BashArity::label('phpunit tests/Unit/ExampleTest.php --filter=testFoo'));
        $this->assertSame('pest …', BashArity::label('pest --filter=testFoo'));
        $this->assertSame('git push …', BashArity::label('git push origin main --force-with-lease'));
    }

    #[Test]
    public function bash_arity_handles_unknown_command_gracefully(): void
    {
        $this->assertSame('mybin foo …', BashArity::label('mybin foo --flag'));
        // ls has arity 1, so args collapse.
        $this->assertSame('ls …', BashArity::label('ls -la /tmp'));
        $this->assertSame('', BashArity::label(''));
    }
}