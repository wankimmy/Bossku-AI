<?php

namespace Tests\Unit;

use App\Services\BosskuAi\DomainModelSelector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DomainModelSelectorTest extends TestCase
{
    private DomainModelSelector $selector;

    /** @var list<string> */
    private array $candidates = ['deepseek-v4-pro', 'glm-5.1', 'kimi-k2.6', 'qwen3-coder-next'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new DomainModelSelector();
        config(['bossku_models.domain_adaptive_reviewers' => true]);
    }

    #[Test]
    public function a_coding_heavy_domain_promotes_the_strongest_coder(): void
    {
        $ordered = $this->selector->order($this->candidates, 'frontend');

        $this->assertSame('qwen3-coder-next', $ordered[0]);
        $this->assertSameSize($this->candidates, $ordered, 'no candidate is dropped');
    }

    #[Test]
    public function a_reasoning_heavy_domain_keeps_the_deep_reasoner_first(): void
    {
        $ordered = $this->selector->order($this->candidates, 'security');

        $this->assertSame('deepseek-v4-pro', $ordered[0]);
    }

    #[Test]
    public function an_explicit_pin_is_never_demoted(): void
    {
        // glm-5.1 is the weakest coder yet stays first on a frontend change when pinned.
        $ordered = $this->selector->order(['glm-5.1', 'qwen3-coder-next', 'deepseek-v4-pro'], 'frontend', keepPrimary: true);

        $this->assertSame('glm-5.1', $ordered[0]);
        $this->assertSame('qwen3-coder-next', $ordered[1]);
    }

    #[Test]
    public function disabling_the_flag_preserves_the_static_order(): void
    {
        config(['bossku_models.domain_adaptive_reviewers' => false]);

        $this->assertSame($this->candidates, $this->selector->order($this->candidates, 'frontend'));
    }

    #[Test]
    public function complement_prefers_a_different_model_family(): void
    {
        $ordered = $this->selector->complementTo('deepseek-v4-pro', $this->candidates, 'security');

        $this->assertNotSame('deepseek-v4-pro', $ordered[0], 'a just-failed model should not lead the retry');
    }

    #[Test]
    public function domain_is_derived_from_the_executor_profile(): void
    {
        $this->assertSame('frontend', $this->selector->domainFor(['executor_profile' => 'frontend_ui']));
        $this->assertSame('devops', $this->selector->domainFor(['executor_profile' => 'devops']));
        $this->assertSame('security', $this->selector->domainFor(['executor_profile' => 'high_risk']));
    }

    #[Test]
    public function security_signals_override_the_profile(): void
    {
        $domain = $this->selector->domainFor(
            ['executor_profile' => 'frontend_ui'],
            ['skill' => 'bosskuai-cybersecurity-risk', 'category' => 'authentication'],
        );

        $this->assertSame('security', $domain);
    }
}
