<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Checklist as BosskuChecklist;
use App\Models\BosskuAi\Playbook;
use App\Models\BosskuAi\Rule;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SkillRouterService
{
    /** @var list<string> */
    protected array $stopwords = ['the', 'a', 'an', 'for', 'and', 'or', 'to', 'how', 'can', 'with', 'on', 'in', 'please', 'use', 'we', 'i', 'is', 'it', 'this', 'that'];

    /** @param Collection<int, \App\Models\BosskuAi\Memory> $memories */
    public function route(string $prompt, Collection $memories): array
    {
        /** @var Collection<int, Skill> $skills */
        $skills = Skill::query()->where('is_active', true)->get();

        /** @var array<string,float|int> $scores */
        $scores = [];

        foreach ($skills as $skill) {
            $scores[$skill->name] = $this->scoreSkill($prompt, $skill);
        }

        arsort($scores);
        $names = array_keys($scores);

        /** @var Skill|null $primary */
        $primary = null;
        $secondary = null;

        if ($names !== []) {
            $primary = Skill::query()->where('name', $names[0])->first();
            foreach (array_slice($names, 1) as $n) {
                if (($scores[$n] ?? 0) >= 6) {
                    $secondary = Skill::query()->where('name', $n)->first();
                    break;
                }
            }
        }

        $primary ??= Skill::query()->first();
        $primary ??= new Skill([
            'name' => 'cofounder',
            'description' => 'General fallback',
        ]);

        if ($secondary?->name === $primary->name) {
            $secondary = null;
        }

        /** @phpstan-ignore-next-line */
        $skillNames = array_filter([
            $primary->name ?? null,
            $secondary->name ?? null,
        ]);

        $rulesModels = Rule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($skillNames) {
                $q->where('scope', 'global')->orWhereIn('skill_name', $skillNames);
            })
            ->orderByDesc('priority')
            ->take(25)
            ->get()
            ->take(5);

        $rulePayload = [];
        foreach ($rulesModels as $r) {
            $rulePayload[] = [
                'name' => $r->name,
                'skill_name' => $r->skill_name,
                'text' => $r->rule_text,
                'priority' => $r->priority,
                'scope' => $r->scope,
            ];
        }

        $pbPayload = [];
        $clPayload = [];
        foreach (array_slice($skillNames, 0, 2) as $sName) {
            $skill = Skill::query()->where('name', $sName)->first();
            if (! $skill) {
                continue;
            }
            $linksPlay = SkillLink::query()->where('skill_id', $skill->id)->where('link_type', 'playbook')->take(3)->get();
            foreach ($linksPlay as $link) {
                $pb = Playbook::query()->find($link->linked_id);
                if ($pb && count($pbPayload) < 2) {
                    $pbPayload[] = [
                        'id' => $pb->id,
                        'name' => $pb->name,
                        'excerpt' => Str::limit(strip_tags($pb->content), 2500),
                    ];
                }
            }
            $linksChk = SkillLink::query()->where('skill_id', $skill->id)->where('link_type', 'checklist')->take(3)->get();
            foreach ($linksChk as $link) {
                $chk = BosskuChecklist::query()->find($link->linked_id);
                if ($chk && count($clPayload) < 2) {
                    $clPayload[] = [
                        'id' => $chk->id,
                        'name' => $chk->name,
                        'excerpt' => Str::limit(strip_tags($chk->content), 1500),
                    ];
                }
            }
        }

        return [
            'primary_skill' => [
                'name' => $primary->name,
                'reason' => 'Highest router score from keyword + tag + description match.',
            ],
            'secondary_skills' => $secondary
                ? [['name' => $secondary->name, 'reason' => 'Second-highest score above threshold.']]
                : [],
            'rules' => $rulePayload,
            'playbooks' => $pbPayload,
            'checklists' => $clPayload,
            '_scores' => $scores,
        ];
    }

    protected function scoreSkill(string $prompt, Skill $skill): int
    {
        $p = Str::lower($prompt);
        $text = Str::lower($skill->name.' '.$skill->description.' '.json_encode($skill->metadata));
        $tokens = array_filter(
            preg_split('/\W+/', $p) ?: [],
            fn ($t) => strlen((string) $t) > 2 && ! in_array((string) $t, $this->stopwords, true)
        );
        $score = 0;
        foreach ($tokens as $tok) {
            if (Str::contains($text, (string) $tok)) {
                $score += 2;
            }
        }
        if (str_contains($p, strtolower($skill->name))) {
            $score += 12;
        }
        $hints = data_get($skill->metadata, 'hints.tags');
        if (is_array($hints)) {
            foreach ($hints as $tag) {
                if (Str::contains($p, strtolower((string) $tag))) {
                    $score += 8;
                }
            }
        }
        $when = strtolower((string) data_get($skill->metadata, 'hints.when', ''));
        if ($when !== '') {
            foreach ($tokens as $tok) {
                if (str_contains($when, (string) $tok)) {
                    $score += 2;
                }
            }
        }
        if (($skill->metadata['fallback'] ?? false) === true) {
            $score -= 40;
        }

        return $score;
    }
}
