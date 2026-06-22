<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\UsageEvent;
use App\Services\Governance\CostBudgetGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CostBudgetGuardTest extends TestCase
{
    use RefreshDatabase;

    private CostBudgetGuard $guard;

    private Run $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = app(CostBudgetGuard::class);
        $this->run = Run::query()->create(['prompt' => 'budget test', 'status' => 'running', 'metadata' => []]);
    }

    private function spend(float $usd): void
    {
        UsageEvent::query()->create([
            'run_id' => $this->run->id,
            'provider' => 'test',
            'model' => 'claude-opus',
            'role' => 'executor',
            'input_tokens' => 100,
            'output_tokens' => 100,
            'cost_usd' => $usd,
            'call_type' => 'chat',
        ]);
    }

    #[Test]
    public function ok_when_no_caps_configured(): void
    {
        config(['bossku.cost_budget_usd_per_run' => 0.0, 'bossku.token_budget_per_run' => 0]);
        $this->spend(5.0);

        $this->assertSame(CostBudgetGuard::OK, $this->guard->evaluate($this->run->id, 999999)['state']);
    }

    #[Test]
    public function exceeded_when_usd_spend_over_cap(): void
    {
        config(['bossku.cost_budget_usd_per_run' => 1.0]);
        $this->spend(0.7);
        $this->spend(0.5); // total 1.2 > 1.0

        $status = $this->guard->evaluate($this->run->id);
        $this->assertSame(CostBudgetGuard::EXCEEDED, $status['state']);
        $this->assertSame('usd_cap', $status['reason']);
        $this->assertEqualsWithDelta(1.2, $status['usd_spent'], 0.0001);
    }

    #[Test]
    public function warning_at_threshold(): void
    {
        config(['bossku.cost_budget_usd_per_run' => 1.0, 'bossku.budget_warn_threshold' => 0.8]);
        $this->spend(0.85); // 85% of cap

        $this->assertSame(CostBudgetGuard::WARNING, $this->guard->evaluate($this->run->id)['state']);
    }

    #[Test]
    public function exceeded_on_token_cap(): void
    {
        config(['bossku.cost_budget_usd_per_run' => 0.0, 'bossku.token_budget_per_run' => 1000]);

        $status = $this->guard->evaluate($this->run->id, 1500);
        $this->assertSame(CostBudgetGuard::EXCEEDED, $status['state']);
        $this->assertSame('token_cap', $status['reason']);
    }

    #[Test]
    public function should_halt_only_when_hard_stop_enabled_and_exceeded(): void
    {
        config(['bossku.cost_budget_usd_per_run' => 1.0]);
        $this->spend(2.0);

        config(['bossku.budget_hard_stop' => false]);
        $this->assertFalse($this->guard->shouldHalt($this->run->id));

        config(['bossku.budget_hard_stop' => true]);
        $this->assertTrue($this->guard->shouldHalt($this->run->id));
    }
}
