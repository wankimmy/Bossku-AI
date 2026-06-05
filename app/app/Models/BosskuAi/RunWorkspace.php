<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunWorkspace extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_run_workspaces';

    protected $fillable = [
        'run_id',
        'project_id',
        'base_ref',
        'branch_name',
        'worktree_path',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
