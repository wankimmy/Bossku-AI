<?php

namespace App\Services\Specialists;

use App\Models\BosskuAi\SpecialistAgent;

class SpecialistMatchResult
{
    public function __construct(
        public readonly ?SpecialistAgent $agent,
        public readonly int $score,
        public readonly string $reason,
        public readonly SpecialistIntent $intent,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        if ($this->agent === null) {
            return [
                'matched' => false,
                'score' => $this->score,
                'reason' => $this->reason,
                'intent' => $this->intent->value,
            ];
        }

        return array_merge($this->agent->toOfficePayload(), [
            'matched' => true,
            'match_score' => $this->score,
            'match_reason' => $this->reason,
            'intent' => $this->intent->value,
            'linked_skill_name' => $this->agent->linkedSkill?->name,
        ]);
    }
}
