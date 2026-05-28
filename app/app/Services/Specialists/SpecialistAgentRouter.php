<?php

namespace App\Services\Specialists;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use Illuminate\Support\Str;

class SpecialistAgentRouter
{
    public function matchForPrompt(string $prompt, ?Project $project): ?SpecialistAgent
    {
        if ($project === null) {
            return null;
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        $agents = SpecialistAgent::query()
            ->approved()
            ->forProject($project)
            ->with('linkedSkill')
            ->get();

        $best = null;
        $bestScore = 0;
        foreach ($agents as $agent) {
            $score = $this->score($agent, $prompt);
            if ($score > $bestScore) {
                $best = $agent;
                $bestScore = $score;
            }
        }

        return $bestScore >= 5 ? $best : null;
    }

    /** @return array<string, mixed> */
    public function payloadForAgent(SpecialistAgent $agent, int $score = 0): array
    {
        return array_merge($agent->toOfficePayload(), [
            'match_score' => $score,
            'linked_skill_name' => $agent->linkedSkill?->name,
        ]);
    }

    protected function score(SpecialistAgent $agent, string $prompt): int
    {
        $haystack = Str::lower($prompt);
        $score = 0;

        foreach ($agent->trigger_keywords ?? [] as $keyword) {
            $keyword = Str::lower(trim((string) $keyword));
            if ($keyword === '') {
                continue;
            }

            if (str_contains($haystack, $keyword)) {
                $score += 7;
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
            }
        }

        return $score;
    }

    /** @return list<string> */
    protected function tokens(string $text): array
    {
        preg_match_all('/[a-z0-9][a-z0-9_-]{2,}/i', Str::lower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
