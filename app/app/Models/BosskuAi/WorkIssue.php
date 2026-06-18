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
        'run_id',
        'source_plan_item_id',
        'title',
        'description',
        'status',
        'priority',
        'approval_state',
        'assignee_role_slug',
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

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
