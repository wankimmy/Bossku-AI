<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkIssue extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_work_issues';

    protected $fillable = [
        'project_id',
        'goal_id',
        'parent_issue_id',
        'run_id',
        'source_plan_item_id',
        'title',
        'description',
        'status',
        'priority',
        'approval_state',
        'assignee_role_slug',
        'assignee_agent_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function parentIssue(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_issue_id');
    }

    public function assigneeAgent(): BelongsTo
    {
        return $this->belongsTo(SpecialistAgent::class, 'assignee_agent_id');
    }
}
