<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business goal that work rolls up to — Paperclip's "manage goals, not PRs".
 * Goals form a tree (parent/child) and aggregate progress from sub-goals, a
 * numeric target metric, or linked work issues.
 */
class Goal extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_goals';

    protected $fillable = [
        'project_id',
        'parent_goal_id',
        'title',
        'description',
        'status',
        'priority',
        'target_metric',
        'target_value',
        'current_value',
        'progress',
        'due_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:4',
            'current_value' => 'decimal:4',
            'progress' => 'integer',
            'due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function parentGoal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_goal_id');
    }

    public function childGoals(): HasMany
    {
        return $this->hasMany(self::class, 'parent_goal_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(WorkIssue::class, 'goal_id');
    }
}
