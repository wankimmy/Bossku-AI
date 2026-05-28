<?php

namespace App\Services\Llm;

use App\Models\BosskuAi\UsageEvent;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;

class UsageTracker
{
    public function __construct(
        protected ModelRegistry $registry,
    ) {}

    public function track(LlmRequest $request, LlmResponse $response): UsageEvent
    {
        $inputTokens  = $response->inputTokens  ?? 0;
        $outputTokens = $response->outputTokens ?? 0;

        $costUsd = $response->costUsd > 0.0
            ? $response->costUsd
            : ModelRegistry::estimateCost($response->modelResolved, $inputTokens, $outputTokens);

        $metadata = array_merge($request->metadata, [
            'model_logical' => $response->modelLogical,
            'pricing_known' => ModelRegistry::hasKnownPricing($response->modelResolved),
        ]);

        $event = UsageEvent::create([
            'run_id'        => $request->runId,
            'run_step_id'   => $request->runStepId,
            'provider'      => $response->provider,
            'model'         => $response->modelResolved,
            'role'          => $request->role,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd'      => $costUsd,
            'call_type'     => 'complete',
            'metadata'      => $metadata,
        ]);

        return $event;
    }
}
