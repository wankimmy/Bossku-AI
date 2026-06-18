<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\WorkflowRouteHelper;
use App\Support\StringCoercion;

class StaffCouncilService
{
    public function __construct(
        protected RuntimeSettings $settings,
        protected CompanyStaffService $staff,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $routerContext
     * @return array<string, mixed>
     */
    public function reviewPlan(
        Run $run,
        array $plan,
        array $modelRoute,
        array $routerContext,
        int $tokenAcc,
        ?Project $project,
    ): array {
        $skip = $this->skipReason($modelRoute, $tokenAcc, requireExecutor: true);
        if ($skip !== null) {
            return $this->skipped($skip[0], $skip[1]);
        }
        if ($project === null) {
            return $this->skipped('no_active_project', 'Staff council needs an active project.');
        }

        $selected = $this->staff->selectForCouncil($run->prompt, $plan, $modelRoute, $project);
        if ($selected->isEmpty()) {
            return $this->skipped('no_staff', 'No active company staff matched this plan.');
        }

        $voices = $selected->map(fn ($agent) => [
            'role_slug' => $agent->role_slug,
            'display_name' => $agent->display_name,
            'runtime_mode' => $agent->runtime_mode,
            'position' => $this->staff->positionFor($agent, $plan),
            'recommendations' => $this->recommendationsForRole($agent->role_slug, $plan),
        ])->values()->all();

        return [
            'status' => 'completed',
            'reason' => null,
            'voices' => $voices,
            'consensus' => 'Proceed as a CEO-approved company workflow: staff can advise and propose work, but execution stays approval-gated.',
            'staff_recommendations' => [
                'Convert approved plan checklist items into Kanban issues after CEO approval.',
                'Let Project Manager and Tech Lead shape issue breakdown before executor starts.',
                'Keep follow-up work as suggestions until the CEO approves another run.',
            ],
            'issue_breakdown' => $this->issueBreakdown($plan),
            'stop_conditions' => [
                'Wait for CEO approval before starting more work.',
                'Stop when configured revision or approval rounds are exhausted.',
                'Pause when staff identify unresolved scope, security, or acceptance risk.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    public function reviewContentDeliverable(
        Run $run,
        string $prompt,
        string $deliverable,
        array $modelRoute,
        ?Project $project,
    ): array {
        $workflow = (string) ($modelRoute['workflow'] ?? '');
        if ($workflow === 'direct_answer') {
            return $this->skipped('short_direct_answer', 'Direct answer prompts stay lightweight and do not run staff council.');
        }

        $skip = $this->skipReason($modelRoute, 0, requireExecutor: false);
        if ($skip !== null) {
            return $this->skipped($skip[0], $skip[1]);
        }
        if ($project === null) {
            return $this->skipped('no_active_project', 'Staff council needs an active project.');
        }
        if ($workflow !== 'writer_only') {
            return $this->skipped('not_content_workflow', 'Content staff review only runs for writer workflows in this MVP.');
        }

        $selected = $this->staff->selectForCouncil($prompt.' '.$deliverable, [], $modelRoute, $project);
        if ($selected->isEmpty()) {
            return $this->skipped('no_staff', 'No active company staff matched this content workflow.');
        }

        $voices = $selected->map(fn ($agent) => [
            'role_slug' => $agent->role_slug,
            'display_name' => $agent->display_name,
            'runtime_mode' => $agent->runtime_mode,
            'position' => $this->staff->positionFor($agent),
            'recommendations' => $this->recommendationsForRole($agent->role_slug, []),
        ])->values()->all();

        return [
            'status' => 'completed',
            'reason' => null,
            'voices' => $voices,
            'consensus' => 'Deliver the requested content, then apply staff review notes before handing it back to the CEO.',
            'staff_recommendations' => [
                'Keep the final deliverable first; put council review after it.',
                'Treat staff improvements as review notes, not automatic follow-up execution.',
            ],
            'issue_breakdown' => [],
            'stop_conditions' => [
                'Do not start a new content run without CEO approval.',
                'Pause if claims, SEO terms, or sales promises are unsupported.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @return array{0: string, 1: string}|null
     */
    protected function skipReason(array $modelRoute, int $tokenAcc, bool $requireExecutor): ?array
    {
        if (! $this->settings->companyStaffEnabled() || ! $this->settings->staffCouncilEnabled()) {
            return ['disabled', 'Company staff council is disabled in Settings.'];
        }

        $budget = (int) config('bossku.token_budget_per_run', 0);
        if ($budget > 0 && $tokenAcc >= $budget) {
            return ['token_budget', 'Staff council skipped because the run is already at its token budget.'];
        }

        if ($requireExecutor) {
            $workflow = (string) ($modelRoute['workflow'] ?? 'orchestrator_executor');
            if (! in_array('executor', WorkflowRouteHelper::pipelineAgentsForWorkflow($workflow), true)
                || ($modelRoute['needs_executor'] ?? true) === false) {
                return ['not_executor_workflow', 'Staff council plan review only runs before executor workflows.'];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function skipped(string $reason, string $summary): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'voices' => [],
            'consensus' => $summary,
            'staff_recommendations' => [],
            'issue_breakdown' => [],
            'stop_conditions' => [],
        ];
    }

    /** @return list<string> */
    protected function recommendationsForRole(string $role, array $plan): array
    {
        return match ($role) {
            'project-manager' => ['Create one issue per approved plan item and keep statuses visible to the CEO.'],
            'tech-lead' => ['Keep the first implementation slice small enough to verify in one focused test pass.'],
            'ui-ux-designer' => ['Check mobile and desktop text fit before calling the UI complete.'],
            'blog-writer' => ['Lead with useful substance before promotional language.'],
            'seo-writer' => ['Use search-intent headings and avoid keyword stuffing.'],
            'marketing-manager' => ['Make positioning specific to the product and audience.'],
            'sales-manager' => ['Tie claims to a buyer pain and clear next action.'],
            'qa' => ['Define acceptance checks from the approved plan before execution starts.'],
            'security' => ['Verify secrets, permissions, and destructive actions stay gated.'],
            'customer-support' => ['Add support-friendly wording when user confusion is likely.'],
            default => ['Keep feedback bounded and actionable.'],
        };
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    protected function issueBreakdown(array $plan): array
    {
        $items = is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [];
        $out = [];
        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = StringCoercion::toString($item['id'] ?? null, 'plan-'.($idx + 1));
            $owner = StringCoercion::toString($item['owner'] ?? null);
            $title = StringCoercion::toString($item['title'] ?? $item['description'] ?? null, 'Plan item '.($idx + 1));
            $out[] = [
                'plan_item_id' => $id,
                'title' => $title,
                'assignee_role_slug' => $this->assigneeFor($owner, $title, $idx),
                'priority' => $idx === 0 ? 'high' : 'medium',
            ];
        }

        return $out;
    }

    protected function assigneeFor(string $owner, string $title, int $idx): string
    {
        if ($idx === 0) {
            return 'tech-lead';
        }

        $haystack = strtolower($owner.' '.$title);
        if (str_contains($haystack, 'audit') || str_contains($haystack, 'test') || str_contains($haystack, 'verify')) {
            return 'qa';
        }
        if (str_contains($haystack, 'security') || str_contains($haystack, 'auth')) {
            return 'security';
        }
        if (str_contains($haystack, 'ui') || str_contains($haystack, 'ux') || str_contains($haystack, 'design')) {
            return 'ui-ux-designer';
        }

        return 'project-manager';
    }
}
