<?php

namespace App\Services\Governance;

use App\Models\BosskuAi\UsageEvent;

/**
 * Cost & token budget enforcement with warning thresholds and a hard stop —
 * Paperclip-style "overspend pauses the agent and cancels queued work".
 *
 * Bossku-AI already records real $ spend per call ({@see \App\Services\Llm\UsageTracker}
 * → bossku_ai_usage_events.cost_usd) but historically only emitted a soft token
 * warning that never stopped anything. This guard turns that telemetry into
 * governance: it reads authoritative spend from the DB, compares it against
 * scoped caps, and reports ok / warning / exceeded so the orchestrator can halt
 * a runaway run (most importantly the audit→revise loop) instead of burning
 * budget indefinitely.
 *
 * All caps default to 0 (off); the hard stop is opt-in (`budget_hard_stop`).
 */
class CostBudgetGuard
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const EXCEEDED = 'exceeded';

    public function usdCapPerRun(): float
    {
        return max(0.0, (float) config('bossku.cost_budget_usd_per_run', 0.0));
    }

    public function tokenCapPerRun(): int
    {
        return max(0, (int) config('bossku.token_budget_per_run', 0));
    }

    public function warnThreshold(): float
    {
        $t = (float) config('bossku.budget_warn_threshold', 0.8);

        return ($t > 0.0 && $t < 1.0) ? $t : 0.8;
    }

    public function hardStopEnabled(): bool
    {
        return (bool) config('bossku.budget_hard_stop', false);
    }

    /** Authoritative $ spend for a run, summed from recorded usage events. */
    public function spentUsd(string $runId): float
    {
        if ($runId === '') {
            return 0.0;
        }

        return (float) UsageEvent::query()->where('run_id', $runId)->sum('cost_usd');
    }

    /**
     * @return array{state: string, reason: string, usd_spent: float, usd_cap: float, tokens: int, token_cap: int}
     */
    public function evaluate(string $runId, int $tokensAccrued = 0): array
    {
        $usdSpent = $this->spentUsd($runId);
        $usdCap = $this->usdCapPerRun();
        $tokenCap = $this->tokenCapPerRun();
        $tokensAccrued = max(0, $tokensAccrued);

        $base = [
            'usd_spent' => round($usdSpent, 6),
            'usd_cap' => $usdCap,
            'tokens' => $tokensAccrued,
            'token_cap' => $tokenCap,
        ];

        if ($usdCap > 0.0 && $usdSpent >= $usdCap) {
            return array_merge($base, ['state' => self::EXCEEDED, 'reason' => 'usd_cap']);
        }
        if ($tokenCap > 0 && $tokensAccrued >= $tokenCap) {
            return array_merge($base, ['state' => self::EXCEEDED, 'reason' => 'token_cap']);
        }

        $warn = $this->warnThreshold();
        if ($usdCap > 0.0 && $usdSpent >= $usdCap * $warn) {
            return array_merge($base, ['state' => self::WARNING, 'reason' => 'usd_threshold']);
        }
        if ($tokenCap > 0 && $tokensAccrued >= $tokenCap * $warn) {
            return array_merge($base, ['state' => self::WARNING, 'reason' => 'token_threshold']);
        }

        return array_merge($base, ['state' => self::OK, 'reason' => '']);
    }

    public function isExceeded(string $runId, int $tokensAccrued = 0): bool
    {
        return $this->evaluate($runId, $tokensAccrued)['state'] === self::EXCEEDED;
    }

    /** True only when the budget is exceeded AND the hard stop is enabled. */
    public function shouldHalt(string $runId, int $tokensAccrued = 0): bool
    {
        return $this->hardStopEnabled() && $this->isExceeded($runId, $tokensAccrued);
    }
}
