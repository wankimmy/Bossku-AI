<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Interactions\Interaction;
use App\Services\BosskuAi\Interactions\InteractionKind;
use App\Services\BosskuAi\Interactions\InteractionReply;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the typed human-in-the-loop interaction layer. Proves the four
 * interaction kinds produce stable interrupt values, replies serialize to
 * resume writes, and the idempotency keys are deterministic.
 */
class InteractionTest extends TestCase
{
    #[Test]
    public function confirmation_interaction_produces_interrupt_value(): void
    {
        $interaction = Interaction::confirmation('Proceed with migration?', true);

        $this->assertSame(InteractionKind::Confirmation, $interaction->kind);
        $value = $interaction->toInterruptValue();

        $this->assertSame('interaction:'.$interaction->id(), $value['key']);
        $this->assertSame('interaction:request_confirmation', $value['request']['operation_type']);
        $this->assertSame('Proceed with migration?', $value['request']['description']);
        $this->assertSame('request_confirmation', $value['request']['interaction_kind']);
        $this->assertSame('Proceed with migration?', $interaction->payload['question']);
        $this->assertTrue($interaction->payload['default']);
    }

    #[Test]
    public function checkbox_interaction_carries_options_and_defaults(): void
    {
        $interaction = Interaction::checkbox(
            'Which areas to audit?',
            ['auth', 'billing', 'migrations'],
            ['auth'],
        );

        $this->assertSame(InteractionKind::CheckboxConfirmation, $interaction->kind);
        $this->assertSame(['auth', 'billing', 'migrations'], $interaction->payload['options']);
        $this->assertSame(['auth'], $interaction->payload['defaults']);
        $this->assertSame('Which areas to audit?', $interaction->summary());
    }

    #[Test]
    public function questions_interaction_summarizes_count(): void
    {
        $interaction = Interaction::questions([
            ['id' => 'q1', 'text' => 'Which DB?'],
            ['id' => 'q2', 'text' => 'Which ORM?', 'options' => ['eloquent', 'doctrine']],
        ]);

        $this->assertSame(InteractionKind::Questions, $interaction->kind);
        $this->assertSame('2 question(s) for you.', $interaction->summary());
        $this->assertCount(2, $interaction->payload['questions']);
    }

    #[Test]
    public function suggest_tasks_interaction_carries_task_list(): void
    {
        $interaction = Interaction::suggestTasks([
            ['id' => 't1', 'title' => 'Add migration'],
            ['id' => 't2', 'title' => 'Update tests'],
        ]);

        $this->assertSame(InteractionKind::SuggestTasks, $interaction->kind);
        $this->assertSame('2 suggested task(s).', $interaction->summary());
        $this->assertCount(2, $interaction->payload['tasks']);
    }

    #[Test]
    public function interaction_id_is_deterministic_for_same_payload(): void
    {
        $a = Interaction::confirmation('Same question?');
        $b = Interaction::confirmation('Same question?');

        $this->assertSame($a->id(), $b->id());
    }

    #[Test]
    public function interaction_id_differs_for_different_payload(): void
    {
        $a = Interaction::confirmation('Question A?');
        $b = Interaction::confirmation('Question B?');

        $this->assertNotSame($a->id(), $b->id());
    }

    #[Test]
    public function target_revision_id_carried_for_stale_detection(): void
    {
        $interaction = Interaction::confirmation('Proceed?', targetRevisionId: 'rev-abc123');

        $this->assertSame('rev-abc123', $interaction->targetRevisionId);
        $this->assertSame('rev-abc123', $interaction->toInterruptValue()['request']['target_revision_id']);
    }

    #[Test]
    public function reply_serializes_to_resume_writes(): void
    {
        $interaction = Interaction::confirmation('Proceed?');
        $reply = new InteractionReply(
            interactionId: $interaction->id(),
            kind: InteractionKind::Confirmation,
            answer: ['answer' => true],
            decidedBy: 'user@example.com',
            note: 'looks good',
        );

        $writes = $reply->toResumeWrites();

        $this->assertArrayHasKey('interaction_reply', $writes);
        $this->assertSame($interaction->id(), $writes['interaction_reply']['interaction_id']);
        $this->assertSame('request_confirmation', $writes['interaction_reply']['kind']);
        $this->assertSame(['answer' => true], $writes['interaction_reply']['answer']);
        $this->assertSame('user@example.com', $writes['interaction_reply']['decided_by']);
        $this->assertSame('looks good', $writes['interaction_reply']['note']);
    }

    #[Test]
    public function checkbox_reply_carries_selected(): void
    {
        $reply = new InteractionReply(
            interactionId: 'i1',
            kind: InteractionKind::CheckboxConfirmation,
            answer: ['selected' => ['auth', 'billing']],
        );

        $this->assertSame(['selected' => ['auth', 'billing']], $reply->answer);
    }

    #[Test]
    public function questions_reply_carries_answers_keyed_by_question_id(): void
    {
        $reply = new InteractionReply(
            interactionId: 'i1',
            kind: InteractionKind::Questions,
            answer: ['answers' => ['q1' => 'postgres', 'q2' => ['eloquent']]],
        );

        $this->assertSame('postgres', $reply->answer['answers']['q1']);
        $this->assertSame(['eloquent'], $reply->answer['answers']['q2']);
    }

    #[Test]
    public function suggest_tasks_reply_carries_accepted_and_rejected(): void
    {
        $reply = new InteractionReply(
            interactionId: 'i1',
            kind: InteractionKind::SuggestTasks,
            answer: ['accepted' => ['t1'], 'rejected' => ['t2']],
        );

        $this->assertSame(['t1'], $reply->answer['accepted']);
        $this->assertSame(['t2'], $reply->answer['rejected']);
    }

    #[Test]
    public function ask_factory_accepts_any_kind_and_payload(): void
    {
        $interaction = Interaction::ask(InteractionKind::Confirmation, ['question' => 'Go?']);

        $this->assertSame(InteractionKind::Confirmation, $interaction->kind);
        $this->assertSame('Go?', $interaction->payload['question']);
    }
}