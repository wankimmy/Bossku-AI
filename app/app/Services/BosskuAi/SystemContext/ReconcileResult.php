<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * The result of reconciling a source's current value against its admitted
 * baseline. Ported from opencode's reconcile result union.
 *
 * - Unchanged: the value is identical; no action needed.
 * - Updated: the value changed; the update() text should be admitted as a
 *   Mid-Conversation System Message.
 * - ReplacementReady: the value changed and the source opts into full
 *   replacement (baseline re-emitted). Used after compaction or model switch.
 * - ReplacementBlocked: the value changed but replacement is not safe (e.g.
 *   the new value failed validation); keep the last-admitted baseline.
 */
enum ReconcileResult: string
{
    case Unchanged = 'unchanged';
    case Updated = 'updated';
    case ReplacementReady = 'replacement_ready';
    case ReplacementBlocked = 'replacement_blocked';
}