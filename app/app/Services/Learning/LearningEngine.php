<?php

namespace App\Services\Learning;

use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Run;

class LearningEngine
{
    public function extractFromRun(Run $run): array
    {
        $extractions = [];

        if ($run->status === 'completed' && (float) $run->audit_score > 0.7) {
            $extractions[] = [
                'type'       => 'pattern',
                'content'    => 'Completed run with high audit score: ' . ($run->selected_skill_name ?? 'unknown skill'),
                'confidence' => (float) $run->audit_score,
                'evidence'   => ['run_id' => $run->getKey(), 'audit_score' => $run->audit_score],
            ];
        }

        $thumbsUp = FeedbackItem::where('target_type', 'run')
            ->where('target_id', $run->getKey())
            ->where('signal', 'thumbs_up')
            ->exists();

        if ($thumbsUp) {
            $extractions[] = [
                'type'       => 'preference',
                'content'    => 'User expressed positive preference for run approach: ' . ($run->selected_skill_name ?? 'unknown skill'),
                'confidence' => 0.7,
                'evidence'   => ['run_id' => $run->getKey(), 'signal' => 'thumbs_up'],
            ];
        }

        return $extractions;
    }

    public function saveEvents(Run $run, array $extractions): void
    {
        foreach ($extractions as $extraction) {
            LearningEvent::create([
                'run_id'     => $run->getKey(),
                'type'       => $extraction['type'],
                'content'    => $extraction['content'],
                'confidence' => $extraction['confidence'],
                'evidence'   => $extraction['evidence'],
                'status'     => 'pending',
            ]);
        }
    }
}
