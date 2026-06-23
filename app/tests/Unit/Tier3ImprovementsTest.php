<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Hooks\HookProfile;
use App\Services\BosskuAi\Kanban\KanbanCard;
use App\Services\BosskuAi\Loops\CompletionSignal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Tier 3 smaller improvements: hook runtime controls (env-var
 * profiles), Agent Kanban (state machine + failure-mode prevention), and
 * the completion-signal mechanism for autonomous loops.
 */
class Tier3ImprovementsTest extends TestCase
{
    // --- Hook Profile ---

    #[Test]
    public function hook_profile_defaults_to_standard(): void
    {
        // BOSSKU_HOOK_PROFILE is not set in test env → standard.
        $this->assertSame(HookProfile::STANDARD, HookProfile::current());
    }

    #[Test]
    public function standard_profile_enables_all_hooks(): void
    {
        $this->assertTrue(HookProfile::isEnabled('tool.definition'));
        $this->assertTrue(HookProfile::isEnabled('permission.ask'));
        $this->assertTrue(HookProfile::isEnabled('chat.system.transform'));
    }

    #[Test]
    public function disabled_hooks_override_profile(): void
    {
        putenv('BOSSKU_DISABLED_HOOKS=tool.definition,permission.ask');

        $this->assertFalse(HookProfile::isEnabled('tool.definition'));
        $this->assertFalse(HookProfile::isEnabled('permission.ask'));
        $this->assertTrue(HookProfile::isEnabled('chat.system.transform'));

        putenv('BOSSKU_DISABLED_HOOKS=');
    }

    #[Test]
    public function minimal_profile_enables_only_safety_hooks(): void
    {
        putenv('BOSSKU_HOOK_PROFILE=minimal');

        $this->assertFalse(HookProfile::isEnabled('tool.definition'));
        $this->assertFalse(HookProfile::isEnabled('permission.ask'));
        $this->assertTrue(HookProfile::isEnabled('tool.execute.before'));
        $this->assertTrue(HookProfile::isEnabled('tool.execute.after'));

        putenv('BOSSKU_HOOK_PROFILE=');
    }

    #[Test]
    public function active_hooks_excludes_disabled(): void
    {
        putenv('BOSSKU_DISABLED_HOOKS=permission.ask');
        $active = HookProfile::activeHooks();

        $this->assertNotContains('permission.ask', $active);
        $this->assertContains('tool.definition', $active);

        putenv('BOSSKU_DISABLED_HOOKS=');
    }

    // --- Kanban Card ---

    #[Test]
    public function kanban_card_requires_acceptance_criteria(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('acceptance criterion');

        new KanbanCard(
            id: 'c1',
            title: 'Add auth',
            owner: 'executor',
            state: KanbanCard::BACKLOG,
            scope: ['src/auth'],
            acceptance: [], // empty — prevents "no product artifact" failure mode
        );
    }

    #[Test]
    public function kanban_valid_state_transitions_succeed(): void
    {
        $card = new KanbanCard('c1', 'Task', 'executor', KanbanCard::BACKLOG, ['src/x'], ['it works']);

        $this->assertTrue($card->transition(KanbanCard::READY));
        $this->assertSame(KanbanCard::READY, $card->state);

        $this->assertTrue($card->transition(KanbanCard::RUNNING));
        $this->assertTrue($card->transition(KanbanCard::REVIEW));
        $this->assertTrue($card->transition(KanbanCard::MERGED));
    }

    #[Test]
    public function kanban_invalid_state_transition_fails(): void
    {
        $card = new KanbanCard('c1', 'Task', 'executor', KanbanCard::BACKLOG, ['src/x'], ['it works']);

        $this->assertFalse($card->transition(KanbanCard::MERGED)); // can't skip states
        $this->assertSame(KanbanCard::BACKLOG, $card->state);
    }

    #[Test]
    public function kanban_blocked_can_return_to_ready(): void
    {
        $card = new KanbanCard('c1', 'Task', 'executor', KanbanCard::READY, ['src/x'], ['it works']);
        $card->transition(KanbanCard::BLOCKED);
        $this->assertSame(KanbanCard::BLOCKED, $card->state);

        $this->assertTrue($card->transition(KanbanCard::READY));
        $this->assertSame(KanbanCard::READY, $card->state);
    }

    #[Test]
    public function kanban_overlaps_detects_file_conflict(): void
    {
        $cardA = new KanbanCard('a', 'Task A', 'executor-1', KanbanCard::RUNNING, ['src/auth.php', 'src/routes.php'], ['works']);
        $cardB = new KanbanCard('b', 'Task B', 'executor-2', KanbanCard::READY, ['src/routes.php', 'src/db.php'], ['works']);

        $this->assertTrue($cardA->overlaps($cardB)); // both touch src/routes.php
    }

    #[Test]
    public function kanban_non_overlapping_cards_can_run_in_parallel(): void
    {
        $cardA = new KanbanCard('a', 'Task A', 'executor-1', KanbanCard::RUNNING, ['src/auth.php'], ['works']);
        $cardB = new KanbanCard('b', 'Task B', 'executor-2', KanbanCard::READY, ['src/billing.php'], ['works']);

        $this->assertFalse($cardA->overlaps($cardB));
    }

    #[Test]
    public function kanban_to_array_serializes(): void
    {
        $card = new KanbanCard('c1', 'Task', 'executor', KanbanCard::READY, ['src/x'], ['it works'], mergeGate: true, branch: 'feature/x');
        $arr = $card->toArray();

        $this->assertSame('c1', $arr['id']);
        $this->assertSame('ready', $arr['state']);
        $this->assertTrue($arr['merge_gate']);
        $this->assertSame('feature/x', $arr['branch']);
    }

    // --- Completion Signal ---

    #[Test]
    public function completion_signal_stops_after_threshold(): void
    {
        $signal = new CompletionSignal('BOSSKU_PROJECT_COMPLETE', 3);

        $signal->record('some output');
        $signal->record('BOSSKU_PROJECT_COMPLETE');
        $this->assertFalse($signal->shouldStop()); // 1 of 3

        $signal->record('BOSSKU_PROJECT_COMPLETE');
        $this->assertFalse($signal->shouldStop()); // 2 of 3

        $signal->record('BOSSKU_PROJECT_COMPLETE');
        $this->assertTrue($signal->shouldStop()); // 3 of 3
    }

    #[Test]
    public function non_matching_output_resets_counter(): void
    {
        $signal = new CompletionSignal('DONE', 2);

        $signal->record('DONE');
        $signal->record('DONE');
        $this->assertTrue($signal->shouldStop());

        $signal->record('still working');
        $this->assertFalse($signal->shouldStop());
        $this->assertSame(0, $signal->consecutiveCount());
    }

    #[Test]
    public function completion_signal_phrase_can_be_anywhere_in_output(): void
    {
        $signal = new CompletionSignal('COMPLETE', 1);

        $signal->record('The task is COMPLETE. All tests pass.');
        $this->assertTrue($signal->shouldStop());
    }

    #[Test]
    public function reset_clears_counter(): void
    {
        $signal = new CompletionSignal('DONE', 2);
        $signal->record('DONE');
        $signal->record('DONE');
        $signal->reset();

        $this->assertSame(0, $signal->consecutiveCount());
        $this->assertFalse($signal->shouldStop());
    }

    #[Test]
    public function threshold_must_be_at_least_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CompletionSignal('DONE', 0);
    }
}