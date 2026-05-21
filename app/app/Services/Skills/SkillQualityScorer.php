<?php

namespace App\Services\Skills;

use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillCandidate;

class SkillQualityScorer
{
    public function score(Skill|SkillCandidate $skill): float
    {
        $content = $skill instanceof Skill ? $skill->content : $skill->draft_content;

        if (blank($content)) {
            return 0.0;
        }

        $score = 0.0;

        $description = $skill->description ?? '';
        if (filled($description)) {
            $score += 20;
            if (mb_strlen($description) > 50) {
                $score += 10;
            }
        }

        if (mb_strlen((string) $content) > 200) {
            $score += 20;
        }

        $rules = $skill->rules ?? [];
        if (! empty($rules)) {
            $score += 15;
        }

        $tools = $skill->tools ?? [];
        if (! empty($tools)) {
            $score += 15;
        }

        if ($skill instanceof Skill && (int) ($skill->usage_count ?? 0) > 0) {
            $score += 20;
        }

        return min(100.0, $score);
    }

    public function scoreAndSave(Skill $skill): float
    {
        $score = $this->score($skill);
        $skill->quality_score = $score;
        $skill->save();

        return $score;
    }
}
