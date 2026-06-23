<?php

namespace App\Services\BosskuAi\Interactions;

/**
 * Typed human-in-the-loop interaction kinds. Ported from paperclip's four
 * issue-thread interaction kinds, replacing free-text clarification questions
 * with auditable, resumable artifacts.
 *
 * Each kind has a stable string identifier and a payload shape. The interrupt
 * value carried by the Kernel's Interrupt type uses these to tell the UI what
 * to render and how to resume.
 *
 * - request_confirmation: yes/no decision (e.g. "Proceed with migration?").
 * - request_checkbox_confirmation: multi-select (e.g. "Which of these to include?").
 * - ask_user_questions: open-ended questions with optional multiple-choice.
 * - suggest_tasks: propose sub-tasks the user can accept/reject (maps to
 *   BosskuAI's planner_questions).
 */
enum InteractionKind: string
{
    case Confirmation = 'request_confirmation';

    case CheckboxConfirmation = 'request_checkbox_confirmation';

    case Questions = 'ask_user_questions';

    case SuggestTasks = 'suggest_tasks';
}