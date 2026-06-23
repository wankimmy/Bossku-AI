<?php

namespace App\Services\BosskuAi\Interactions;

/**
 * A typed human-in-the-loop interaction. Replaces free-text clarification
 * questions with an auditable, resumable artifact. Ported from paperclip's
 * issue-thread interaction kinds + langgraph's interrupt(value)/resume(value).
 *
 * Flow:
 *   1. An agent (or the orchestrator) calls Interaction::ask($kind, $payload).
 *   2. The Kernel suspends the run with an Interrupt carrying this interaction.
 *   3. The UI renders the interaction per its kind and collects the user's reply.
 *   4. The resume writes the InteractionReply back; the node re-runs and the
 *      interrupt returns the reply instead of suspending.
 *
 * The interaction has a stable id (idempotency key), a kind, a payload, and
 * optional staleness metadata (target_revision_id, superseded_on_comment).
 */
final class Interaction
{
    /**
     * @param  InteractionKind  $kind
     * @param  array<string, mixed>  $payload  kind-specific:
     *     Confirmation: {question: string, default?: bool}
     *     CheckboxConfirmation: {question: string, options: list<string>, defaults?: list<string>}
     *     Questions: {questions: list<{id: string, text: string, options?: list<string>}>}
     *     SuggestTasks: {tasks: list<{id: string, title: string, description?: string}>}
     * @param  ?string  $targetRevisionId  optimistic-lock target (stale-target expiry)
     * @param  ?string  $id  idempotency key; auto-generated if null
     */
    public function __construct(
        public readonly InteractionKind $kind,
        public readonly array $payload,
        public readonly ?string $targetRevisionId = null,
        public readonly ?string $id = null,
    ) {}

    public function id(): string
    {
        return $this->id ?? md5(serialize([$this->kind->value, $this->payload]));
    }

    /**
     * Produce the interrupt value shape that the Kernel's GraphContext::interrupt()
     * expects: ['key' => string, 'request' => array].
     *
     * @return array{key: string, request: array<string, mixed>}
     */
    public function toInterruptValue(): array
    {
        return [
            'key' => 'interaction:'.$this->id(),
            'request' => [
                'operation_type' => 'interaction:'.$this->kind->value,
                'description' => $this->summary(),
                'risk_level' => 'low',
                'evidence' => $this->payload,
                'interaction_kind' => $this->kind->value,
                'interaction_id' => $this->id(),
                'target_revision_id' => $this->targetRevisionId,
            ],
        ];
    }

    public function summary(): string
    {
        return match ($this->kind) {
            InteractionKind::Confirmation => (string) ($this->payload['question'] ?? 'Confirmation required.'),
            InteractionKind::CheckboxConfirmation => (string) ($this->payload['question'] ?? 'Select options.'),
            InteractionKind::Questions => count($this->payload['questions'] ?? []).' question(s) for you.',
            InteractionKind::SuggestTasks => count($this->payload['tasks'] ?? []).' suggested task(s).',
        };
    }

    /** Convenience constructors */

    /** @param array<string, mixed> $payload */
    public static function ask(InteractionKind $kind, array $payload, ?string $targetRevisionId = null): self
    {
        return new self($kind, $payload, $targetRevisionId);
    }

    public static function confirmation(string $question, ?bool $default = null, ?string $targetRevisionId = null): self
    {
        $payload = ['question' => $question];
        if ($default !== null) {
            $payload['default'] = $default;
        }

        return new self(InteractionKind::Confirmation, $payload, $targetRevisionId);
    }

    /** @param list<string> $options @param list<string>|null $defaults */
    public static function checkbox(string $question, array $options, ?array $defaults = null, ?string $targetRevisionId = null): self
    {
        $payload = ['question' => $question, 'options' => $options];
        if ($defaults !== null) {
            $payload['defaults'] = $defaults;
        }

        return new self(InteractionKind::CheckboxConfirmation, $payload, $targetRevisionId);
    }

    /** @param list<array{id: string, text: string, options?: list<string>}> $questions */
    public static function questions(array $questions, ?string $targetRevisionId = null): self
    {
        return new self(InteractionKind::Questions, ['questions' => $questions], $targetRevisionId);
    }

    /** @param list<array{id: string, title: string, description?: string}> $tasks */
    public static function suggestTasks(array $tasks, ?string $targetRevisionId = null): self
    {
        return new self(InteractionKind::SuggestTasks, ['tasks' => $tasks], $targetRevisionId);
    }
}