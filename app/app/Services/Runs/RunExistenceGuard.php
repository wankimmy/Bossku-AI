<?php

namespace App\Services\Runs;

use App\Models\BosskuAi\Run;
use Illuminate\Database\QueryException;

/**
 * Request-scoped cache for whether a bossku_ai_runs row still exists.
 * Used by best-effort telemetry (stream events, usage) so a deleted or wiped
 * run does not crash in-flight LLM work.
 */
class RunExistenceGuard
{
    /** @var array<string, bool> */
    private array $existsByRunId = [];

    public function exists(?string $runId): bool
    {
        if ($runId === null || $runId === '') {
            return false;
        }

        if (array_key_exists($runId, $this->existsByRunId)) {
            return $this->existsByRunId[$runId];
        }

        return $this->existsByRunId[$runId] = Run::query()->whereKey($runId)->exists();
    }

    public function markMissing(string $runId): void
    {
        $this->existsByRunId[$runId] = false;
    }

    public static function isIntegrityViolation(QueryException $e): bool
    {
        if (str_starts_with((string) $e->getCode(), '23')) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'foreign key')
            || str_contains($message, 'violates foreign key')
            || str_contains($message, 'foreign key constraint failed');
    }
}
