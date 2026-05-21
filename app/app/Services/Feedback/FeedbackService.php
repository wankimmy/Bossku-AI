<?php

namespace App\Services\Feedback;

use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Skill;
use Illuminate\Support\Collection;

class FeedbackService
{
    public function submit(
        string $targetType,
        string $targetId,
        string $signal,
        ?int $rating,
        ?string $comment
    ): FeedbackItem {
        return FeedbackItem::create([
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'signal'      => $signal,
            'rating'      => $rating,
            'comment'     => $comment,
            'processed'   => false,
        ]);
    }

    public function process(FeedbackItem $item): void
    {
        if ($item->target_type === 'skill') {
            $skill = Skill::find($item->target_id);
            if ($skill) {
                if ($item->signal === 'thumbs_up') {
                    $skill->increment('feedback_score');
                } elseif ($item->signal === 'thumbs_down') {
                    $skill->decrement('feedback_score');
                }
            }
        }

        if ($item->target_type === 'memory') {
            $memory = Memory::find($item->target_id);
            if ($memory) {
                $current = (float) ($memory->confidence ?? 0.5);
                if ($item->signal === 'thumbs_up') {
                    $memory->confidence = min(1.0, $current + 0.05);
                } elseif ($item->signal === 'thumbs_down') {
                    $memory->confidence = max(0.0, $current - 0.05);
                }
                $memory->save();
            }
        }

        $item->processed    = true;
        $item->processed_at = now();
        $item->save();
    }

    public function forTarget(string $type, string $id): Collection
    {
        return FeedbackItem::where('target_type', $type)
            ->where('target_id', $id)
            ->get();
    }

    public function summary(string $type, string $id): array
    {
        $items = $this->forTarget($type, $id);

        return [
            'thumbs_up'   => $items->where('signal', 'thumbs_up')->count(),
            'thumbs_down' => $items->where('signal', 'thumbs_down')->count(),
            'avg_rating'  => $items->whereNotNull('rating')->avg('rating'),
            'count'       => $items->count(),
        ];
    }
}
