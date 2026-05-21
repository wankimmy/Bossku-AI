<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_approvals';

    protected $fillable = [
        'run_id', 'run_step_id', 'operation_type', 'operation_description',
        'risk_level', 'evidence', 'status', 'decision_note',
        'decided_by', 'decided_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'metadata' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function runStep(): BelongsTo
    {
        return $this->belongsTo(RunStep::class, 'run_step_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
