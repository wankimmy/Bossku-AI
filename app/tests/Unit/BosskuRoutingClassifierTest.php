<?php

namespace Tests\Unit;

use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\PromptRouteClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BosskuRoutingClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests are deterministic: don't depend on container .env BOSSKU_* or live LLM router responses.
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
        $this->assertFalse($r['needs_final_reviewer']);
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
        $r = $this->classify('Write social media post for Festivent vendor signup');
        $this->assertSame('marketing', $r['task_type']);
        $this->assertSame('writer_only', $r['workflow']);
        $this->assertFalse($r['needs_executor']);
    }

    #[Test]
    public function default_executor_model_is_glm_not_gpt55(): void
    {
        $cfg = app(ModelRoutingConfig::class);
        $this->assertStringContainsString('glm', strtolower((string) $cfg->executorProfile('default')['primary']));
        $this->assertStringNotContainsString('gpt-5.5', strtolower((string) $cfg->executorProfile('default')['primary']));
    }

    #[Test]
    public function high_risk_executor_uses_deepseek(): void
    {
        $cfg = app(ModelRoutingConfig::class);
        $this->assertStringContainsString('deepseek', strtolower((string) $cfg->executorProfile('high_risk')['primary']));
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
