<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\WorkflowRouteHelper;
use App\Support\StringCoercion;

class PlanCouncilService
{
    public function __construct(
        protected RuntimeSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $routerContext
     * @param  array<string, mixed>  $specialistContext
     * @return array<string, mixed>
     */
    public function review(
        array $plan,
        array $modelRoute,
        array $routerContext,
        int $tokenAcc,
        array $specialistContext = [],
    ): array {
        if (! $this->settings->councilPlanReviewEnabled()) {
            return $this->skipped('disabled', 'Council plan review is disabled in Settings.');
        }

        $workflow = (string) ($modelRoute['workflow'] ?? 'orchestrator_executor');
        if (! in_array('executor', WorkflowRouteHelper::pipelineAgentsForWorkflow($workflow), true)
            || ($modelRoute['needs_executor'] ?? true) === false
            || ($plan['execution_mode'] ?? '') === 'answer_only') {
            return $this->skipped('not_executor_workflow', 'Council review only runs before executor workflows.');
        }

        $budget = (int) config('bossku.token_budget_per_run', 0);
        if ($budget > 0 && $tokenAcc >= $budget) {
            return $this->skipped('token_budget', 'Council review skipped because the run is already at its token budget.');
        }

        $targetSummary = $this->targetSummary($plan);
        $riskSummary = $this->firstNonEmpty([
            $this->joinList($plan['risk_notes'] ?? []),
            $this->joinList($plan['constraints'] ?? []),
            'No explicit planner risk notes were provided.',
        ]);
        $primarySkill = is_array($routerContext['primary_skill'] ?? null) ? $routerContext['primary_skill'] : [];
        $skillName = StringCoercion::toString($primarySkill['name'] ?? $plan['selected_skill'] ?? null, 'general');
        $revisionRounds = $this->settings->maxRevisionRounds();
        $approvalRounds = $this->settings->maxApprovalReviewRounds();

        $voices = [
            [
                'id' => 'architect',
                'label' => 'Architect',
                'position' => 'The plan should stay anchored to '.$targetSummary.' and preserve the existing planner review gate.',
                'reasoning' => [
                    'Use the current Planner -> Executor -> Auditor route instead of adding peer-to-peer runtime chat.',
                    'Keep the plan artifact as the source of truth for the executor and auditor.',
                ],
            ],
            [
                'id' => 'skeptic',
                'label' => 'Skeptic',
                'position' => 'The riskiest assumption is: '.$riskSummary,
                'reasoning' => [
                    'Any vague target or unresolved product intent should be settled before execution starts.',
                    'Council output is advisory; it must not bypass the user approval gate.',
                ],
            ],
            [
                'id' => 'pragmatist',
                'label' => 'Pragmatist',
                'position' => 'Reuse the existing '.$skillName.' route, plan confirmation, and approval/revision loops.',
                'reasoning' => [
                    'Ship the useful review surface without adding new public endpoints or storage tables.',
                    'Let request-changes re-enter the existing replan path.',
                ],
            ],
            [
                'id' => 'critic',
                'label' => 'Critic',
                'position' => 'The loop must stop when configured budgets are spent or when an agent needs user input.',
                'reasoning' => [
                    'Bounded looping protects the user from token burn disguised as diligence.',
                    'The UI must show why the loop continues and what condition stops it.',
                ],
            ],
        ];

        if ($specialistContext !== []) {
            $voices[] = [
                'id' => 'specialist',
                'label' => 'Specialist',
                'position' => StringCoercion::toString(
                    $specialistContext['summary'] ?? $specialistContext['handoff_to_executor'] ?? null,
                    'Use the selected specialist guidance before executor handoff.',
                ),
                'reasoning' => $this->stringList($specialistContext['pitfalls'] ?? []),
            ];
        }

        return [
            'status' => 'completed',
            'reason' => null,
            'voices' => $voices,
            'consensus' => 'Proceed with a bounded plan review for '.$targetSummary.' using the existing planner approval gate.',
            'strongest_dissent' => $voices[1]['position'],
            'recommended_adjustments' => array_values(array_unique(array_filter([
                'Confirm the plan before execution starts.',
                $riskSummary !== 'No explicit planner risk notes were provided.' ? 'Resolve or accept: '.$riskSummary : null,
            ]))),
            'stop_conditions' => [
                'Stop after revision rounds: '.$revisionRounds.'; approval rounds: '.$approvalRounds.'.',
                'Stop when revision rounds: '.$revisionRounds.' are exhausted.',
                $budget > 0 ? 'Warn when estimated token budget is crossed: '.$budget.'.' : 'No hard token budget is configured.',
                'Pause when executor, auditor, or user approval requires input.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function skipped(string $reason, string $summary): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'voices' => [],
            'consensus' => $summary,
            'strongest_dissent' => '',
            'recommended_adjustments' => [],
            'stop_conditions' => [],
        ];
    }

    /** @param array<string, mixed> $plan */
    protected function targetSummary(array $plan): string
    {
        $targets = is_array($plan['target_file_list'] ?? null) ? $plan['target_file_list'] : [];
        $paths = [];
        foreach ($targets as $target) {
            if (! is_array($target)) {
                continue;
            }
            $path = StringCoercion::toString($target['path'] ?? null);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        if ($paths !== []) {
            return implode(', ', array_slice($paths, 0, 4));
        }

        return StringCoercion::toString($plan['goal'] ?? $plan['summary'] ?? null, 'the approved task scope');
    }

    /** @param mixed $value */
    protected function joinList(mixed $value): string
    {
        return implode(' ', $this->stringList($value));
    }

    /** @param list<string> $candidates */
    protected function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => StringCoercion::toString($item),
            $value,
        )));
    }
}
