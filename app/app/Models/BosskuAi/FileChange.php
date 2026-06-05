<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileChange extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_file_changes';

    protected $fillable = [
        'run_id', 'run_step_id', 'file_path', 'change_type',
        'patch', 'reason', 'agent', 'audit_note', 'approval_status',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function runStep(): BelongsTo
    {
        return $this->belongsTo(RunStep::class, 'run_step_id');
    }
}
