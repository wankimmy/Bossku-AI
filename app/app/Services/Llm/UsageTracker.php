<?php

namespace App\Services\Llm;

use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\UsageEvent;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Runs\RunExistenceGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class UsageTracker
{
    public function __construct(
        protected ModelRegistry $registry,
        protected RunExistenceGuard $runGuard,
    ) {}

    public function track(LlmRequest $request, LlmResponse $response): ?UsageEvent
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

        [$runId, $runStepId] = $this->resolveRunForeignKeys($request->runId, $request->runStepId);
        if ($request->runId !== null && $request->runId !== '' && $runId === null) {
            $metadata['run_id_orphaned'] = $request->runId;
        }

        try {
            return UsageEvent::create([
                'run_id'        => $runId,
                'run_step_id'   => $runStepId,
                'provider'      => $response->provider,
                'model'         => $response->modelResolved,
                'role'          => $request->role,
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd'      => $costUsd,
                'call_type'     => 'complete',
                'metadata'      => $metadata,
            ]);
        } catch (QueryException $e) {
            if (! RunExistenceGuard::isIntegrityViolation($e)) {
                throw $e;
            }

            if ($request->runId !== null && $request->runId !== '') {
                $this->runGuard->markMissing($request->runId);
            }

            Log::warning('bosskuai.usage.skip_integrity_violation', [
                'run_id' => $request->runId,
                'run_step_id' => $request->runStepId,
                'role' => $request->role,
                'model' => $response->modelResolved,
            ]);

            return $this->trackWithoutRun($request, $response, $metadata);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function trackWithoutRun(LlmRequest $request, LlmResponse $response, array $metadata): ?UsageEvent
    {
        if ($request->runId === null || $request->runId === '') {
            return null;
        }

        $metadata['run_id_orphaned'] = $request->runId;

        $retryRequest = new LlmRequest(
            model: $request->model,
            messages: $request->messages,
            role: $request->role,
            temperature: $request->temperature,
            maxTokens: $request->maxTokens,
            forceProvider: $request->forceProvider,
            runId: null,
            runStepId: null,
            metadata: $metadata,
        );

        return $this->track($retryRequest, $response);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolveRunForeignKeys(?string $runId, ?string $runStepId): array
    {
        if ($runId === null || $runId === '') {
            return [null, null];
        }

        if (! $this->runGuard->exists($runId)) {
            Log::warning('bosskuai.usage.missing_run', [
                'run_id' => $runId,
                'run_step_id' => $runStepId,
            ]);

            return [null, null];
        }

        if ($runStepId === null || $runStepId === '') {
            return [$runId, null];
        }

        $stepOk = RunStep::query()
            ->whereKey($runStepId)
            ->where('run_id', $runId)
            ->exists();

        if (! $stepOk) {
            return [$runId, null];
        }

        return [$runId, $runStepId];
    }
}
