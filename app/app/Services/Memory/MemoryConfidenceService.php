<?php

namespace App\Services\Memory;

use App\Models\BosskuAi\Memory;

class MemoryConfidenceService
{
    public function decay(Memory $memory): float
    {
        $isOldEnough = $memory->created_at <= now()->subDays(30);
        $notRecentlyUsed = $memory->last_used_at === null
            || $memory->last_used_at <= now()->subDays(30);

        if ($isOldEnough && $notRecentlyUsed) {
            $current = (float) ($memory->confidence ?? 0.5);
            $memory->confidence = max(0.0, $current - 0.05);
            $memory->save();
        }

        return (float) $memory->confidence;
    }

    public function boost(Memory $memory): float
    {
        $current = (float) ($memory->confidence ?? 0.5);
        $memory->confidence  = min(1.0, $current + 0.1);
        $memory->last_used_at = now();
        $memory->save();

        return (float) $memory->confidence;
    }

    public function markStale(Memory $memory): void
    {
        if ((float) ($memory->confidence ?? 1.0) < 0.3) {
            $memory->is_active = false;
            $memory->save();
        }
    }
}
