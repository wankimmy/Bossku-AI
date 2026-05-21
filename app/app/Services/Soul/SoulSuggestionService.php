<?php

namespace App\Services\Soul;

use App\Models\BosskuAi\Run;

class SoulSuggestionService
{
    public function generate(Run $run): array
    {
        $suggestions = [];

        $steps = $run->steps()->orderBy('step_number')->get();
        if ($steps->isEmpty()) {
            return $suggestions;
        }

        $stepTypes = $steps->pluck('type')->filter()->all();
        $counts    = array_count_values($stepTypes);

        foreach ($counts as $type => $count) {
            if ($count >= 3) {
                $suggestions[] = 'Consider adding a soul rule to streamline repeated "' . $type . '" steps (appeared ' . $count . ' times in this run).';
            }
        }

        if (filled($run->selected_skill_name) && (float) ($run->audit_score ?? 0) > 0.9) {
            $suggestions[] = 'Run achieved high audit score (' . $run->audit_score . ') using skill "' . $run->selected_skill_name . '". Consider encoding this approach as a soul principle.';
        }

        if ((float) ($run->audit_score ?? 1) < 0.5) {
            $suggestions[] = 'Run had a low audit score (' . $run->audit_score . '). Review soul constraints that may be too restrictive or misaligned.';
        }

        return $suggestions;
    }
}
