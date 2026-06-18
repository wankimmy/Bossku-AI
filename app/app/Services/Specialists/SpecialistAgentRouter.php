<?php

namespace App\Services\Specialists;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Company\CompanyStaffService;
use Illuminate\Support\Str;

class SpecialistAgentRouter
{
    public function __construct(
        protected CompanyStaffService $companyStaff,
        protected SpecialistIntentClassifier $intentClassifier,
    ) {}

    public function matchForPrompt(string $prompt, ?Project $project, array $modelRoute = []): ?SpecialistAgent
    {
        return $this->matchDetailed($prompt, $project, $modelRoute)->agent;
    }

    public function matchDetailed(string $prompt, ?Project $project, array $modelRoute = []): SpecialistMatchResult
    {
        $intent = $this->intentClassifier->classify($prompt, $modelRoute);
        $prompt = trim($prompt);

        if ($prompt === '') {
            return new SpecialistMatchResult(null, 0, 'empty_prompt', $intent);
        }

        if ($project !== null) {
            $this->companyStaff->seedDefaults($project);
        }

        $agents = $this->agentsForProject($project);
        if ($agents->isEmpty()) {
            return new SpecialistMatchResult(null, 0, 'no_agents_available', $intent);
        }

        $best = null;
        $bestScore = 0;
        $bestReason = 'no_keyword_match';

        foreach ($agents as $agent) {
            [$score, $reason] = $this->scoreWithReason($agent, $prompt, $intent);
            if ($score > $bestScore) {
                $best = $agent;
                $bestScore = $score;
                $bestReason = $reason;
            }
        }

        if ($bestScore < 5) {
            $fallback = $this->fallbackForIntent($agents, $intent);
            if ($fallback !== null) {
                return new SpecialistMatchResult($fallback, 5, 'intent_fallback:'.$intent->value, $intent);
            }

            return new SpecialistMatchResult(null, $bestScore, $bestReason, $intent);
        }

        return new SpecialistMatchResult($best, $bestScore, $bestReason, $intent);
    }

    /** @return array<string, mixed> */
    public function payloadForAgent(SpecialistAgent $agent, int $score = 0, string $reason = '', ?SpecialistIntent $intent = null): array
    {
        return array_merge($agent->toOfficePayload(), [
            'match_score' => $score,
            'match_reason' => $reason !== '' ? $reason : 'manual_selection',
            'intent' => $intent?->value,
            'linked_skill_name' => $agent->linkedSkill?->name,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, SpecialistAgent> */
    protected function agentsForProject(?Project $project)
    {
        if ($project === null) {
            return collect();
        }

        return SpecialistAgent::query()
            ->approved()
            ->forProject($project)
            ->with('linkedSkill')
            ->orderBy('staff_sort_order')
            ->get();
    }

    /** @return array{0: int, 1: string} */
    protected function scoreWithReason(SpecialistAgent $agent, string $prompt, SpecialistIntent $intent): array
    {
        $haystack = Str::lower($prompt);
        $score = 0;
        $reasons = [];

        if (in_array($agent->role_slug, $intent->staffRoleSlugs(), true)) {
            $score += 8;
            $reasons[] = 'intent_role';
        }

        foreach ($agent->trigger_keywords ?? [] as $keyword) {
            $keyword = Str::lower(trim((string) $keyword));
            if ($keyword === '') {
                continue;
            }
            if (str_contains($haystack, $keyword)) {
                $score += 7;
                $reasons[] = 'keyword:'.$keyword;
            }
        }

        $searchable = Str::lower(implode(' ', array_filter([
            $agent->role_slug,
            $agent->display_name,
            $agent->description,
            $agent->linkedSkill?->name,
            $agent->linkedSkill?->description,
        ])));
        foreach ($this->tokens($prompt) as $token) {
            if (str_contains($searchable, $token)) {
                $score += 2;
                $reasons[] = 'token:'.$token;
            }
        }

        return [$score, $reasons === [] ? 'low_signal' : implode(',', array_slice($reasons, 0, 3))];
    }

    /** @param \Illuminate\Support\Collection<int, SpecialistAgent> $agents */
    protected function fallbackForIntent($agents, SpecialistIntent $intent): ?SpecialistAgent
    {
        foreach ($intent->staffRoleSlugs() as $slug) {
            $found = $agents->firstWhere('role_slug', $slug);
            if ($found !== null) {
                return $found;
            }
        }

        return $agents->first();
    }

    /** @return list<string> */
    protected function tokens(string $text): array
    {
        preg_match_all('/[a-z0-9][a-z0-9_-]{2,}/i', Str::lower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
