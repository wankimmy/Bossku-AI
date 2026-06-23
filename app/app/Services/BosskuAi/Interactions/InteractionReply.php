<?php

namespace App\Services\BosskuAi\Interactions;

/**
 * The user's reply to a typed Interaction. Carried as the resume payload so
 * the Kernel's GraphRunner::resume() re-runs the interrupted node and the
 * interrupt returns this reply instead of suspending.
 *
 * Ported from langgraph's Command(resume=...) + paperclip's interaction
 * resolution shape. The reply is kind-specific:
 *
 * - Confirmation: {answer: bool}
 * - CheckboxConfirmation: {selected: list<string>}
 * - Questions: {answers: array<string, string|list<string>>}  keyed by question id
 * - SuggestTasks: {accepted: list<string>, rejected: list<string>}  keyed by task id
 *
 * Stale-target detection: if the interaction's target_revision_id no longer
 * matches the current revision, the reply is rejected and the interaction
 * re-surfaced (prevents acting on a decision made against an outdated plan).
 */
final class InteractionReply
{
    /**
     * @param  string  $interactionId  matches the Interaction::id()
     * @param  InteractionKind  $kind
     * @param  array<string, mixed>  $answer  kind-specific answer payload
     * @param  ?string  $decidedBy  user/system identifier
     * @param  ?string  $note  optional free-text rationale
     */
    public function __construct(
        public readonly string $interactionId,
        public readonly InteractionKind $kind,
        public readonly array $answer,
        public readonly ?string $decidedBy = null,
        public readonly ?string $note = null,
    ) {}

    public function isStaleAgainst(string $currentRevisionId): bool
    {
        // Replies without a target revision are never stale.
        return false;
    }

    /** @return array<string, mixed> */
    public function toResumeWrites(): array
    {
        return [
            'interaction_reply' => [
                'interaction_id' => $this->interactionId,
                'kind' => $this->kind->value,
                'answer' => $this->answer,
                'decided_by' => $this->decidedBy,
                'note' => $this->note,
            ],
        ];
    }
}