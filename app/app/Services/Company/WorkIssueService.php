<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\WorkIssue;
use App\Services\BosskuAi\RuntimeSettings;
use App\Support\StringCoercion;

class WorkIssueService
{
    public function __construct(
        protected RuntimeSettings $settings,
        protected CompanyStaffService $staff,
        protected AgentWakeupDispatcher $wakeups,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @return list<WorkIssue>
     */
    public function createFromApprovedPlan(Run $run, array $plan, ?Project $project): array
    {
        if ($project === null || ! $this->settings->staffAutoIssueGenerationEnabled()) {
            return [];
        }

        $this->staff->seedDefaults($project);
        $checklist = is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [];
        $breakdown = $this->breakdownByPlanItem($plan);
        $issues = [];

        foreach ($checklist as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $planItemId = StringCoercion::toString($item['id'] ?? null, 'plan-'.($idx + 1));
            $title = StringCoercion::toString($item['title'] ?? $item['description'] ?? null, 'Plan item '.($idx + 1));
            $description = StringCoercion::toString($item['description'] ?? $item['success_criterion'] ?? null);
            $staff = $breakdown[$planItemId] ?? [];
            $assigneeRole = StringCoercion::toString($staff['assignee_role_slug'] ?? null, $idx === 0 ? 'tech-lead' : 'project-manager');
            $assigneeAgent = $this->staff->agentForRole($project, $assigneeRole);

            $issue = WorkIssue::query()->firstOrCreate(
                [
                    'run_id' => $run->id,
                    'source_plan_item_id' => $planItemId,
                ],
                [
                    'project_id' => $project->id,
                    'title' => $title,
                    'description' => $description,
                    'status' => 'todo',
                    'priority' => StringCoercion::toString($staff['priority'] ?? null, $idx === 0 ? 'high' : 'medium'),
                    'approval_state' => 'approved',
                    'assignee_role_slug' => $assigneeRole,
                    'assignee_agent_id' => $assigneeAgent?->id,
                    'metadata' => [
                        'source' => 'approved_plan',
                        'owner' => StringCoercion::toString($item['owner'] ?? null),
                    ],
                ],
            );

            if ($assigneeAgent !== null && $issue->assignee_agent_id === null) {
                $issue->update(['assignee_agent_id' => $assigneeAgent->id]);
            }

            if ($assigneeAgent !== null) {
                $this->wakeups->enqueue(
                    $assigneeAgent,
                    $issue->refresh(),
                    $run,
                    'issue_assigned',
                    [
                        'prompt' => 'Review and advance work issue: '.$issue->title,
                        'task_key' => 'issue:'.$issue->id,
                    ],
                );
            }

            $issues[] = $issue->refresh();
        }

        return $issues;
    }

    public function enqueueForAssigneeChange(WorkIssue $issue, ?Run $run = null): void
    {
        if ($issue->assignee_agent_id === null) {
            return;
        }

        $agent = $issue->assigneeAgent;
        if ($agent === null) {
            return;
        }

        $this->wakeups->enqueue(
            $agent,
            $issue,
            $run ?? $issue->run,
            'issue_assigned',
            [
                'prompt' => 'You were assigned work issue: '.$issue->title,
                'task_key' => 'issue:'.$issue->id.':assignee',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, array<string, mixed>>
     */
    protected function breakdownByPlanItem(array $plan): array
    {
        $council = is_array($plan['staff_council'] ?? null) ? $plan['staff_council'] : [];
        $items = is_array($council['issue_breakdown'] ?? null) ? $council['issue_breakdown'] : [];
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = StringCoercion::toString($item['plan_item_id'] ?? null);
            if ($id !== '') {
                $out[$id] = $item;
            }
        }

        return $out;
    }
}
