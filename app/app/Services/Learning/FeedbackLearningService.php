<?php

namespace App\Services\Learning;

use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\LearningEvent;

class FeedbackLearningService
{
    public function convertFeedbackToLearning(FeedbackItem $item): ?LearningEvent
    {
        if ($item->signal === 'flag') {
            return null;
        }

        if ($item->signal === 'thumbs_down') {
            return LearningEvent::create([
                'run_id'     => null,
                'type'       => 'correction',
                'content'    => filled($item->comment)
                    ? $item->comment
                    : 'User rejected this output on ' . $item->target_type . ':' . $item->target_id . ' (no comment provided).',
                'confidence' => filled($item->comment) ? 0.9 : 0.5,
                'evidence'   => [
                    'feedback_item_id' => $item->getKey(),
                    'target_type'      => $item->target_type,
                    'target_id'        => $item->target_id,
                    'signal'           => $item->signal,
                    'has_comment'      => filled($item->comment),
                ],
                'status' => 'pending',
            ]);
        }

        if ($item->signal === 'thumbs_up') {
            return LearningEvent::create([
                'run_id'     => null,
                'type'       => 'preference',
                'content'    => 'Positive signal on ' . $item->target_type . ':' . $item->target_id,
                'confidence' => 0.7,
                'evidence'   => [
                    'feedback_item_id' => $item->getKey(),
                    'target_type'      => $item->target_type,
                    'target_id'        => $item->target_id,
                    'signal'           => $item->signal,
                ],
                'status' => 'pending',
            ]);
        }

        return null;
    }
}
