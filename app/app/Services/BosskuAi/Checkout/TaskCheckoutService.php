<?php

namespace App\Services\BosskuAi\Checkout;

use App\Models\BosskuAi\TaskCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atomic task checkout — the concurrency primitive for parallel agents.
 * Ported from paperclip's SPEC-implementation.md 10.4.1: a single SQL
 * conditional update is the lock. 0 rows affected → CheckoutConflictException
 * (HTTP 409 equivalent). The "never retry a 409" rule is the agent contract.
 *
 * This prevents two concurrent agents (Claude + Cursor, or two specialists)
 * from working the same task. The checkout row records ownership; release()
 * frees it; forceRelease() is the admin escape hatch.
 *
 * Flow:
 *   1. Agent calls checkout($taskRef, $assignee) → returns lock_token or throws.
 *   2. Agent does the work, holding the lock.
 *   3. Agent calls complete($taskRef, $lockToken) → status = done.
 *   4. Or release($taskRef, $lockToken) → status = available, assignee = null.
 */
final class TaskCheckoutService
{
    /**
     * Atomically acquire a checkout. If the task is available, or is in_progress
     * but owned by the same agent (re-checkout on resume), the UPDATE succeeds
     * and returns a new lock_token. If another agent owns it, 0 rows are
     * affected → CheckoutConflictException.
     *
     * @param  array{type: string, id: string}  $taskRef  the polymorphic task reference
     * @param  string  $assignee  agent role slug or run id
     * @param  ?string  $runId  the run acquiring the checkout
     * @return string  the lock token (use for release/complete)
     * @throws CheckoutConflictException when another agent already owns it
     */
    public function checkout(array $taskRef, string $assignee, ?string $runId = null): string
    {
        $lockToken = Str::uuid()->toString();

        // Single conditional UPDATE — the atomic compare-and-set.
        // Allowed when:
        //   (status = available AND assignee IS NULL)
        //   OR (status = in_progress AND assignee = $assignee)  — re-checkout on resume
        $affected = DB::table('bossku_ai_task_checkouts')
            ->where('checkoutable_type', $taskRef['type'])
            ->where('checkoutable_id', $taskRef['id'])
            ->where(function ($q) use ($assignee) {
                $q->where(function ($q2) {
                    $q2->where('status', TaskCheckout::STATUS_AVAILABLE)
                        ->whereNull('assignee');
                })->orWhere(function ($q3) use ($assignee) {
                    $q3->where('status', TaskCheckout::STATUS_IN_PROGRESS)
                        ->where('assignee', $assignee);
                });
            })
            ->update([
                'assignee' => $assignee,
                'run_id' => $runId,
                'status' => TaskCheckout::STATUS_IN_PROGRESS,
                'lock_token' => $lockToken,
                'checked_out_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new CheckoutConflictException($taskRef, $assignee);
        }

        return $lockToken;
    }

    /**
     * Create a checkout row in the available state if it doesn't exist yet.
     * Idempotent — if the row already exists, does nothing.
     *
     * @param  array{type: string, id: string}  $taskRef
     * @param  array<string, mixed>  $metadata
     */
    public function ensureAvailable(array $taskRef, array $metadata = []): void
    {
        $exists = DB::table('bossku_ai_task_checkouts')
            ->where('checkoutable_type', $taskRef['type'])
            ->where('checkoutable_id', $taskRef['id'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('bossku_ai_task_checkouts')->insert([
            'id' => Str::uuid()->toString(),
            'checkoutable_type' => $taskRef['type'],
            'checkoutable_id' => $taskRef['id'],
            'status' => TaskCheckout::STATUS_AVAILABLE,
            'metadata' => json_encode($metadata) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Release a checkout back to available (e.g. the agent gave up).
     *
     * @param  array{type: string, id: string}  $taskRef
     * @param  string  $lockToken  must match the checkout's lock_token
     * @return bool true if released, false if the token didn't match
     */
    public function release(array $taskRef, string $lockToken): bool
    {
        return DB::table('bossku_ai_task_checkouts')
            ->where('checkoutable_type', $taskRef['type'])
            ->where('checkoutable_id', $taskRef['id'])
            ->where('lock_token', $lockToken)
            ->where('status', TaskCheckout::STATUS_IN_PROGRESS)
            ->update([
                'status' => TaskCheckout::STATUS_AVAILABLE,
                'assignee' => null,
                'run_id' => null,
                'lock_token' => null,
                'checked_out_at' => null,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Mark a checkout as done (the work is complete).
     *
     * @param  array{type: string, id: string}  $taskRef
     * @param  string  $lockToken
     * @return bool
     */
    public function complete(array $taskRef, string $lockToken): bool
    {
        return DB::table('bossku_ai_task_checkouts')
            ->where('checkoutable_type', $taskRef['type'])
            ->where('checkoutable_id', $taskRef['id'])
            ->where('lock_token', $lockToken)
            ->update([
                'status' => TaskCheckout::STATUS_DONE,
                'completed_at' => now(),
                'lock_token' => null,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Force-release a checkout (admin escape hatch, e.g. the agent crashed).
     * Does not require the lock token.
     *
     * @param  array{type: string, id: string}  $taskRef
     */
    public function forceRelease(array $taskRef): void
    {
        DB::table('bossku_ai_task_checkouts')
            ->where('checkoutable_type', $taskRef['type'])
            ->where('checkoutable_id', $taskRef['id'])
            ->update([
                'status' => TaskCheckout::STATUS_AVAILABLE,
                'assignee' => null,
                'run_id' => null,
                'lock_token' => null,
                'checked_out_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Who owns this task right now?
     *
     * @param  array{type: string, id: string}  $taskRef
     * @return ?array{assignee: ?string, status: string, run_id: ?string, lock_token: ?string}
     */
    public function owner(array $taskRef): ?array
    {
        $row = DB::table('bossku_ai_task_checkouts')
            ->where('checkoutable_type', $taskRef['type'])
            ->where('checkoutable_id', $taskRef['id'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'assignee' => $row->assignee,
            'status' => $row->status,
            'run_id' => $row->run_id,
            'lock_token' => $row->lock_token,
        ];
    }
}