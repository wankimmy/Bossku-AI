<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialistAgent extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_specialist_agents';

    protected $fillable = [
        'project_id',
        'role_slug',
        'display_name',
        'description',
        'trigger_keywords',
        'persona_content',
        'linked_skill_id',
        'approval_status',
        'pixel_palette',
        'pixel_hue_shift',
        'seat_id',
        'usage_count',
        'last_used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'trigger_keywords' => 'array',
            'pixel_palette' => 'integer',
            'pixel_hue_shift' => 'integer',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function linkedSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'linked_skill_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeForProject(Builder $query, Project|string $project): Builder
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        return $query->where('project_id', $projectId);
    }

    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->forceFill(['last_used_at' => now()])->save();
    }

    /** @return array<string, mixed> */
    public function toOfficePayload(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'role_slug' => $this->role_slug,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'trigger_keywords' => $this->trigger_keywords ?? [],
            'linked_skill_id' => $this->linked_skill_id,
            'approval_status' => $this->approval_status,
            'pixel_palette' => $this->pixel_palette,
            'pixel_hue_shift' => $this->pixel_hue_shift,
            'seat_id' => $this->seat_id,
            'usage_count' => $this->usage_count,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
        ];
    }
}
