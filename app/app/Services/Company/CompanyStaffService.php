<?php

namespace App\Services\Company;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\BosskuAi\RuntimeSettings;
use App\Support\StringCoercion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CompanyStaffService
{
    public function __construct(
        protected RuntimeSettings $settings,
    ) {}

    /** @return list<array<string, mixed>> */
    public function defaultRoster(): array
    {
        return [
            [
                'role_slug' => 'project-manager',
                'display_name' => 'Project Manager',
                'description' => 'Turns CEO goals into approved work items, sequencing, risks, and delivery checkpoints.',
                'trigger_keywords' => ['project', 'plan', 'kanban', 'milestone', 'scope', 'delivery'],
                'persona_content' => 'You are the Project Manager. Convert CEO goals into bounded, approved work and keep the team honest about scope, dependencies, and status.',
                'runtime_mode' => 'mixed',
            ],
            [
                'role_slug' => 'tech-lead',
                'display_name' => 'Tech Lead',
                'description' => 'Owns technical approach, architecture tradeoffs, implementation sequencing, and engineering quality.',
                'trigger_keywords' => ['architecture', 'implementation', 'backend', 'frontend', 'code', 'technical', 'engineering'],
                'persona_content' => 'You are the Tech Lead. Choose practical architecture, identify implementation risks, and keep engineering work testable and maintainable.',
                'runtime_mode' => 'mixed',
            ],
            [
                'role_slug' => 'ui-ux-designer',
                'display_name' => 'UI/UX Designer',
                'description' => 'Reviews product flows, layout clarity, interface ergonomics, and user-facing interaction details.',
                'trigger_keywords' => ['ui', 'ux', 'design', 'layout', 'screen', 'user experience', 'frontend'],
                'persona_content' => 'You are the UI/UX Designer. Make interfaces clear, efficient, and polished without adding decorative complexity.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'blog-writer',
                'display_name' => 'Blog Writer',
                'description' => 'Produces article structure, narrative flow, drafts, and editorial improvements.',
                'trigger_keywords' => ['blog', 'article', 'post', 'newsletter', 'writing', 'editorial'],
                'persona_content' => 'You are the Blog Writer. Produce clear, useful long-form content with a strong angle and clean structure.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'seo-writer',
                'display_name' => 'SEO Writer',
                'description' => 'Improves search intent, headings, keywords, metadata, and content discoverability.',
                'trigger_keywords' => ['seo', 'keyword', 'search', 'metadata', 'ranking', 'organic'],
                'persona_content' => 'You are the SEO Writer. Improve discoverability while preserving accuracy and readable copy.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'marketing-manager',
                'display_name' => 'Marketing Manager',
                'description' => 'Shapes campaign strategy, positioning, launch messaging, channels, and growth experiments.',
                'trigger_keywords' => ['marketing', 'campaign', 'positioning', 'launch', 'growth', 'brand'],
                'persona_content' => 'You are the Marketing Manager. Align the output with positioning, audience, channels, and credible growth strategy.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'sales-manager',
                'display_name' => 'Sales Manager',
                'description' => 'Improves offers, objections, outreach, pipeline thinking, and conversion-oriented messaging.',
                'trigger_keywords' => ['sales', 'lead', 'outreach', 'proposal', 'conversion', 'objection'],
                'persona_content' => 'You are the Sales Manager. Make the output commercially useful, specific, and grounded in buyer objections.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'qa',
                'display_name' => 'QA',
                'description' => 'Checks acceptance criteria, edge cases, regression risk, and verification strategy.',
                'trigger_keywords' => ['qa', 'test', 'verify', 'regression', 'acceptance', 'quality'],
                'persona_content' => 'You are QA. Define what must be tested, which edge cases matter, and where regressions are likely.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'security',
                'display_name' => 'Security',
                'description' => 'Flags security, privacy, permissions, secrets, and abuse risks before execution.',
                'trigger_keywords' => ['security', 'auth', 'permission', 'secret', 'privacy', 'risk'],
                'persona_content' => 'You are Security. Identify practical security and privacy risks and make bounded mitigation suggestions.',
                'runtime_mode' => 'advisory',
            ],
            [
                'role_slug' => 'customer-support',
                'display_name' => 'Customer Support',
                'description' => 'Reviews user confusion, support burden, docs gaps, and customer-facing communication.',
                'trigger_keywords' => ['support', 'customer', 'docs', 'help', 'faq', 'user issue'],
                'persona_content' => 'You are Customer Support. Surface likely user confusion and suggest concise support-friendly improvements.',
                'runtime_mode' => 'advisory',
            ],
        ];
    }

    /** @return Collection<int, SpecialistAgent> */
    public function seedDefaults(Project $project): Collection
    {
        if (! $this->settings->companyStaffEnabled()) {
            return new Collection;
        }

        foreach ($this->defaultRoster() as $index => $row) {
            SpecialistAgent::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'role_slug' => $row['role_slug'],
                ],
                [
                    'display_name' => $row['display_name'],
                    'description' => $row['description'],
                    'trigger_keywords' => $row['trigger_keywords'],
                    'persona_content' => $row['persona_content'],
                    'approval_status' => 'approved',
                    'is_company_staff' => true,
                    'staff_active' => true,
                    'council_enabled' => true,
                    'runtime_mode' => $row['runtime_mode'],
                    'staff_sort_order' => $index + 1,
                    'metadata' => ['seeded_by' => 'company_staff_mvp'],
                ],
            );
        }

        return $this->staffForProject($project);
    }

    /** @return Collection<int, SpecialistAgent> */
    public function staffForProject(Project $project, bool $activeOnly = false): Collection
    {
        $query = SpecialistAgent::query()
            ->where('project_id', $project->id)
            ->where('is_company_staff', true)
            ->orderBy('staff_sort_order')
            ->orderBy('display_name');

        if ($activeOnly) {
            $query->where('staff_active', true);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @return Collection<int, SpecialistAgent>
     */
    public function selectForCouncil(string $prompt, array $plan, array $modelRoute, ?Project $project): Collection
    {
        if ($project === null || ! $this->settings->companyStaffEnabled()) {
            return new Collection;
        }

        $this->seedDefaults($project);
        $staff = SpecialistAgent::query()
            ->where('project_id', $project->id)
            ->where('is_company_staff', true)
            ->where('staff_active', true)
            ->where('council_enabled', true)
            ->orderBy('staff_sort_order')
            ->get();

        $haystack = Str::lower(implode(' ', array_filter([
            $prompt,
            json_encode($plan) ?: '',
            json_encode($modelRoute) ?: '',
        ])));

        $selected = $staff->filter(function (SpecialistAgent $agent) use ($haystack, $modelRoute): bool {
            if (in_array($agent->role_slug, ['project-manager', 'tech-lead'], true)) {
                return true;
            }

            if (($modelRoute['needs_executor'] ?? false) && in_array($agent->role_slug, ['qa', 'security'], true)) {
                return true;
            }

            foreach ($agent->trigger_keywords ?? [] as $keyword) {
                $keyword = Str::lower(trim((string) $keyword));
                if ($keyword !== '' && str_contains($haystack, $keyword)) {
                    return true;
                }
            }

            if (($modelRoute['needs_auditor'] ?? false) && $agent->role_slug === 'qa') {
                return true;
            }
            if (($modelRoute['needs_security_auditor'] ?? false) && $agent->role_slug === 'security') {
                return true;
            }

            return false;
        });

        return new Collection($selected->values()->all());
    }

    public function positionFor(SpecialistAgent $agent, array $plan = []): string
    {
        $goal = StringCoercion::toString($plan['goal'] ?? $plan['summary'] ?? null, 'the approved CEO goal');

        return match ($agent->role_slug) {
            'project-manager' => 'Break '.$goal.' into approved, trackable work and keep follow-up execution CEO-gated.',
            'tech-lead' => 'Keep the technical implementation bounded, testable, and aligned with existing architecture.',
            'ui-ux-designer' => 'Check that the user-facing flow is clear, scannable, and not overloaded.',
            'blog-writer' => 'Make content useful, structured, and readable for the intended audience.',
            'seo-writer' => 'Align headings, intent, and terms without sacrificing accuracy.',
            'marketing-manager' => 'Make positioning concrete and avoid broad claims the product cannot support.',
            'sales-manager' => 'Tie messaging to buyer pain, objections, and a clear next action.',
            'qa' => 'Define acceptance checks and regression risks before the work is called done.',
            'security' => 'Flag permission, privacy, secret, and abuse risks before execution expands.',
            'customer-support' => 'Reduce likely user confusion and make the final output easier to support.',
            default => 'Contribute a bounded staff perspective before CEO approval.',
        };
    }
}
