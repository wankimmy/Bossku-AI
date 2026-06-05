<?php

namespace Tests\Unit;

use App\Services\BosskuAi\RiskRuleEngine;
use App\Services\Governance\RiskClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskClassifierTest extends TestCase
{
    private function makeClassifier(): RiskClassifier
    {
        return new RiskClassifier(new RiskRuleEngine());
    }

    #[Test]
    public function buy_coffee_is_low_risk(): void
    {
        $this->assertSame('low', $this->makeClassifier()->classify('buy coffee'));
    }

    #[Test]
    public function update_stripe_payment_webhook_is_critical(): void
    {
        $this->assertSame('critical', $this->makeClassifier()->classify('update stripe payment webhook'));
    }

    #[Test]
    public function add_index_on_users_table_migration_is_high(): void
    {
        $this->assertSame('high', $this->makeClassifier()->classify('add index on users table migration'));
    }

    #[Test]
    public function review_auth_middleware_security_is_high(): void
    {
        $result = $this->makeClassifier()->classify('review auth middleware security');
        $this->assertContains($result, ['high', 'critical']);
    }

    #[Test]
    public function npm_install_lodash_is_medium(): void
    {
        $this->assertSame('medium', $this->makeClassifier()->classify('npm install lodash'));
    }

    #[Test]
    #[DataProvider('riskLevelProvider')]
    public function classifier_returns_expected_level(string $prompt, string $expected): void
    {
        $result = $this->makeClassifier()->classify($prompt);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function riskLevelProvider(): array
    {
        return [
            'buy coffee → low'              => ['buy coffee', 'low'],
            'npm install lodash → medium'   => ['npm install lodash', 'medium'],
        ];
    }
}
