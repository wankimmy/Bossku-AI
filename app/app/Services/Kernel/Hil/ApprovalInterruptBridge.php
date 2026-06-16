<?php

namespace App\Services\Kernel\Hil;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;
use App\Services\Kernel\Types\Interrupt;

/**
 * Bridges the graph kernel's durable interrupts to BosskuAI's existing Approval
 * records — the human-in-the-loop seam.
 *
 * Flow:
 *   1. A node calls $ctx->interrupt("approve:deploy", [...request...]).
 *   2. The runner suspends and returns a GraphResult with an Interrupt.
 *   3. record() persists a pending Approval for that interrupt.
 *   4. A human approves/rejects via the normal ApprovalController.
 *   5. resumeScratch() turns decided approvals back into the resume payload, so
 *      GraphRunner::resume() re-runs the node and $ctx->interrupt() returns the
 *      decision instead of throwing.
 */
final class ApprovalInterruptBridge
{
    /**
     * Persist a pending Approval for a kernel interrupt. The interrupt value is
     * expected to be ['key' => string, 'request' => array] (the shape produced
     * by GraphContext::interrupt()).
     */
    public function record(Run $run, Interrupt $interrupt): Approval
    {
        $value = is_array($interrupt->value) ? $interrupt->value : [];
        $key = (string) ($value['key'] ?? $interrupt->node);
        $request = is_array($value['request'] ?? null) ? $value['request'] : [];

        return Approval::query()->create([
            'run_id' => $run->getKey(),
            'operation_type' => (string) ($request['operation_type'] ?? 'agent_interrupt'),
            'operation_description' => (string) ($request['description'] ?? 'Graph node requested human input.'),
            'risk_level' => (string) ($request['risk_level'] ?? 'medium'),
            'evidence' => is_array($request['evidence'] ?? null) ? $request['evidence'] : [],
            'status' => 'pending',
            'metadata' => [
                'kernel_interrupt' => true,
                'interrupt_key' => $key,
                'node' => $interrupt->node,
            ],
        ]);
    }

    /** Whether the run still has an undecided kernel interrupt. */
    public function hasPending(Run $run): bool
    {
        return $this->pendingQuery($run)->exists();
    }

    /**
     * Build the resume scratchpad from decided approvals for this run, keyed by
     * the interrupt key the node will look up. Each value carries the decision so
     * the node can branch on approve vs reject.
     *
     * @return array<string, array{status: string, note: ?string, decided_by: ?string, approval_id: string}>
     */
    public function resumeScratch(Run $run): array
    {
        $scratch = [];
        $approvals = Approval::query()
            ->where('run_id', $run->getKey())
            ->whereIn('status', ['approved', 'rejected', 'auto_approved'])
            ->get();

        foreach ($approvals as $approval) {
            $key = (string) ($approval->metadata['interrupt_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $scratch[$key] = [
                'status' => (string) $approval->status,
                'note' => $approval->decision_note,
                'decided_by' => $approval->decided_by,
                'approval_id' => (string) $approval->getKey(),
            ];
        }

        return $scratch;
    }

    private function pendingQuery(Run $run): \Illuminate\Database\Eloquent\Builder
    {
        return Approval::query()
            ->where('run_id', $run->getKey())
            ->where('status', 'pending')
            ->where('metadata->kernel_interrupt', true);
    }
}
