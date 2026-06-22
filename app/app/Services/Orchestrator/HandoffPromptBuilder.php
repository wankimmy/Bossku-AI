<?php

namespace App\Services\Orchestrator;

use App\Support\StringCoercion;
use App\Support\TaskContextResolver;
use Illuminate\Support\Str;

/**
 * Builds task-aware next steps and paste-ready follow-up prompt suggestions.
 */
class HandoffPromptBuilder
{
    /**
     * @param  list<string>  $files
     * @param  array{executed_lines: list<string>, proposed_lines: list<string>, failed_commands: list<string>, git_restore_failed: bool, summary_text: string}  $commandOutcome
     * @param  list<string>  $executedCommandLines
     * @param  list<string>  $proposedCommandLines
     * @param  array<string, mixed>|null  $lastFinal
     * @param  array<string, mixed>  $contextAnchors
     * @return array{next_step: string, primary_prompt: string, prompt_suggestions: list<array{label: string, prompt: string}>}
     */
    public function build(
        array $files,
        array $commandOutcome,
        array $executedCommandLines,
        array $proposedCommandLines,
        ?array $lastFinal,
        string $userPrompt,
        string $planGoal,
        array $contextAnchors = [],
        bool $hasMergeEvidence = false,
    ): array {
        $actions = is_array($lastFinal['required_actions'] ?? null) ? $lastFinal['required_actions'] : [];
        $decision = strtoupper(trim(StringCoercion::toString($lastFinal['decision'] ?? null, '')));

        if ($actions !== [] && in_array($decision, ['REVISE', 'REJECT'], true)) {
            $primary = trim(StringCoercion::toString($actions[0]));

            return [
                'next_step' => $primary,
                'primary_prompt' => $primary,
                'prompt_suggestions' => $this->wrapSuggestions([
                    'Fix' => $primary,
                ]),
            ];
        }

        $targetPath = $this->primaryTargetPath($contextAnchors, $files, $userPrompt, $planGoal);
        $taskKind = (string) ($contextAnchors['task_kind'] ?? 'general');
        $intent = trim((string) ($contextAnchors['last_actionable_user_intent'] ?? ''));
        if ($intent === '') {
            $intent = $planGoal !== '' ? $planGoal : Str::limit(trim($userPrompt), 120);
        }

        $nextStep = $this->deriveNextStep(
            $files,
            $commandOutcome,
            $executedCommandLines,
            $proposedCommandLines,
            $taskKind,
            $targetPath,
            $intent,
            $hasMergeEvidence,
            $actions,
        );

        $suggestions = $this->derivePromptSuggestions(
            $taskKind,
            $targetPath,
            $intent,
            $files,
            $commandOutcome,
            $executedCommandLines,
            $proposedCommandLines,
            $contextAnchors,
        );

        $primaryPrompt = $suggestions[0]['prompt'] ?? $nextStep;

        return [
            'next_step' => $nextStep,
            'primary_prompt' => $primaryPrompt,
            'prompt_suggestions' => $suggestions,
        ];
    }

    /**
     * @param  list<string>  $files
     * @param  array{executed_lines: list<string>, proposed_lines: list<string>, failed_commands: list<string>, git_restore_failed: bool, summary_text: string}  $commandOutcome
     * @param  list<string>  $executedCommandLines
     * @param  list<string>  $proposedCommandLines
     * @param  list<string>  $actions
     */
    private function deriveNextStep(
        array $files,
        array $commandOutcome,
        array $executedCommandLines,
        array $proposedCommandLines,
        string $taskKind,
        string $targetPath,
        string $intent,
        bool $hasMergeEvidence,
        array $actions,
    ): string {
        if (($commandOutcome['git_restore_failed'] ?? false) === true) {
            return 'Git restore did not complete. Run manually in the project: '
                .implode('; ', array_slice($commandOutcome['failed_commands'] ?? [], 0, 3));
        }

        if ($actions !== []) {
            return implode('; ', array_map(static fn ($a) => StringCoercion::toString($a), $actions));
        }

        if ($executedCommandLines === [] && $proposedCommandLines !== []) {
            return 'Commands were proposed but not executed — run them manually in the active project root.';
        }

        if (in_array($taskKind, ['docs_read', 'docs_execute', 'project_understanding'], true)) {
            if ($targetPath !== '') {
                return $taskKind === 'docs_execute'
                    ? "Continue with `{$targetPath}`: read the doc, summarize executable steps, then implement only safe repo-scoped changes."
                    : "Inspect `{$targetPath}` and continue the read-only task: {$intent}";
            }

            return 'Continue the docs-driven task by reading the referenced file and reporting findings before making changes.';
        }

        if ($files !== [] && $executedCommandLines === []) {
            return 'Review the changed files and run the relevant project checks for this task.';
        }

        if ($executedCommandLines === []) {
            return $hasMergeEvidence
                ? 'Run the relevant test suite and confirm the outcome before merge.'
                : 'Run the relevant project checks for this task and report pass/fail with any errors.';
        }

        return $hasMergeEvidence
            ? 'Review the changed files and run any missing checks before merge.'
            : 'Review the changed files and run any missing checks for this task.';
    }

    /**
     * @param  list<string>  $files
     * @param  array{executed_lines: list<string>, proposed_lines: list<string>, failed_commands: list<string>, git_restore_failed: bool, summary_text: string}  $commandOutcome
     * @param  list<string>  $executedCommandLines
     * @param  list<string>  $proposedCommandLines
     * @param  array<string, mixed>  $contextAnchors
     * @return list<array{label: string, prompt: string}>
     */
    private function derivePromptSuggestions(
        string $taskKind,
        string $targetPath,
        string $intent,
        array $files,
        array $commandOutcome,
        array $executedCommandLines,
        array $proposedCommandLines,
        array $contextAnchors,
    ): array {
        $targetClause = $targetPath !== '' ? " `{$targetPath}`" : '';
        $intentClause = $intent !== '' ? " for: {$intent}" : '';

        $suggestions = [];

        if (in_array($taskKind, ['docs_read', 'docs_execute'], true) && $targetPath !== '') {
            $suggestions['Continue'] = "Read{$targetClause} in the active repo, summarize its executable instructions, then execute only the safe code steps. Pause before any destructive, secret, payment, auth, migration, or deployment action.";
            $suggestions['Verify'] = "Inspect the latest run{$intentClause} and report whether `{$targetPath}` was actually read or executed. If not, explain the routing/context failure.";
            $suggestions['Handoff'] = "Create a concise continuation prompt for{$targetClause} that includes the active repo, target file, user intent, risks, and the next safe command/check.";
        } elseif ($taskKind === 'project_understanding') {
            $suggestions['Continue'] = 'Continue the project-understanding pass: inspect the active repository, confirm purpose/stack/conventions, and list the safest next implementation step without editing files yet.';
            $suggestions['Verify'] = 'Verify the project-understanding summary against source files and call out anything inferred vs confirmed.';
        } elseif ($files !== []) {
            $listed = implode(', ', array_slice($files, 0, 5));
            $suggestions['Verify'] = "Review the changes in {$listed}, note anything unexpected, and run the appropriate project checks{$intentClause}.";
            $suggestions['Continue'] = "Continue the implementation{$intentClause} and report what still remains.";
        } elseif ($executedCommandLines === [] && $proposedCommandLines !== []) {
            $suggestions['Continue'] = 'Run the proposed project commands from the run summary in the active project root and report stdout/stderr.';
        } else {
            $suggestions['Continue'] = "Continue the task in the active project{$intentClause} and report results.";
            $suggestions['Verify'] = "Re-check the latest run output{$intentClause} and confirm whether the original request is fully satisfied.";
        }

        if (($commandOutcome['git_restore_failed'] ?? false) === true) {
            $suggestions['Debug'] = 'Diagnose why git restore failed, restore the rejected paths, then retry the task from a clean working tree.';
        }

        if (($contextAnchors['attachment_refs'] ?? []) !== []) {
            $attachment = (string) ($contextAnchors['attachment_refs'][0] ?? '');
            if ($attachment !== '') {
                $suggestions['Continue'] = "Read the attached file `{$attachment}` and continue the task{$intentClause}.";
            }
        }

        return $this->wrapSuggestions(array_slice($suggestions, 0, 4));
    }

    /**
     * @param  array<string, string>  $labeled
     * @return list<array{label: string, prompt: string}>
     */
    private function wrapSuggestions(array $labeled): array
    {
        $out = [];
        foreach ($labeled as $label => $prompt) {
            $prompt = trim($prompt);
            if ($prompt === '') {
                continue;
            }
            $out[] = ['label' => $label, 'prompt' => $prompt];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $contextAnchors
     * @param  list<string>  $files
     */
    private function primaryTargetPath(array $contextAnchors, array $files, string $userPrompt, string $planGoal): string
    {
        $docs = is_array($contextAnchors['docs_targets'] ?? null) ? $contextAnchors['docs_targets'] : [];
        if ($docs !== []) {
            return (string) $docs[0];
        }

        $paths = is_array($contextAnchors['target_paths'] ?? null) ? $contextAnchors['target_paths'] : [];
        if ($paths !== []) {
            return (string) $paths[0];
        }

        $fromPrompt = TaskContextResolver::extractRepoLikePaths($userPrompt.' '.$planGoal);
        if ($fromPrompt !== []) {
            return $fromPrompt[0];
        }

        return $files[0] ?? '';
    }
}
