<?php

namespace App\Services;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStreamEvent;
use App\Services\Runs\RunExistenceGuard;
use Illuminate\Database\QueryException;

class RunStreamEventService
{
    private const MAX_EVENTS_PER_RUN = 500;

    /** @var array<string, int> */
    private array $nextSeqByRun = [];

    public function __construct(
        private readonly RunExistenceGuard $runGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(?string $runId, array $payload): void
    {
        if ($runId === null || $runId === '') {
            $runId = isset($payload['run_id']) ? (string) $payload['run_id'] : null;
        }
        if ($runId === null || $runId === '') {
            return;
        }

        // The stream-event log is best-effort telemetry. If the parent run no
        // longer exists (e.g. it was deleted while a terminal event was still
        // being emitted), skip the write rather than throwing a foreign-key
        // violation that would crash the stream.
        if (! $this->runGuard->exists($runId)) {
            return;
        }

        $seq = $this->nextSeq($runId);

        try {
            RunStreamEvent::query()->create([
                'run_id' => $runId,
                'seq' => $seq,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (RunExistenceGuard::isIntegrityViolation($e)) {
                // Run was deleted between the existence check and the insert.
                $this->runGuard->markMissing($runId);

                return;
            }

            throw $e;
        }

        $this->pruneOldEvents($runId);
    }

    /**
     * @return array{run_id: string, status: string, events: list<array<string, mixed>>, last_seq: int}
     */
    public function eventsSince(Run $run, int $afterSeq = 0): array
    {
        $runId = $run->id;

        $rows = RunStreamEvent::query()
            ->where('run_id', $runId)
            ->where('seq', '>', $afterSeq)
            ->orderBy('seq')
            ->get(['seq', 'payload']);

        $events = [];
        $lastSeq = $afterSeq;
        foreach ($rows as $row) {
            /** @var array<string, mixed> $payload */
            $payload = is_array($row->payload) ? $row->payload : [];
            $payload['seq'] = (int) $row->seq;
            $events[] = $payload;
            $lastSeq = (int) $row->seq;
        }

        return [
            'run_id' => $run->id,
            'status' => (string) $run->status,
            'events' => $events,
            'last_seq' => $lastSeq,
        ];
    }

    private function nextSeq(string $runId): int
    {
        if (isset($this->nextSeqByRun[$runId])) {
            return ++$this->nextSeqByRun[$runId];
        }

        $max = RunStreamEvent::query()
            ->where('run_id', $runId)
            ->max('seq');

        $this->nextSeqByRun[$runId] = $max !== null ? ((int) $max) + 1 : 1;

        return $this->nextSeqByRun[$runId];
    }

    private function pruneOldEvents(string $runId): void
    {
        $count = RunStreamEvent::query()->where('run_id', $runId)->count();
        if ($count <= self::MAX_EVENTS_PER_RUN) {
            return;
        }

        $toDelete = $count - self::MAX_EVENTS_PER_RUN;
        $ids = RunStreamEvent::query()
            ->where('run_id', $runId)
            ->orderBy('seq')
            ->limit($toDelete)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            RunStreamEvent::query()->whereIn('id', $ids)->delete();
        }
    }

    /**
     * @param  callable(array<string, mixed>): void|null  $afterEmit
     * @return callable(array<string, mixed>): void
     */
    public function sseEmitter(?callable $afterEmit = null): callable
    {
        return function (array $evt) use ($afterEmit): void {
            $this->append(
                isset($evt['run_id']) ? (string) $evt['run_id'] : null,
                $evt,
            );

            echo 'data: '.json_encode($evt, JSON_THROW_ON_ERROR)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            if ($afterEmit !== null) {
                $afterEmit($evt);
            }
        };
    }

    public function beginBackgroundStream(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);
    }
}
