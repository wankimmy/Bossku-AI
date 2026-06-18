<?php

namespace Tests\Unit;

use App\Services\Llm\ModelAutoSelectorService;
use App\Services\Llm\ProviderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelAutoSelectorServiceTest extends TestCase
{
    #[Test]
    public function recommends_coding_models_for_executor_role(): void
    {
        $factory = $this->createMock(ProviderFactory::class);
        $factory->method('isProviderConfigured')->willReturn(true);

        $selector = new ModelAutoSelectorService($factory);
        $results = $selector->recommendForRole('executor', 'moonshot', 3);

        $this->assertNotEmpty($results);
        $this->assertTrue($results[0]['auto_selected']);
        $this->assertStringContainsString('kimi', strtolower($results[0]['id']));
    }

    #[Test]
    public function skips_unconfigured_providers_in_cloud_list(): void
    {
        $factory = $this->createMock(ProviderFactory::class);
        $factory->method('isProviderConfigured')->willReturnCallback(
            fn (string $slug): bool => $slug === 'ollama-cloud',
        );

        $selector = new ModelAutoSelectorService($factory);
        $providers = $selector->cloudProvidersForRole('orchestrator', 2);

        $configured = array_filter($providers, fn (array $p): bool => $p['configured']);
        $this->assertCount(1, $configured);
        $this->assertSame('ollama-cloud', array_values($configured)[0]['provider']);
    }

    #[Test]
    public function auto_select_returns_top_model_id(): void
    {
        $factory = $this->createMock(ProviderFactory::class);
        $factory->method('isProviderConfigured')->willReturn(true);

        $selector = new ModelAutoSelectorService($factory);
        $modelId = $selector->autoSelectModel('orchestrator', 'anthropic');

        $this->assertNotNull($modelId);
        $this->assertStringStartsWith('claude-', $modelId);
    }
}
