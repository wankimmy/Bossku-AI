<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Agents\MarkdownAgentPackLoader;

class TeamsCatalogService
{
    public function __construct(
        protected CompanyStaffService $staff,
        protected MarkdownAgentPackLoader $packLoader,
    ) {}

    /** @return list<array<string, mixed>> */
    public function teams(): array
    {
        return [
            [
                'slug' => 'core-engineering',
                'name' => 'Core Engineering',
                'roles' => ['project-manager', 'tech-lead', 'qa', 'security'],
            ],
            [
                'slug' => 'content-machine',
                'name' => 'Content Machine',
                'roles' => ['blog-writer', 'seo-writer', 'marketing-manager', 'customer-support'],
            ],
            [
                'slug' => 'growth-sales',
                'name' => 'Growth & Sales',
                'roles' => ['marketing-manager', 'sales-manager', 'seo-writer'],
            ],
            [
                'slug' => 'product-design',
                'name' => 'Product Design',
                'roles' => ['ui-ux-designer', 'tech-lead', 'qa'],
            ],
            [
                'slug' => 'security-qa',
                'name' => 'Security & QA',
                'roles' => ['security', 'qa', 'tech-lead'],
            ],
        ];
    }

    public function installTeam(Project $project, string $teamSlug): int
    {
        $team = collect($this->teams())->firstWhere('slug', $teamSlug);
        if ($team === null) {
            return 0;
        }

        $this->staff->seedDefaults($project);
        $roster = collect($this->staff->defaultRoster());
        $installed = 0;

        foreach ($team['roles'] as $roleSlug) {
            $row = $roster->firstWhere('role_slug', $roleSlug);
            if ($row === null) {
                continue;
            }
            $row['department'] = $team['slug'];
            SpecialistAgent::query()
                ->where('project_id', $project->id)
                ->where('role_slug', $row['role_slug'])
                ->where('is_company_staff', true)
                ->update([
                    'department' => $team['slug'],
                    'metadata' => [
                        'agent_mode' => 'subagent',
                        'source' => 'teams_catalog:'.$teamSlug,
                    ],
                ]);
            $this->packLoader->syncToProject([
                'role_slug' => $row['role_slug'],
                'display_name' => $row['display_name'],
                'description' => $row['description'],
                'trigger_keywords' => $row['trigger_keywords'],
                'persona_content' => $row['persona_content'],
                'runtime_mode' => $row['runtime_mode'],
                'department' => $team['slug'],
                'can_create_agents' => (bool) ($row['can_create_agents'] ?? false),
                'budget_policy' => $row['budget_policy'] ?? 'standard',
                'agent_mode' => 'subagent',
                'source' => 'teams_catalog:'.$teamSlug,
            ], $project->id);
            $installed++;
        }

        return $installed;
    }
}
