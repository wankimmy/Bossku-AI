<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentWakeupRequest extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_agent_wakeup_requests';

    protected $fillable = [
        'specialist_agent_id',
        'work_issue_id',
        'run_id',
        'wake_reason',
        'status',
        'idempotency_key',
        'context_snapshot',
        'skip_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function specialistAgent(): BelongsTo
    {
        return $this->belongsTo(SpecialistAgent::class, 'specialist_agent_id');
    }

    public function workIssue(): BelongsTo
    {
        return $this->belongsTo(WorkIssue::class, 'work_issue_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }
}
