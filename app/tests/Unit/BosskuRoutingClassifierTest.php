<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\PromptRouteClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BosskuRoutingClassifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests are deterministic: don't depend on container .env BOSSKU_* or live LLM router responses.
        Setting::query()->delete();
        Setting::setValue('routing_llm_enabled', '0');
        config([
            'bossku_models.router.enabled' => false,
            'bossku_models.executor.default.primary' => 'glm-5.1',
            'bossku_models.executor.high_risk.primary' => 'deepseek-v4-pro',
        ]);
    }

    private function classify(string $prompt): array
    {
        /** @var PromptRouteClassifier $c */
        $c = app(PromptRouteClassifier::class);
        $out = $c->classify($prompt);

        return $out['route'];
    }

    #[Test]
    public function repo_audit_prompt_requires_executor_and_auditor(): void
    {
        $r = $this->classify('help me audit splitlah repo, is there any features u think need to add ? audit full');
        $this->assertStringContainsString('orchestrator_executor', (string) $r['workflow']);
        $this->assertTrue($r['needs_executor']);
        $this->assertTrue($r['needs_auditor']);
        $this->assertTrue($r['needs_repo_context']);
    }

    #[Test]
    public function full_repo_audit_enables_multi_dimension_and_security_pass(): void
    {
        $r = $this->classify('help me audit splitlah repo, is there any features u think need to add ? audit full');
        $this->assertSame('full', $r['audit_mode']);
        $this->assertTrue($r['needs_security_auditor']);
        $this->assertStringContainsString('security', (string) $r['workflow']);
    }

    #[Test]
    public function security_only_audit_skips_full_mode(): void
    {
        $r = $this->classify('Run OWASP security audit on authentication endpoints');
        $this->assertNotSame('full', $r['audit_mode'] ?? 'full');
    }

    #[Test]
    public function repo_understanding_prompt_is_read_only_no_audit_pipeline(): void
    {
        $r = $this->classify('Inspect the active repository and summarize the project purpose, structure, conventions, stack, and risks. Do not edit files.');
        $this->assertSame('orchestrator_only', $r['workflow']);
        $this->assertSame('bosskuai-project-understanding', $r['skill']);
        $this->assertTrue($r['needs_repo_context']);
        $this->assertFalse($r['needs_file_edit']);
        $this->assertFalse($r['needs_executor']);
        $this->assertFalse($r['needs_auditor']);
        $this->assertFalse($r['needs_security_auditor']);
        $this->assertFalse($r['needs_final_reviewer']);
    }

    #[Test]
    public function review_request_still_audits_even_with_summarize_wording(): void
    {
        // "review" is an audit verb — it must NOT be misread as read-only understanding.
        $r = $this->classify('Review the repo and summarize the findings');
        $this->assertStringContainsString('orchestrator_executor', (string) $r['workflow']);
        $this->assertTrue($r['needs_executor']);
        $this->assertTrue($r['needs_auditor']);
    }

    #[Test]
    public function smoke_test_prompt_uses_direct_answer(): void
    {
        $r = $this->classify('test');
        $this->assertSame('direct_answer', $r['workflow']);
        $this->assertFalse($r['needs_executor']);
        $this->assertFalse($r['needs_auditor']);
    }

    #[Test]
    public function short_low_risk_prompts_skip_the_llm_router_when_heuristics_are_decisive(): void
    {
        Setting::setValue('routing_llm_enabled', '1');
        config(['bossku_models.router.enabled' => true]);

        $fallback = $this->createMock(ModelFallbackService::class);
        $fallback->expects($this->never())->method('chatWithFallbacks');
        $this->app->instance(ModelFallbackService::class, $fallback);

        $r = $this->classify('test');

        $this->assertSame('direct_answer', $r['workflow']);
        $this->assertSame('low', $r['risk_level']);
    }

    #[Test]
    public function laravel_question_is_direct_answer_low_risk(): void
    {
        $r = $this->classify('Explain Laravel policy vs gate');
        $this->assertSame('question', $r['task_type']);
        $this->assertSame('direct_answer', $r['workflow']);
        $this->assertFalse($r['needs_executor']);
        $this->assertFalse($r['needs_auditor']);
        $this->assertFalse($r['needs_security_auditor']);
        $this->assertFalse($r['needs_final_reviewer']);
    }

    #[Test]
    public function mobile_button_spacing_uses_frontend_executor(): void
    {
        $r = $this->classify('Fix button spacing in dashboard mobile view');
        $this->assertSame('ui_ux', $r['task_type']);
        $this->assertSame('frontend_ui', $r['executor_profile']);
        $this->assertTrue($r['needs_executor']);
        $this->assertFalse($r['needs_auditor']);
        $this->assertFalse($r['needs_final_reviewer']);
    }

    #[Test]
    public function simple_file_create_skips_auditor(): void
    {
        $r = $this->classify('Create hello-world.txt with Hello World');
        $this->assertSame('orchestrator_executor', $r['workflow']);
        $this->assertTrue($r['needs_executor']);
        $this->assertFalse($r['needs_auditor']);
        $this->assertFalse($r['needs_security_auditor']);
    }

    #[Test]
    public function validation_typo_skips_auditor(): void
    {
        $r = $this->classify('Fix Laravel validation message typo');
        $this->assertFalse($r['needs_auditor']);
    }

    #[Test]
    public function payment_webhook_is_high_risk_full_chain(): void
    {
        $r = $this->classify('Fix payment webhook signature validation');
        $this->assertSame('payment', $r['task_type']);
        $this->assertSame('high', $r['risk_level']);
        $this->assertTrue($r['needs_security_auditor']);
        $this->assertTrue($r['needs_final_reviewer']);
        $this->assertSame('orchestrator_executor_auditor_security_final_reviewer', $r['workflow']);
    }

    #[Test]
    public function auth_middleware_is_high_risk(): void
    {
        $r = $this->classify('Refactor user authentication middleware');
        $this->assertSame('authentication', $r['task_type']);
        $this->assertSame('high', $r['risk_level']);
        $this->assertSame('high_risk', $r['executor_profile']);
    }

    #[Test]
    public function readme_update_low_risk_writer_or_orchestrator(): void
    {
        $r = $this->classify('Update README installation guide');
        $this->assertSame('documentation', $r['task_type']);
        $this->assertFalse($r['needs_final_reviewer']);
        $this->assertContains($r['workflow'], ['writer_only', 'orchestrator_only', 'direct_answer']);
    }

    #[Test]
    public function subscription_migration_high_risk(): void
    {
        $r = $this->classify('Create database migration for subscriptions table');
        $this->assertSame('database', $r['task_type']);
        $this->assertSame('high', $r['risk_level']);
        $this->assertTrue($r['needs_final_reviewer']);
    }

    #[Test]
    public function validation_typo_medium_backend(): void
    {
        $r = $this->classify('Fix Laravel validation message typo');
        $this->assertContains($r['executor_profile'], ['backend', 'default']);
        $this->assertFalse($r['needs_final_reviewer']);
    }

    #[Test]
    public function redis_optimization_medium(): void
    {
        $r = $this->classify('Optimize Redis cache usage for product listing');
        $this->assertSame('redis', $r['skill']);
        $this->assertSame('backend', $r['executor_profile']);
        $this->assertFalse($r['needs_final_reviewer']);
    }

    #[Test]
    public function deploy_production_high_risk(): void
    {
        $r = $this->classify('Deploy this Docker app to production with Nginx and SSL');
        $this->assertSame('deployment', $r['task_type']);
        $this->assertSame('high', $r['risk_level']);
        $this->assertContains($r['executor_profile'], ['devops', 'high_risk']);
    }

    #[Test]
    public function marketing_post_writer_only(): void
    {
        $r = $this->classify('Write social media post for Acme vendor signup');
        $this->assertSame('marketing', $r['task_type']);
        $this->assertSame('writer_only', $r['workflow']);
        $this->assertFalse($r['needs_executor']);
    }

    #[Test]
    public function default_executor_model_matches_config_default(): void
    {
        $cfg = app(ModelRoutingConfig::class);
        $expected = strtolower((string) config('bossku_models.defaults.executor_default', ''));
        $this->assertSame($expected, strtolower((string) $cfg->executorProfile('default')['primary']));
        $this->assertStringNotContainsString('gpt-5.5', strtolower((string) $cfg->executorProfile('default')['primary']));
    }

    #[Test]
    public function high_risk_executor_matches_config_default(): void
    {
        $cfg = app(ModelRoutingConfig::class);
        $expected = strtolower((string) config('bossku_models.executor.high_risk.primary', config('bossku_models.defaults.executor_high_risk')));
        $this->assertSame($expected, strtolower((string) $cfg->executorProfile('high_risk')['primary']));
    }

    #[DataProvider('riskUpgradeProvider')]
    #[Test]
    public function deterministic_keywords_upgrade_risk(string $prompt, string $expectedMin): void
    {
        $r = $this->classify($prompt);
        $order = ['low' => 0, 'medium' => 1, 'high' => 2];
        $this->assertGreaterThanOrEqual($order[$expectedMin], $order[$r['risk_level']]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function riskUpgradeProvider(): array
    {
        return [
            'api medium' => ['Change API validation for orders', 'medium'],
            'payment high' => ['Update subscription billing checkout', 'high'],
        ];
    }
}
