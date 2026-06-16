<?php

namespace App\Services\Kernel\Checkpoint;

use App\Models\BosskuAi\Run;
use App\Services\Kernel\Graph\DefaultPipelineGraph;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Runtime\RunState;
use RuntimeException;

/**
 * Time-travel over a run's checkpoint history: list past checkpoints and fork a
 * new run from any of them, optionally patching the state. This is LangGraph's
 * update_state-on-a-new-thread — rewind, edit, branch.
 */
final class CheckpointService
{
    public function __construct(private readonly CheckpointSaverInterface $saver) {}

    /**
     * Checkpoint history for a run, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function history(string $threadId, int $limit = 50): array
    {
        return array_map(
            static fn (Checkpoint $cp): array => [
                'id' => $cp->id,
                'parent_id' => $cp->parentId,
                'step' => $cp->step,
                'source' => $cp->source,
                'next' => $cp->next,
            ],
            $this->saver->list($threadId, $limit),
        );
    }

    public function get(string $threadId, string $checkpointId): ?Checkpoint
    {
        return $this->saver->get($threadId, $checkpointId);
    }

    /**
     * Fork a new run from a checkpoint of an existing run. The new run begins
     * with a fork-source checkpoint carrying the (optionally patched) channel
     * state, ready to be resumed by the GraphRunner.
     *
     * @param  array<string, mixed>  $statePatch  channel writes applied to the forked state
     */
    public function fork(Run $sourceRun, string $checkpointId, array $statePatch = [], ?StateSchema $schema = null): Run
    {
        $source = $this->saver->get((string) $sourceRun->getKey(), $checkpointId);
        if ($source === null) {
            throw new RuntimeException("Checkpoint {$checkpointId} not found for run {$sourceRun->getKey()}.");
        }

        $schema ??= DefaultPipelineGraph::schema();

        // Replay the snapshot through the schema so the patch goes through the
        // channel reducers, then re-snapshot.
        $state = RunState::fromSchema($schema);
        $state->restore($source->channelValues);
        if ($statePatch !== []) {
            $state->update($statePatch);
        }

        $forkRun = Run::query()->create([
            'prompt' => $sourceRun->prompt,
            'status' => 'forked',
            'run_kind' => 'fork',
            'parent_run_id' => $sourceRun->getKey(),
            'metadata' => [
                'forked_from' => [
                    'run_id' => (string) $sourceRun->getKey(),
                    'checkpoint_id' => $checkpointId,
                    'step' => $source->step,
                ],
            ],
        ]);

        $this->saver->put((string) $forkRun->getKey(), new Checkpoint(
            id: Checkpoint::newId(),
            parentId: null,
            channelValues: $state->checkpoint(),
            next: $source->next,
            step: $source->step,
            source: \App\Services\Kernel\Constants::SOURCE_FORK,
            metadata: ['forked_from_checkpoint' => $checkpointId],
        ));

        return $forkRun;
    }
}
