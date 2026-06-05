<?php

namespace App\Models\BosskuAi;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RunStep extends Model
{
    use HasUuids;

    protected $table = 'bossku_ai_run_steps';

    protected $fillable = [
        'run_id', 'step_number', 'type', 'model', 'provider', 'skill_name',
        'status', 'input', 'output', 'rules_used', 'playbooks_used',
        'checklists_used', 'memory_used', 'latency_ms', 'token_estimate',
        'error', 'metadata', 'safe_reasoning_summary', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'rules_used' => 'array',
            'playbooks_used' => 'array',
            'checklists_used' => 'array',
            'memory_used' => 'array',
            'metadata' => 'array',
            'cost' => 'decimal:8',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class, 'run_step_id');
    }

    public function agentMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'run_step_id');
    }

    public function fileChanges(): HasMany
    {
        return $this->hasMany(FileChange::class, 'run_step_id');
    }
}
