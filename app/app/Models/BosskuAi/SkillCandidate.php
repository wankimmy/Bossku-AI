<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillCandidate extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_skill_candidates';

    protected $fillable = [
        'name', 'description', 'category', 'draft_content',
        'approval_status', 'quality_score', 'source_run_count',
        'source_run_ids', 'tags', 'metadata', 'approved_skill_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_run_ids' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function approvedSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'approved_skill_id');
    }

    public function isRiskyCategory(): bool
    {
        return in_array($this->category, ['payment-gateway', 'security', 'deployment', 'auth']);
    }
}
